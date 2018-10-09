ALTER TABLE evaluations
ADD COLUMN `history_id` int DEFAULT NULL AFTER `data`;

ALTER TABLE evaluations ADD INDEX (`history_id`);
