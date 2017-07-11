ALTER TABLE sites 
ADD COLUMN `type` varchar(100) DEFAULT NULL AFTER `organization`, 
ADD COLUMN `awardee` varchar(100) DEFAULT NULL AFTER `type`;
