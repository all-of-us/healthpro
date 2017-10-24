ALTER TABLE sites
ADD COLUMN `centrifuge_type` varchar(50) NULL DEFAULT NULL AFTER `email`;
