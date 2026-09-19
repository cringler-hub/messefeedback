-- Einmalig in phpMyAdmin ausführen: Debriefing-Mails enthalten jetzt
-- zusätzlich eine konkrete Handlungsempfehlung für den Tag, dafür
-- braucht die bestehende Tabelle eine neue Spalte.
ALTER TABLE debriefings ADD COLUMN action_text TEXT NULL AFTER summary_text;
