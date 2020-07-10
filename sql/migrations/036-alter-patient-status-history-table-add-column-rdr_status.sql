ALTER TABLE patient_status_history
ADD COLUMN `rdr_status` tinyint(1) NOT NULL DEFAULT 0 AFTER `import_id`;
