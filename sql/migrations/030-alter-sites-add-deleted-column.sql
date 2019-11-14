ALTER TABLE sites
ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT 0 AFTER `workqueue_download`;
