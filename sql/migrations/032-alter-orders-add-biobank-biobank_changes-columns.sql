ALTER TABLE orders
ADD COLUMN `biobank_finalized` tinyint(1) NOT NULL DEFAULT 0 AFTER `history_id`,
ADD COLUMN `biobank_changes` text DEFAULT NULL AFTER `biobank_finalized`;
