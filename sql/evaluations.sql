CREATE TABLE `evaluations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `participant_id` int(11) NOT NULL,
  `created_ts` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_ts` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `version` varchar(10) NOT NULL,
  `data` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `participant_id` (`participant_id`)
) DEFAULT CHARSET=utf8mb4;
