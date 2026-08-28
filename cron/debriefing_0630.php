<?php
declare(strict_types=1);

// Täglich um 06:30 Uhr per Cron aufrufen. Fasst das Feedback ALLER
// Mitarbeiter vom Vortag zu EINEM gemeinsamen Team-Debriefing +
// Motivationsspruch zusammen und verschickt diesen identischen Text
// an alle aktiven Mitarbeiter.

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/claude_client.php';
require_once __DIR__ . '/../lib/format_feedback.php';
require_once __DIR__ . '/../lib/cron_auth.php';

require_cron_auth();

$pdo = get_db();
$feedbackDate = date('Y-m-d', strtotime('-1 day'));

// Alle Einreichungen des Vortags einsammeln, um daraus EIN
// gemeinsames Feedback-Dokument fürs Team zu bauen.
$submissions = $pdo->prepare(
    'SELECT fs.id AS submission_id, e.name
     FROM feedback_submissions fs
     JOIN employees e ON e.id = fs.employee_id
     WHERE fs.feedback_date = ? AND e.active = 1
     ORDER BY e.name'
);
$submissions->execute([$feedbackDate]);
$submissionRows = $submissions->fetchAll();

if (count($submissionRows) === 0) {
    echo "Keine Einreichungen für {$feedbackDate}, kein Debriefing generiert.\n";
    exit;
}

// Alle aktiven Mitarbeiter sind die Empfänger - unabhängig davon, ob
// sie selbst Feedback abgegeben haben, damit das ganze Team informiert ist.
$recipients = $pdo->query('SELECT id, name, email FROM employees WHERE active = 1')->fetchAll();

$pending = array_filter($recipients, function (array $employee) use ($pdo, $feedbackDate) {
    $check = $pdo->prepare(
        'SELECT 1 FROM debriefings WHERE employee_id = ? AND debriefing_date = ?'
    );
    $check->execute([$employee['id'], $feedbackDate]);
    return $check->fetchColumn() === false;
});

if (count($pending) === 0) {
    echo "Team-Debriefing für {$feedbackDate} wurde bereits an alle aktiven Mitarbeiter verschickt.\n";
    exit;
}

$answerStmt = $pdo->prepare(
    'SELECT question_key, answer_value FROM feedback_answers WHERE submission_id = ?'
);

$feedbackBlocks = [];
foreach ($submissionRows as $row) {
    $answerStmt->execute([$row['submission_id']]);
    $answersByKey = [];
    foreach ($answerStmt->fetchAll() as $a) {
        $answersByKey[$a['question_key']] = $a['answer_value'];
    }
    $feedbackBlocks[] = "### {$row['name']}\n" . format_answers_for_prompt($answersByKey);
}
$combinedFeedback = implode("\n\n", $feedbackBlocks);

try {
    $result = generate_team_debriefing($combinedFeedback, count($submissionRows));
} catch (Throwable $e) {
    $msg = "debriefing_0630.php: Claude-Fehler: " . $e->getMessage();
    error_log($msg);
    echo $msg . "\n";
    exit(1);
}

$body = "Guten Morgen,\n\n"
    . "euer Team-Debriefing zum gestrigen Messetag:\n\n"
    . $result['summary'] . "\n\n"
    . "Für heute:\n\"" . $result['quote'] . "\"\n\n"
    . "Einen guten Start in den Tag!";

$insertDebriefing = $pdo->prepare(
    'INSERT INTO debriefings (employee_id, debriefing_date, summary_text, motivation_quote, sent_at)
     VALUES (?, ?, ?, ?, ?)'
);

$sent = 0;
$failed = 0;

foreach ($pending as $employee) {
    $mailOk = send_mail($employee['email'], 'Euer Team-Debriefing & Spruch des Tages', $body);
    $sentAt = $mailOk ? date('Y-m-d H:i:s') : null;

    $insertDebriefing->execute([
        $employee['id'],
        $feedbackDate,
        $result['summary'],
        $result['quote'],
        $sentAt,
    ]);

    if ($mailOk) {
        $sent++;
    } else {
        $msg = "debriefing_0630.php: Mail an {$employee['email']} konnte nicht gesendet werden: " . (get_last_mail_error() ?? 'unbekannter Fehler');
        error_log($msg);
        echo $msg . "\n";
        $failed++;
    }
}

echo "Team-Debriefing verschickt an: {$sent}, Fehler: {$failed}, Empfänger gesamt: " . count($pending) . "\n";
