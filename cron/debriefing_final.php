<?php
declare(strict_types=1);

// EINMALIGES Abschluss-Skript für den Morgen nach dem letzten
// Messetag. Fasst den letzten Tag zusammen, liefert zusätzlich eine
// Gesamtrückschau über die komplette Messe (alle Tage) und bedankt
// sich persönlich beim Team. Für den täglichen Automatikbetrieb
// weiterhin debriefing_0630.php verwenden - dieses Skript einmalig
// manuell bzw. am letzten Tag statt dessen aufrufen.

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/claude_client.php';
require_once __DIR__ . '/../lib/format_feedback.php';
require_once __DIR__ . '/../lib/cron_auth.php';

require_cron_auth();

$pdo = get_db();
$lastDay = date('Y-m-d', strtotime('-1 day'));

$recipients = $pdo->query('SELECT id, name, email FROM employees WHERE active = 1')->fetchAll();

if (count($recipients) === 0) {
    echo "Keine aktiven Mitarbeiter, kein Abschluss-Debriefing verschickt.\n";
    exit;
}

$answerStmt = $pdo->prepare(
    'SELECT question_key, answer_value FROM feedback_answers WHERE submission_id = ?'
);

/**
 * @param array<int, array{submission_id: int, name: string, feedback_date?: string}> $rows
 */
function build_feedback_text(PDOStatement $answerStmt, array $rows): string
{
    $blocks = [];
    foreach ($rows as $row) {
        $answerStmt->execute([$row['submission_id']]);
        $answersByKey = [];
        foreach ($answerStmt->fetchAll() as $a) {
            $answersByKey[$a['question_key']] = $a['answer_value'];
        }
        $label = isset($row['feedback_date']) ? "{$row['feedback_date']} – {$row['name']}" : $row['name'];
        $blocks[] = "### {$label}\n" . format_answers_for_prompt($answersByKey);
    }

    return implode("\n\n", $blocks);
}

// Nur der letzte Messetag.
$lastDayStmt = $pdo->prepare(
    'SELECT fs.id AS submission_id, e.name
     FROM feedback_submissions fs
     JOIN employees e ON e.id = fs.employee_id
     WHERE fs.feedback_date = ? AND e.active = 1
     ORDER BY e.name'
);
$lastDayStmt->execute([$lastDay]);
$lastDayFeedback = build_feedback_text($answerStmt, $lastDayStmt->fetchAll());

// Die komplette Messe, alle Tage.
$allEventRows = $pdo->query(
    'SELECT fs.id AS submission_id, fs.feedback_date, e.name
     FROM feedback_submissions fs
     JOIN employees e ON e.id = fs.employee_id
     WHERE e.active = 1
     ORDER BY fs.feedback_date, e.name'
)->fetchAll();
$allEventFeedback = build_feedback_text($answerStmt, $allEventRows);
$eventDayCount = max(count(array_unique(array_column($allEventRows, 'feedback_date'))), 1);

try {
    $result = generate_event_wrap_up($lastDayFeedback, $allEventFeedback, count($recipients), $eventDayCount);
} catch (Throwable $e) {
    $msg = "debriefing_final.php: Claude-Fehler: " . $e->getMessage();
    error_log($msg);
    echo $msg . "\n";
    exit(1);
}

$subject = 'Danke euch allen – euer Innotrans-Abschluss-Feedback';

$body = "Guten Morgen,\n\n"
    . "die Innotrans ist geschafft – hier noch ein letztes Feedback von uns:\n\n"
    . "Der gestrige, letzte Messetag:\n" . $result['daily_summary'] . "\n\n"
    . "Die gesamte Messe im Rückblick:\n" . $result['event_summary'] . "\n\n"
    . $result['thanks'] . "\n\n"
    . "Vielen Dank und bis zum nächsten Mal!";

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
        $lastDay,
        $result['daily_summary'],
        $result['event_summary'],
        $result['thanks'],
        $sentAt,
    ]);

    if ($mailOk) {
        $sent++;
    } else {
        $msg = "debriefing_final.php: Mail an {$employee['email']} konnte nicht gesendet werden: " . (get_last_mail_error() ?? 'unbekannter Fehler');
        error_log($msg);
        echo $msg . "\n";
        $failed++;
    }
}

echo "Abschluss-Debriefing verschickt an: {$sent}, Fehler: {$failed}, Empfänger gesamt: " . count($recipients) . "\n";
