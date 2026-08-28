<?php
declare(strict_types=1);

// Täglich um 18:00 Uhr per Cron aufrufen. Verschickt an alle aktiven
// Mitarbeiter, die heute noch kein Feedback abgegeben haben, eine
// kurze Erinnerungsmail mit Link zum Formular.

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/cron_auth.php';

require_cron_auth();

$config = load_config();
$pdo = get_db();

$today = date('Y-m-d');

$pending = $pdo->prepare(
    "SELECT e.id, e.name, e.email
     FROM employees e
     WHERE e.active = 1
       AND NOT EXISTS (
           SELECT 1 FROM feedback_submissions fs
           WHERE fs.employee_id = e.id AND fs.feedback_date = ?
       )
       AND NOT EXISTS (
           SELECT 1 FROM reminder_log rl
           WHERE rl.employee_id = e.id AND rl.reminder_date = ?
       )"
);
$pending->execute([$today, $today]);
$pending = $pending->fetchAll();

$formUrl = rtrim($config['app']['base_url'], '/') . '/';
$logStmt = $pdo->prepare(
    'INSERT INTO reminder_log (employee_id, reminder_date) VALUES (?, ?)'
);

$sent = 0;
foreach ($pending as $employee) {
    $body = "Hallo {$employee['name']},\n\n"
        . "kurze Erinnerung: Bitte fülle noch dein Tagesfeedback zur Innotrans aus, "
        . "dauert nur 1 Minute:\n{$formUrl}\n\n"
        . "Danke und viele Grüße!";

    if (send_mail($employee['email'], 'Kurze Erinnerung: Tagesfeedback Innotrans', $body)) {
        $logStmt->execute([$employee['id'], $today]);
        $sent++;
    } else {
        $msg = "reminder_18h.php: Mail an {$employee['email']} konnte nicht gesendet werden: " . (get_last_mail_error() ?? 'unbekannter Fehler');
        error_log($msg);
        echo $msg . "\n";
    }
}

echo "Erinnerungen verschickt: {$sent} von " . count($pending) . "\n";
