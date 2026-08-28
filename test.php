<?php
// Temporäre Diagnose-Datei zur Fehlersuche bei HTTP 500.
// Nach erfolgreicher Diagnose wieder löschen.

echo "PHP läuft. Version: " . PHP_VERSION . "\n\n";

echo "Erweiterungen:\n";
foreach (['pdo', 'pdo_mysql', 'mbstring', 'curl'] as $ext) {
    echo "- {$ext}: " . (extension_loaded($ext) ? "OK" : "FEHLT") . "\n";
}

echo "\nconfig.php vorhanden: " . (is_file(__DIR__ . '/config.php') ? "JA" : "NEIN") . "\n";

if (is_file(__DIR__ . '/config.php')) {
    echo "config.php lesbar: " . (is_readable(__DIR__ . '/config.php') ? "JA" : "NEIN") . "\n";
    try {
        $config = require __DIR__ . '/config.php';
        echo "config.php parsebar: JA\n";
        echo "DB-Host aus Config: " . ($config['db']['host'] ?? '(leer)') . "\n";

        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db']['host'], $config['db']['name']);
            $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
            echo "DB-Verbindung: OK\n";
        } catch (Throwable $e) {
            echo "DB-Verbindung FEHLGESCHLAGEN: " . $e->getMessage() . "\n";
        }
    } catch (Throwable $e) {
        echo "config.php Fehler beim Laden: " . $e->getMessage() . "\n";
    }
}

echo "\nDateirechte index.php: " . (is_file(__DIR__ . '/index.php') ? substr(sprintf('%o', fileperms(__DIR__ . '/index.php')), -4) : "Datei fehlt") . "\n";
