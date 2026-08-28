<?php
declare(strict_types=1);

require_once __DIR__ . '/questions.php';

// Baut aus den rohen Key/Value-Antworten einer Einreichung einen
// lesbaren Klartext-Block für den Claude-Prompt (Fragen-Label +
// verständlicher Antworttext statt interner Keys/Codes).
function format_answers_for_prompt(array $answersByKey): string
{
    $lines = [];

    foreach (get_question_catalog() as $q) {
        $key = $q['key'];
        if (!array_key_exists($key, $answersByKey)) {
            continue;
        }
        $value = $answersByKey[$key];

        switch ($q['type']) {
            case 'scale':
                $lines[] = "- {$q['label']} {$value}/5";
                break;

            case 'choice':
                $optionLabel = $q['options'][$value] ?? $value;
                $lines[] = "- {$q['label']} {$optionLabel}";
                break;

            case 'yesno_text':
                $answer = $value === 'ja' ? 'Ja' : 'Nein';
                $followUp = $answersByKey[$key . '_text'] ?? null;
                if ($followUp) {
                    $answer .= " – {$followUp}";
                }
                $lines[] = "- {$q['label']} {$answer}";
                break;

            case 'text':
                if (trim((string) $value) !== '') {
                    $lines[] = "- {$q['label']} {$value}";
                }
                break;
        }
    }

    return implode("\n", $lines);
}
