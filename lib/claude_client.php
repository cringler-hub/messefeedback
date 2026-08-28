<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

class ClaudeApiException extends RuntimeException
{
}

/**
 * Erzeugt aus dem Tagesfeedback eines Mitarbeiters ein kurzes
 * Debriefing plus einen dazu passenden Motivationsspruch für den
 * nächsten Tag.
 *
 * @return array{summary: string, quote: string}
 */
function generate_debriefing(string $employeeName, string $feedbackText): array
{
    $cfg = load_config()['claude'];

    $system = <<<SYS
Du bist Assistent für ein tägliches Mitarbeiter-Debriefing auf einer
Messe (Innotrans). Du bekommst das strukturierte Tagesfeedback eines
Mitarbeiters/einer Mitarbeiterin. Erstelle daraus:

1. "summary": Ein kurzes, persönliches Debriefing (3-5 Sätze, per Du,
   warmer aber professioneller Ton). Geh auf die wichtigsten Punkte
   ein (Highlights, Probleme, Stimmung). Bei Problemen: wertschätzend,
   nicht dramatisierend. Keine Floskeln, keine Wiederholung aller
   Antworten wörtlich, sondern eine echte Verdichtung.
2. "quote": Ein motivierender Spruch für den kommenden Tag (1-2
   Sätze), der zur berichteten Stimmung/Situation passt (z. B.
   aufmunternd nach einem schwierigen Tag, bestärkend nach einem
   guten Tag). Keine abgedroschenen Standardsprüche, möglichst
   variieren.

Antworte AUSSCHLIESSLICH mit einem JSON-Objekt exakt in dieser Form,
ohne weiteren Text davor oder danach:
{"summary": "...", "quote": "..."}
SYS;

    $userMessage = "Mitarbeiter/in: {$employeeName}\n\nTagesfeedback:\n{$feedbackText}";

    $payload = json_encode([
        'model' => $cfg['model'],
        'max_tokens' => 500,
        'system' => $system,
        'messages' => [
            ['role' => 'user', 'content' => $userMessage],
        ],
    ], JSON_THROW_ON_ERROR);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'content-type: application/json',
            'x-api-key: ' . $cfg['api_key'],
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new ClaudeApiException("cURL-Fehler: {$curlError}");
    }
    if ($httpCode !== 200) {
        throw new ClaudeApiException("Claude API HTTP {$httpCode}: {$response}");
    }

    $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    $text = $decoded['content'][0]['text'] ?? '';

    $result = json_decode(trim($text), true);
    if (!is_array($result) || empty($result['summary']) || empty($result['quote'])) {
        // Fallback: JSON-Objekt aus der Antwort herausschneiden, falls
        // Claude zusätzlichen Text drumherum geschrieben hat.
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $result = json_decode($matches[0], true);
        }
    }

    if (!is_array($result) || empty($result['summary']) || empty($result['quote'])) {
        throw new ClaudeApiException('Konnte Antwort der Claude API nicht als JSON parsen: ' . $text);
    }

    return [
        'summary' => (string) $result['summary'],
        'quote' => (string) $result['quote'],
    ];
}
