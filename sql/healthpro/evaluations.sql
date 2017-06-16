CREATE TABLE `evaluations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `site` varchar(50) NOT NULL,
  `participant_id` varchar(50) NOT NULL,
  `rdr_id` VARCHAR(50) NULL DEFAULT NULL,
  `parent_id` int(11) NULL DEFAULT NULL,
  `created_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finalized_user_id` int(11) DEFAULT NULL,
  `finalized_site` varchar(50) DEFAULT NULL,
  `finalized_ts` TIMESTAMP NULL DEFAULT NULL,
  `version` varchar(10) NOT NULL,
  `data` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `participant_id` (`participant_id`)
) DEFAULT CHARSET=utf8mb4;
