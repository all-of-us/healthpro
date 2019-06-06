ALTER TABLE patient_status_history
ADD COLUMN `rdr_ts` timestamp NULL DEFAULT NULL AFTER `created_ts`;
