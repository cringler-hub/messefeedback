-- Mitarbeiterliste. Vor dem Import beliebig um weitere Zeilen
-- ergänzen (name, email) – jederzeit auch später über
-- INSERT/UPDATE/DELETE erweiterbar, ohne das Schema zu ändern.

INSERT INTO employees (name, email) VALUES
    ('Max Mustermann', 'max.mustermann@example.com')
ON DUPLICATE KEY UPDATE name = VALUES(name);
