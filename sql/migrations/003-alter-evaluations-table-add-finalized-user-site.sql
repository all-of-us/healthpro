ALTER TABLE evaluations 
ADD COLUMN `finalized_user_id` int(11) DEFAULT NULL AFTER `updated_ts`, 
ADD COLUMN `finalized_site` varchar(50) DEFAULT NULL AFTER `finalized_user_id`;