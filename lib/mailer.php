<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

class SmtpException extends RuntimeException
{
}

// Minimaler SMTP-Client per PHP-Sockets (STARTTLS + AUTH LOGIN).
// IONOS' PHP mail() liefert auf Shared Hosting keine zuverlässige
// Zustellung, echter SMTP-Versand über das Postfach ist der
// verlässliche Weg.
function send_mail(string $to, string $subject, string $body): bool
{
    $config = load_config();
    $mailCfg = $config['mail'];
    $smtpCfg = $config['smtp'];

    $fromAddress = $mailCfg['from_address'];
    $fromName = mb_encode_mimeheader($mailCfg['from_name'], 'UTF-8');
    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');

    $headers = implode("\r\n", [
        "From: {$fromName} <{$fromAddress}>",
        "To: <{$to}>",
        "Subject: {$encodedSubject}",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ]);

    // Dot-Stuffing: Zeilen, die mit einem Punkt beginnen, würden das
    // Daten-Ende der SMTP-Session vortäuschen.
    $dataBody = preg_replace('/^\./m', '..', $body);
    $data = $headers . "\r\n\r\n" . $dataBody;

    try {
        smtp_deliver($smtpCfg, $fromAddress, $to, $data);
        return true;
    } catch (Throwable $e) {
        error_log('send_mail: ' . $e->getMessage());
        return false;
    }
}

function smtp_deliver(array $smtpCfg, string $from, string $to, string $data): void
{
    $socket = @fsockopen($smtpCfg['host'], (int) $smtpCfg['port'], $errno, $errstr, 15);
    if ($socket === false) {
        throw new SmtpException("Verbindung fehlgeschlagen: {$errstr} ({$errno})");
    }

    try {
        smtp_expect($socket, 220);

        $ehloHost = parse_url($smtpCfg['host'], PHP_URL_HOST) ?: $smtpCfg['host'];
        smtp_command($socket, "EHLO {$ehloHost}", 250);

        if (($smtpCfg['encryption'] ?? 'tls') === 'tls') {
            smtp_command($socket, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new SmtpException('STARTTLS-Handshake fehlgeschlagen');
            }
            smtp_command($socket, "EHLO {$ehloHost}", 250);
        }

        smtp_command($socket, 'AUTH LOGIN', 334);
        smtp_command($socket, base64_encode($smtpCfg['username']), 334);
        smtp_command($socket, base64_encode($smtpCfg['password']), 235);

        smtp_command($socket, "MAIL FROM:<{$from}>", 250);
        smtp_command($socket, "RCPT TO:<{$to}>", 250);
        smtp_command($socket, 'DATA', 354);
        smtp_command($socket, $data . "\r\n.", 250);
        smtp_command($socket, 'QUIT', 221);
    } finally {
        fclose($socket);
    }
}

/** @param resource $socket */
function smtp_command($socket, string $command, int $expectedCode): string
{
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $expectedCode);
}

/** @param resource $socket */
function smtp_expect($socket, int $expectedCode): string
{
    $response = '';
    do {
        $line = fgets($socket, 512);
        if ($line === false) {
            throw new SmtpException('Keine Antwort vom SMTP-Server erhalten');
        }
        $response .= $line;
    } while (isset($line[3]) && $line[3] === '-');

    $code = (int) substr($response, 0, 3);
    if ($code !== $expectedCode) {
        throw new SmtpException("Erwartet {$expectedCode}, erhalten: " . trim($response));
    }

    return $response;
}
