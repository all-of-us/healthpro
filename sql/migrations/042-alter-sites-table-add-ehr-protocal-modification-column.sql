ALTER TABLE sites
ADD COLUMN `ehr_modification_protocol` tinyint(1) NOT NULL DEFAULT 0 AFTER `workqueue_download`;

