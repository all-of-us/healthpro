ALTER TABLE orders 
ADD COLUMN `collected_user_id` int(11) DEFAULT NULL AFTER `printed_ts`, 
ADD COLUMN `collected_site` varchar(50) DEFAULT NULL AFTER `collected_user_id`,
ADD COLUMN `processed_user_id` int(11) DEFAULT NULL AFTER `collected_notes`, 
ADD COLUMN `processed_site` varchar(50) DEFAULT NULL AFTER `processed_user_id`;
