<?php
declare(strict_types=1);

// Täglich um 06:30 Uhr per Cron aufrufen. Holt das Feedback des
// Vortags je Mitarbeiter, lässt Claude ein kurzes Debriefing plus
// Motivationsspruch erzeugen und verschickt beides per Mail.
// Mitarbeiter ohne Feedback vom Vortag werden übersprungen.

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/claude_client.php';
require_once __DIR__ . '/../lib/format_feedback.php';
require_once __DIR__ . '/../lib/cron_auth.php';

require_cron_auth();

$pdo = get_db();
$feedbackDate = date('Y-m-d', strtotime('-1 day'));

$submissions = $pdo->prepare(
    "SELECT fs.id AS submission_id, e.id AS employee_id, e.name, e.email
     FROM feedback_submissions fs
     JOIN employees e ON e.id = fs.employee_id
     WHERE fs.feedback_date = ?
       AND e.active = 1
       AND NOT EXISTS (
           SELECT 1 FROM debriefings d
           WHERE d.employee_id = e.id AND d.debriefing_date = ?
       )"
);
$submissions->execute([$feedbackDate, $feedbackDate]);
$rows = $submissions->fetchAll();

$answerStmt = $pdo->prepare(
    'SELECT question_key, answer_value FROM feedback_answers WHERE submission_id = ?'
);
$insertDebriefing = $pdo->prepare(
    'INSERT INTO debriefings (employee_id, debriefing_date, summary_text, motivation_quote, sent_at)
     VALUES (?, ?, ?, ?, ?)'
);

$sent = 0;
$failed = 0;

foreach ($rows as $row) {
    $answerStmt->execute([$row['submission_id']]);
    $answersByKey = [];
    foreach ($answerStmt->fetchAll() as $a) {
        $answersByKey[$a['question_key']] = $a['answer_value'];
    }

    $feedbackText = format_answers_for_prompt($answersByKey);

    try {
        $result = generate_debriefing($row['name'], $feedbackText);
    } catch (Throwable $e) {
        error_log("debriefing_0630.php: Claude-Fehler für employee_id={$row['employee_id']}: " . $e->getMessage());
        $failed++;
        continue;
    }

    $body = "Guten Morgen {$row['name']},\n\n"
        . "dein Debriefing zum gestrigen Messetag:\n\n"
        . $result['summary'] . "\n\n"
        . "Für heute:\n\"" . $result['quote'] . "\"\n\n"
        . "Einen guten Start in den Tag!";

    $mailOk = send_mail($row['email'], 'Dein Debriefing & Spruch des Tages', $body);
    $sentAt = $mailOk ? date('Y-m-d H:i:s') : null;

    $insertDebriefing->execute([
        $row['employee_id'],
        $feedbackDate,
        $result['summary'],
        $result['quote'],
        $sentAt,
    ]);

    if ($mailOk) {
        $sent++;
    } else {
        error_log("debriefing_0630.php: Mail an {$row['email']} konnte nicht gesendet werden.");
        $failed++;
    }
}

echo "Debriefings verschickt: {$sent}, Fehler: {$failed}, Kandidaten: " . count($rows) . "\n";
