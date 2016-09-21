CREATE TABLE `evaluations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `participant_id` varchar(50) NOT NULL,
  `created_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `version` varchar(10) NOT NULL,
  `data` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `participant_id` (`participant_id`)
) DEFAULT CHARSET=utf8mb4;
