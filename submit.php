<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/questions.php';

load_config();

function redirect_with_error(string $error): void
{
    header('Location: index.php?error=' . urlencode($error));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Honeypot: befüllte Bot-Falle -> so tun als wäre alles ok, nichts speichern.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    header('Location: danke.php');
    exit;
}

$employeeId = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
if (!$employeeId) {
    redirect_with_error('invalid');
}

$catalog = get_question_catalog();
$answers = [];

foreach ($catalog as $q) {
    $key = $q['key'];
    $raw = $_POST[$key] ?? null;

    switch ($q['type']) {
        case 'scale':
            $value = filter_var($raw, FILTER_VALIDATE_INT);
            if ($value === false || $value < 1 || $value > 5) {
                redirect_with_error('invalid');
            }
            $answers[$key] = (string) $value;
            break;

        case 'choice':
            if (!is_string($raw) || !array_key_exists($raw, $q['options'])) {
                redirect_with_error('invalid');
            }
            $answers[$key] = $raw;
            break;

        case 'yesno_text':
            if (!in_array($raw, ['ja', 'nein'], true)) {
                redirect_with_error('invalid');
            }
            $answers[$key] = $raw;
            if ($raw === 'ja') {
                $text = trim((string) ($_POST[$key . '_text'] ?? ''));
                if ($text !== '') {
                    $answers[$key . '_text'] = mb_substr($text, 0, 500);
                }
            }
            break;

        case 'text':
            $text = trim((string) ($raw ?? ''));
            if ($text !== '') {
                $answers[$key] = mb_substr($text, 0, 2000);
            }
            break;
    }
}

$pdo = get_db();

$employeeExists = $pdo->prepare('SELECT 1 FROM employees WHERE id = ? AND active = 1');
$employeeExists->execute([$employeeId]);
if (!$employeeExists->fetchColumn()) {
    redirect_with_error('invalid');
}

$pdo->beginTransaction();
try {
    $insertSubmission = $pdo->prepare(
        'INSERT INTO feedback_submissions (employee_id, feedback_date) VALUES (?, ?)'
    );
    $insertSubmission->execute([$employeeId, date('Y-m-d')]);
    $submissionId = (int) $pdo->lastInsertId();

    $insertAnswer = $pdo->prepare(
        'INSERT INTO feedback_answers (submission_id, question_key, answer_value) VALUES (?, ?, ?)'
    );
    foreach ($answers as $key => $value) {
        $insertAnswer->execute([$submissionId, $key, $value]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Messefeedback submit.php: ' . $e->getMessage());
    http_response_code(500);
    die('Es ist ein Fehler aufgetreten. Bitte versuche es später erneut.');
}

header('Location: danke.php');
exit;
