<?php
declare(strict_types=1);

function load_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $path = __DIR__ . '/../config.php';
    if (!is_file($path)) {
        http_response_code(500);
        die('config.php fehlt. config.example.php kopieren und mit echten Werten füllen.');
    }

    $config = require $path;
    date_default_timezone_set($config['app']['timezone'] ?? 'Europe/Berlin');

    return $config;
}
