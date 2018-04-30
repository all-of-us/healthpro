ALTER TABLE evaluations_queue
ADD COLUMN `attempted_ts` timestamp NULL DEFAULT NULL AFTER `sent_ts`;
