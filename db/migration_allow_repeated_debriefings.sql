-- Einmalig in phpMyAdmin ausführen, damit das Team-Debriefing bei
-- jedem Aufruf erneut generiert und verschickt wird, auch mehrfach
-- am selben Tag (zu Testzwecken).
--
-- uniq_employee_debrief_day kann nicht direkt gelöscht werden, da
-- der Fremdschlüssel fk_debriefing_employee diesen Index intern
-- nutzt. Deshalb zuerst einen einfachen Ersatz-Index anlegen.
ALTER TABLE debriefings ADD INDEX idx_employee_id (employee_id);
ALTER TABLE debriefings DROP INDEX uniq_employee_debrief_day;
