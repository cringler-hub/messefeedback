<?php
declare(strict_types=1);

// Täglich um 17:45 Uhr per Cron aufrufen. Verschickt bei jedem Aufruf
// ausnahmslos an ALLE aktiven Mitarbeiter eine Erinnerungsmail mit
// Link zum Formular - unabhängig davon, ob schon Feedback vorliegt,
// und auch mehrfach am selben Tag (reminder_log dient nur als Verlauf).

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/cron_auth.php';

require_cron_auth();

$config = load_config();
$pdo = get_db();

$today = date('Y-m-d');

$recipients = $pdo->query('SELECT id, name, email FROM employees WHERE active = 1')->fetchAll();

$formUrl = rtrim($config['app']['base_url'], '/') . '/';
$logStmt = $pdo->prepare(
    'INSERT INTO reminder_log (employee_id, reminder_date) VALUES (?, ?)'
);

$sent = 0;
foreach ($recipients as $employee) {
    $body = "Hallo {$employee['name']},\n\n"
        . "kurze Erinnerung: Bitte fülle dein Tagesfeedback zur Innotrans aus, "
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

echo "Erinnerungen verschickt: {$sent} von " . count($recipients) . "\n";
