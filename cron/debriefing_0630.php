<?php
declare(strict_types=1);

// Täglich um 06:30 Uhr per Cron aufrufen. Fasst das Feedback ALLER
// Mitarbeiter vom Vortag zu EINER gemeinsamen Team-Zusammenfassung +
// Handlungsempfehlung + Motivationsspruch für HEUTE zusammen und
// verschickt diesen identischen Text an alle aktiven Mitarbeiter.
// Wird bei mehrfachem Aufruf am selben Tag jedes Mal erneut generiert
// und verschickt (zu Testzwecken keine "schon verschickt"-Sperre mehr).

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

// Alle aktiven Mitarbeiter sind die Empfänger - unabhängig davon, ob
// sie selbst Feedback abgegeben haben, damit das ganze Team informiert ist.
$recipients = $pdo->query('SELECT id, name, email FROM employees WHERE active = 1')->fetchAll();

if (count($recipients) === 0) {
    echo "Keine aktiven Mitarbeiter, kein Debriefing verschickt.\n";
    exit;
}

if (count($submissionRows) === 0) {
    // Trotzdem verschicken - das Team soll auch an einem Tag ohne
    // Rückmeldungen eine Mail bekommen, statt gar nichts zu hören.
    $result = [
        'summary' => 'Für den gestrigen Messetag lag leider kein Feedback aus dem Team vor.',
        'action' => 'Nehmt euch heute kurz Zeit, das Tagesfeedback auszufüllen, damit die Zusammenfassung morgen wieder alle Eindrücke des Teams abbildet.',
        'quote' => 'Heute ist ein guter Tag für einen guten Tag – auf geht’s!',
    ];
} else {
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
}

$subject = 'Guten Morgen – anbei euer Messefeedback für heute';

$body = "Guten Morgen,\n\n"
    . "anbei euer Messefeedback für heute – hier die Zusammenfassung von gestern:\n\n"
    . $result['summary'] . "\n\n"
    . "Für heute empfehlen wir:\n" . $result['action'] . "\n\n"
    . "\"" . $result['quote'] . "\"\n\n"
    . "Einen guten Start in den Tag!";

$insertDebriefing = $pdo->prepare(
    'INSERT INTO debriefings (employee_id, debriefing_date, summary_text, action_text, motivation_quote, sent_at)
     VALUES (?, ?, ?, ?, ?, ?)'
);

$sent = 0;
$failed = 0;

foreach ($recipients as $employee) {
    $mailOk = send_mail($employee['email'], $subject, $body);
    $sentAt = $mailOk ? date('Y-m-d H:i:s') : null;

    $insertDebriefing->execute([
        $employee['id'],
        $feedbackDate,
        $result['summary'],
        $result['action'],
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

echo "Team-Debriefing verschickt an: {$sent}, Fehler: {$failed}, Empfänger gesamt: " . count($recipients) . "\n";
