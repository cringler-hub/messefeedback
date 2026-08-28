<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Cron-Skripte liegen im Webroot, damit sie sowohl per CLI-Cron als
// auch per URL-Cron (bei IONOS je nach Tarif nur URL-Aufruf möglich)
// funktionieren. Per HTTP ist daher ein Secret-Token Pflicht, damit
// nicht jeder im Internet den Job auslösen kann.
function require_cron_auth(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    $expected = load_config()['app']['cron_secret'];
    $given = $_GET['token'] ?? '';

    if (!is_string($given) || $expected === '' || !hash_equals($expected, $given)) {
        http_response_code(403);
        die('Forbidden');
    }
}
