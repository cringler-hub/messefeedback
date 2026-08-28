<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function send_mail(string $to, string $subject, string $body): bool
{
    $mailCfg = load_config()['mail'];
    $fromAddress = $mailCfg['from_address'];
    $fromName = mb_encode_mimeheader($mailCfg['from_name'], 'UTF-8');

    $headers = [
        "From: {$fromName} <{$fromAddress}>",
        "Reply-To: {$fromAddress}",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');

    return mail($to, $encodedSubject, $body, implode("\r\n", $headers));
}
