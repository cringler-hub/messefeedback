<?php
// Vorlage für die echte Konfiguration.
// Auf dem Server nach config.php kopieren und mit echten Werten
// füllen. config.php wird per .htaccess vor Web-Zugriff geschützt
// und ist in .gitignore eingetragen – NICHT ins Git-Repo committen.

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'dbname',
        'user' => 'dbuser',
        'pass' => 'dbpass',
    ],
    'mail' => [
        // Absenderadresse für Erinnerungs- und Debriefing-Mails.
        'from_address' => 'c.ringler@ringler-online.com',
        'from_name'    => 'Innotrans Messefeedback',
    ],
    'claude' => [
        'api_key' => 'sk-ant-...',
        // Günstiges, schnelles Modell reicht für kurze Zusammenfassungen.
        'model'   => 'claude-haiku-4-5-20251001',
    ],
    'app' => [
        'base_url'    => 'https://www.ringler-online.com/messefeedback',
        // Zufälligen, langen String einsetzen. Wird als ?token=...
        // an die Cron-URLs angehängt, damit nicht jeder im Internet
        // die Cronjobs auslösen kann.
        'cron_secret' => 'change-me-to-a-long-random-string',
        'timezone'    => 'Europe/Berlin',
    ],
];
