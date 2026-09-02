-- Messefeedback / Innotrans Debriefing – Datenbankschema
-- Zeichensatz utf8mb4 für Emojis/Umlaute in Freitextfeldern.

CREATE TABLE IF NOT EXISTS employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Eine Zeile pro Einreichung. Mitarbeiter können am selben Tag
-- beliebig oft Feedback abgeben (kein Unique-Key auf employee+Tag) -
-- das Debriefing fasst am nächsten Morgen automatisch alle
-- Einreichungen eines Tages zusammen.
CREATE TABLE IF NOT EXISTS feedback_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    feedback_date DATE NOT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee_id (employee_id),
    CONSTRAINT fk_submission_employee FOREIGN KEY (employee_id)
        REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Antworten als Key/Value-Zeilen statt starrer Spalten, damit der
-- Fragenkatalog später erweitert/geändert werden kann, ohne das
-- Tabellenschema anzufassen. question_key entspricht den Keys aus
-- lib/questions.php.
CREATE TABLE IF NOT EXISTS feedback_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNSIGNED NOT NULL,
    question_key VARCHAR(60) NOT NULL,
    answer_value TEXT NOT NULL,
    CONSTRAINT fk_answer_submission FOREIGN KEY (submission_id)
        REFERENCES feedback_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Eine KI-Debriefing-Mail pro Mitarbeiter und Tag (idempotenz für den
-- 06:30-Cronjob, falls dieser mehrfach ausgelöst wird).
CREATE TABLE IF NOT EXISTS debriefings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    debriefing_date DATE NOT NULL,
    summary_text TEXT NOT NULL,
    motivation_quote TEXT NOT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    UNIQUE KEY uniq_employee_debrief_day (employee_id, debriefing_date),
    CONSTRAINT fk_debriefing_employee FOREIGN KEY (employee_id)
        REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Protokoll der 18:00-Erinnerungsmails (idempotenz, falls der
-- Cronjob mehrfach ausgelöst wird).
CREATE TABLE IF NOT EXISTS reminder_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    reminder_date DATE NOT NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_employee_reminder_day (employee_id, reminder_date),
    CONSTRAINT fk_reminder_employee FOREIGN KEY (employee_id)
        REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
