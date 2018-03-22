ALTER TABLE sites
ADD COLUMN `status` tinyint(1) NOT NULL DEFAULT 1 AFTER `id`,
ADD COLUMN `site_id` varchar(255) NULL DEFAULT NULL AFTER `name`,
ADD COLUMN `organization_id` varchar(255) NULL DEFAULT NULL AFTER `site_id`,
ADD COLUMN `awardee_id` varchar(255) NULL DEFAULT NULL AFTER `organization_id`;
