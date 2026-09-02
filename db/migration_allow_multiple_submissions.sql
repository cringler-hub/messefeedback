-- Einmalig in phpMyAdmin ausführen, um die bestehende Datenbank auf
-- "mehrfaches Feedback pro Tag erlaubt" umzustellen (schema.sql allein
-- reicht nicht, CREATE TABLE IF NOT EXISTS ändert keine bestehende
-- Tabelle).
--
-- uniq_employee_day kann nicht direkt gelöscht werden, da der
-- Fremdschlüssel fk_submission_employee diesen Index intern nutzt.
-- Deshalb zuerst einen einfachen Ersatz-Index anlegen.
ALTER TABLE feedback_submissions ADD INDEX idx_employee_id (employee_id);
ALTER TABLE feedback_submissions DROP INDEX uniq_employee_day;
