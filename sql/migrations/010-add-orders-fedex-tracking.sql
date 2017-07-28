ALTER TABLE orders
ADD COLUMN `fedex_tracking` varchar(50) NULL DEFAULT NULL AFTER `finalized_notes`;
