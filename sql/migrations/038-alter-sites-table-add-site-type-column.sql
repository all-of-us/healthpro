ALTER TABLE sites
ADD COLUMN `site_type` varchar(100) DEFAULT NULL AFTER `type`;
