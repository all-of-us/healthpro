ALTER TABLE orders
ADD COLUMN `history_id` int DEFAULT NULL AFTER `version`;
