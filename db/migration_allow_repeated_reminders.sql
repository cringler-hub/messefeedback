-- Einmalig in phpMyAdmin ausführen, damit die Erinnerungsmail bei
-- jedem Aufruf erneut an alle ohne Feedback verschickt wird, auch
-- wenn am selben Tag schon einmal erinnert wurde.
--
-- uniq_employee_reminder_day kann nicht direkt gelöscht werden, da
-- der Fremdschlüssel fk_reminder_employee diesen Index intern nutzt.
-- Deshalb zuerst einen einfachen Ersatz-Index anlegen.
ALTER TABLE reminder_log ADD INDEX idx_employee_id (employee_id);
ALTER TABLE reminder_log DROP INDEX uniq_employee_reminder_day;
