ALTER TABLE sites
ADD COLUMN `workqueue_download` varchar(50) NOT NULL AFTER `email`;
