<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/questions.php';

load_config();

$employees = get_db()
    ->query('SELECT id, name FROM employees WHERE active = 1 ORDER BY name')
    ->fetchAll();

$questions = get_question_catalog();
$today = date('d.m.Y');
$error = isset($_GET['error']) ? (string) $_GET['error'] : null;
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Messefeedback Innotrans</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="hero">
    <p class="hero-watermark" aria-hidden="true">FEEDBACK FEEDBACK</p>
    <div class="hero-inner">
        <div class="brand">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 12a8 8 0 0 1 8-8" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>
                <path d="M8 12a4 4 0 0 1 4-4" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>
                <circle cx="12" cy="12" r="1.6" fill="#fff"/>
            </svg>
            <span>funkwerk mobility solutions</span>
        </div>
        <h1>Tagesfeedback Innotrans</h1>
        <p class="meta"><?= htmlspecialchars($today, ENT_QUOTES) ?> · dauert ca. 1 Minute</p>
    </div>
</header>
<div class="container">
    <?php if ($error === 'invalid'): ?>
        <div class="error-box">Bitte alle Pflichtfragen beantworten und nochmal absenden.</div>
    <?php endif; ?>

    <form class="card" method="post" action="submit.php" novalidate>
        <div class="field">
            <label class="question" for="employee_id">Wer bist du?</label>
            <select name="employee_id" id="employee_id" required>
                <option value="" disabled selected>Bitte auswählen …</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?= (int) $emp['id'] ?>"><?= htmlspecialchars($emp['name'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php foreach ($questions as $q): ?>
            <div class="field">
                <label class="question"><?= htmlspecialchars($q['label'], ENT_QUOTES) ?></label>

                <?php if ($q['type'] === 'scale'): ?>
                    <div class="option-row scale">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label class="option-btn">
                                <input type="radio" name="<?= $q['key'] ?>" value="<?= $i ?>" required>
                                <span><?= $i ?></span>
                            </label>
                        <?php endfor; ?>
                    </div>
                    <?php if (!empty($q['scale_labels'])): ?>
                        <div class="scale-hint">
                            <span><?= htmlspecialchars($q['scale_labels'][1] ?? '', ENT_QUOTES) ?></span>
                            <span><?= htmlspecialchars($q['scale_labels'][5] ?? '', ENT_QUOTES) ?></span>
                        </div>
                    <?php endif; ?>

                <?php elseif ($q['type'] === 'choice'): ?>
                    <div class="option-row">
                        <?php foreach ($q['options'] as $value => $optLabel): ?>
                            <label class="option-btn">
                                <input type="radio" name="<?= $q['key'] ?>" value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" required>
                                <span><?= htmlspecialchars($optLabel, ENT_QUOTES) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($q['type'] === 'yesno_text'): ?>
                    <div class="option-row">
                        <label class="option-btn">
                            <input type="radio" name="<?= $q['key'] ?>" value="ja" class="yesno-toggle" data-target="<?= $q['key'] ?>_wrap" required>
                            <span>Ja</span>
                        </label>
                        <label class="option-btn">
                            <input type="radio" name="<?= $q['key'] ?>" value="nein" class="yesno-toggle" data-target="<?= $q['key'] ?>_wrap" required>
                            <span>Nein</span>
                        </label>
                    </div>
                    <div class="follow-up" id="<?= $q['key'] ?>_wrap">
                        <input type="text" name="<?= $q['key'] ?>_text" maxlength="500" placeholder="<?= htmlspecialchars($q['text_placeholder'] ?? '', ENT_QUOTES) ?>">
                    </div>

                <?php elseif ($q['type'] === 'text'): ?>
                    <textarea name="<?= $q['key'] ?>" maxlength="2000" placeholder="Optional …"></textarea>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <!-- Honeypot gegen simple Bots, für Menschen unsichtbar -->
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
            <input type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit" class="submit-btn">Absenden</button>
    </form>
</div>

<script>
document.querySelectorAll('.yesno-toggle').forEach(function (radio) {
    radio.addEventListener('change', function () {
        var wrap = document.getElementById(this.dataset.target);
        if (!wrap) return;
        if (this.value === 'ja') {
            wrap.classList.add('visible');
        } else {
            wrap.classList.remove('visible');
            wrap.querySelector('input').value = '';
        }
    });
});
</script>
</body>
</html>
