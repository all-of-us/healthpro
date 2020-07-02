ALTER TABLE patient_status_history
ADD COLUMN `import_id` int(11) DEFAULT NULL AFTER `rdr_ts`,
ADD CONSTRAINT FOREIGN KEY (`import_id`) REFERENCES patient_status_import(`id`);
