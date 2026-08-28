<?php
declare(strict_types=1);

// Zentraler Fragenkatalog. Wird vom Formular (Rendering), von
// submit.php (Validierung) und vom Debriefing-Cronjob (Klartext für
// die KI-Zusammenfassung) genutzt. Neue Fragen einfach ergänzen –
// die Datenbank speichert Antworten als Key/Value-Zeilen und muss
// dafür nicht geändert werden.
//
// type: 'scale' (1-5), 'choice' (feste Optionen), 'yesno_text'
// (Ja/Nein mit optionalem Freitext-Feld <key>_text), 'text' (Freitext).

function get_question_catalog(): array
{
    return [
        [
            'key'   => 'q1_day_overall',
            'type'  => 'scale',
            'label' => 'Wie war dein Tag insgesamt?',
            'scale_labels' => [1 => 'sehr schlecht', 3 => 'okay', 5 => 'sehr gut'],
        ],
        [
            'key'   => 'q2_visitor_traffic',
            'type'  => 'choice',
            'label' => 'Wie war der Besucherandrang am Stand?',
            'options' => [
                'zu_wenig' => 'Zu wenig',
                'passend'  => 'Genau richtig',
                'zu_viel'  => 'Zu viel',
            ],
        ],
        [
            'key'   => 'q3_conversations',
            'type'  => 'scale',
            'label' => 'Wie liefen die Gespräche mit Besuchern/Kunden?',
            'scale_labels' => [1 => 'sehr schlecht', 3 => 'okay', 5 => 'sehr gut'],
        ],
        [
            'key'   => 'q4_highlight',
            'type'  => 'yesno_text',
            'label' => 'Gab es einen Highlight-Moment oder vielversprechenden Kontakt heute?',
            'text_placeholder' => 'Was war das Highlight?',
        ],
        [
            'key'   => 'q5_problem',
            'type'  => 'yesno_text',
            'label' => 'Gab es ein Problem oder eine Störung (Technik, Standbau, Logistik, Personal)?',
            'text_placeholder' => 'Was war das Problem?',
        ],
        [
            'key'   => 'q6_team_mood',
            'type'  => 'scale',
            'label' => 'Wie war die Teamstimmung / Zusammenarbeit heute?',
            'scale_labels' => [1 => 'sehr schlecht', 3 => 'okay', 5 => 'sehr gut'],
        ],
        [
            'key'   => 'q7_competitor',
            'type'  => 'yesno_text',
            'label' => 'Etwas Interessantes bei der Konkurrenz beobachtet?',
            'text_placeholder' => 'Was ist dir aufgefallen?',
        ],
        [
            'key'   => 'q8_comment',
            'type'  => 'text',
            'label' => 'Was möchtest du sonst noch mitteilen?',
            'optional' => true,
        ],
    ];
}
