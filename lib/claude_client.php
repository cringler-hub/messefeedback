<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

class ClaudeApiException extends RuntimeException
{
}

/**
 * Ruft die Claude API mit einem System- und User-Prompt auf und
 * erwartet eine JSON-Antwort mit den angegebenen Pflichtfeldern.
 *
 * @param string[] $requiredKeys
 * @return array<string, string>
 */
function call_claude_json(string $system, string $userMessage, array $requiredKeys, int $maxTokens = 600): array
{
    $cfg = load_config()['claude'];

    $payload = json_encode([
        'model' => $cfg['model'],
        'max_tokens' => $maxTokens,
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
        CURLOPT_TIMEOUT => 45,
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
    if (!is_array($result) || !claude_result_has_keys($result, $requiredKeys)) {
        // Fallback: JSON-Objekt aus der Antwort herausschneiden, falls
        // Claude zusätzlichen Text drumherum geschrieben hat.
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $result = json_decode($matches[0], true);
        }
    }

    if (!is_array($result) || !claude_result_has_keys($result, $requiredKeys)) {
        throw new ClaudeApiException('Konnte Antwort der Claude API nicht als JSON parsen: ' . $text);
    }

    $output = [];
    foreach ($requiredKeys as $key) {
        $output[$key] = (string) $result[$key];
    }

    return $output;
}

/** @param string[] $requiredKeys */
function claude_result_has_keys(array $result, array $requiredKeys): bool
{
    foreach ($requiredKeys as $key) {
        if (empty($result[$key])) {
            return false;
        }
    }

    return true;
}

/**
 * Erzeugt aus dem gesammelten Tagesfeedback aller Mitarbeiter ein
 * gemeinsames Team-Debriefing plus Motivationsspruch und konkreter
 * Handlungsempfehlung für den heutigen Messetag. Alle Mitarbeiter
 * erhalten denselben Text.
 *
 * @return array{summary: string, action: string, quote: string}
 */
function generate_team_debriefing(string $combinedFeedbackText, int $employeeCount): array
{
    $system = <<<SYS
Du bist Assistent für ein tägliches Team-Debriefing auf einer Messe
(Innotrans). Du bekommst das strukturierte Tagesfeedback mehrerer
Mitarbeiter/innen vom GESTRIGEN Messetag, jeweils mit Namen. Diese
Zusammenfassung wird dem Team HEUTE FRÜH per Mail zugestellt, bevor
der heutige Messetag beginnt. Erstelle daraus EIN gemeinsames
Debriefing für das ganze Team (nicht pro Person einzeln):

1. "summary": Eine kurze, persönliche Team-Zusammenfassung des
   gestrigen Tages (4-6 Sätze, per Ihr/Euch, warm, motivierend und
   zielgerichtet statt nur nüchtern-professionell). Verdichte die
   wichtigsten Punkte über alle Rückmeldungen hinweg (Gesamtstimmung,
   Highlights, gemeldete Probleme, Konkurrenzbeobachtungen). Bei
   Bedarf einzelne Personen namentlich und wertschätzend erwähnen.
   Keine Floskeln, keine wörtliche Wiederholung aller Antworten,
   sondern eine echte, warme Verdichtung fürs ganze Team.
2. "action": Eine konkrete, umsetzbare Handlungsempfehlung zur
   NACHBEARBEITUNG DER LEADS/KONTAKTE (1-2 Sätze), abgeleitet aus dem
   gestrigen Feedback. Benenne wenn möglich konkret genannte
   vielversprechende Kontakte/Highlights aus dem Feedback und wie sie
   priorisiert nachverfolgt werden sollten (z. B. zeitnah anrufen,
   Unterlagen zusenden, internen Ansprechpartner informieren). Falls
   im Feedback keine konkreten Kontakte genannt wurden, eine sinnvolle
   generische Empfehlung zur strukturierten Lead-Nachbearbeitung geben
   (z. B. gesammelte Visitenkarten/Kontakte zeitnah systematisch
   erfassen und Prioritäten setzen). Konkret und direkt umsetzbar,
   keine allgemeinen Tipps zum Messetag selbst.
3. "quote": Ein motivierender, persönlicher Spruch für HEUTE (1-2
   Sätze) fürs ganze Team, der zur berichteten Gesamtstimmung passt
   (z. B. aufmunternd nach einem schwierigen Tag, bestärkend nach
   einem guten Tag). WICHTIG: Beziehe dich auf "heute", NIEMALS auf
   "morgen" - die Leser lesen dies am Morgen des Tages, für den der
   Spruch gilt. Keine abgedroschenen Standardsprüche, möglichst
   variieren.

Antworte AUSSCHLIESSLICH mit einem JSON-Objekt exakt in dieser Form,
ohne weiteren Text davor oder danach:
{"summary": "...", "action": "...", "quote": "..."}
SYS;

    $userMessage = "Feedback von {$employeeCount} Mitarbeiter(n) für den gestrigen Messetag:\n\n{$combinedFeedbackText}";

    return call_claude_json($system, $userMessage, ['summary', 'action', 'quote'], 600);
}

/**
 * Erzeugt zusätzlich zur Tageszusammenfassung eine Gesamtrückschau
 * über die komplette Messe (alle Tage) und einen persönlichen Dank
 * ans Team - für den Morgen nach dem letzten Messetag.
 *
 * @return array{event_summary: string, thanks: string}
 */
function generate_event_closing(string $allEventFeedbackText, int $employeeCount, int $eventDayCount): array
{
    $system = <<<SYS
Du bist Assistent für den ABSCHLUSS eines mehrtägigen Messeauftritts
(Innotrans). Heute ist der Morgen NACH dem letzten Messetag, die Messe
ist vorbei. Du bekommst das gesammelte Feedback über die GESAMTE Messe
(alle Tage). Erstelle daraus:

1. "event_summary": Eine warme, würdigende Gesamtrückschau auf die
   komplette Messe (5-8 Sätze) - roter Faden über die Tage hinweg,
   größte Erfolge und Highlights, wie das Team mit Herausforderungen
   umgegangen ist, bemerkenswerte Kontakte oder Beobachtungen. Soll
   sich wie ein würdiger, persönlicher Rückblick lesen, nicht wie eine
   trockene Auflistung. Keine wörtliche Wiederholung aller Antworten.
2. "thanks": Ein herzlicher, persönlicher Dank an das gesamte Team
   (3-5 Sätze, per Ihr/Euch) für den Einsatz, die Leistung und den
   Zusammenhalt während der gesamten Messe. Warm und aufrichtig,
   konkret statt floskelhaft - gerne mit Bezug auf das, was aus dem
   Feedback an Teamgeist erkennbar wird.

Antworte AUSSCHLIESSLICH mit einem JSON-Objekt exakt in dieser Form,
ohne weiteren Text davor oder danach:
{"event_summary": "...", "thanks": "..."}
SYS;

    $userMessage = "Team: {$employeeCount} Mitarbeiter(innen), Messe über {$eventDayCount} Tag(e).\n\n"
        . "Feedback über die gesamte Messe (alle Tage):\n"
        . ($allEventFeedbackText !== '' ? $allEventFeedbackText : '(kein Feedback über die gesamte Messe vorhanden)');

    return call_claude_json($system, $userMessage, ['event_summary', 'thanks'], 800);
}
