ALTER TABLE orders
ADD COLUMN `version` varchar(10) NULL DEFAULT NULL AFTER `type`;
