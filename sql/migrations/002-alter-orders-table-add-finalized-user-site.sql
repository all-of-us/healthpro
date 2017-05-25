ALTER TABLE orders 
ADD COLUMN `finalized_user_id` int(11) DEFAULT NULL AFTER `processed_notes`, 
ADD COLUMN `finalized_site` varchar(50) DEFAULT NULL AFTER `finalized_user_id`;
