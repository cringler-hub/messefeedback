-- Mitarbeiterliste. Vor dem Import beliebig um weitere Zeilen
-- ergänzen (name, email) – jederzeit auch später über
-- INSERT/UPDATE/DELETE erweiterbar, ohne das Schema zu ändern.

INSERT INTO employees (name, email) VALUES
    ('Christian Ringler', 'christian.ringler@fms.funkwerk.com')
ON DUPLICATE KEY UPDATE name = VALUES(name);
