<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

class ClaudeApiException extends RuntimeException
{
}

/**
 * Erzeugt aus dem gesammelten Tagesfeedback aller Mitarbeiter ein
 * gemeinsames Team-Debriefing plus einen Motivationsspruch für den
 * nächsten Tag. Alle Mitarbeiter erhalten denselben Text.
 *
 * @return array{summary: string, quote: string}
 */
function generate_team_debriefing(string $combinedFeedbackText, int $employeeCount): array
{
    $cfg = load_config()['claude'];

    $system = <<<SYS
Du bist Assistent für ein tägliches Team-Debriefing auf einer Messe
(Innotrans). Du bekommst das strukturierte Tagesfeedback mehrerer
Mitarbeiter/innen desselben Messetags, jeweils mit Namen. Erstelle
daraus EIN gemeinsames Debriefing für das ganze Team (nicht pro
Person einzeln):

1. "summary": Eine kurze Team-Zusammenfassung des Tages (4-6 Sätze,
   per Ihr/Euch, warmer aber professioneller Ton). Verdichte die
   wichtigsten Punkte über alle Rückmeldungen hinweg (Gesamtstimmung,
   Highlights, gemeldete Probleme, Konkurrenzbeobachtungen). Bei
   Bedarf einzelne Personen namentlich erwähnen, wenn es zum
   Verständnis beiträgt. Keine Floskeln, keine wörtliche Wiederholung
   aller Antworten, sondern eine echte Verdichtung fürs ganze Team.
2. "quote": Ein motivierender Spruch für den kommenden Tag (1-2
   Sätze) fürs ganze Team, der zur berichteten Gesamtstimmung passt.
   Keine abgedroschenen Standardsprüche, möglichst variieren.

Antworte AUSSCHLIESSLICH mit einem JSON-Objekt exakt in dieser Form,
ohne weiteren Text davor oder danach:
{"summary": "...", "quote": "..."}
SYS;

    $userMessage = "Feedback von {$employeeCount} Mitarbeiter(n) für denselben Messetag:\n\n{$combinedFeedbackText}";

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
