ALTER TABLE evaluations
ADD COLUMN `history_id` int DEFAULT NULL AFTER `data`;
