ALTER TABLE sites
ADD COLUMN `centrifuge_type` varchar(50) NOT NULL AFTER `email`;
