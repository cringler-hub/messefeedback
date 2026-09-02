-- Einmalig in phpMyAdmin ausführen, um die bestehende Datenbank auf
-- "mehrfaches Feedback pro Tag erlaubt" umzustellen (schema.sql allein
-- reicht nicht, CREATE TABLE IF NOT EXISTS ändert keine bestehende
-- Tabelle).
ALTER TABLE feedback_submissions DROP INDEX uniq_employee_day;
