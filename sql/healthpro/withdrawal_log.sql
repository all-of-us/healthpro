CREATE TABLE `withdrawal_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `participant_id` varchar(50) NOT NULL,
  `insert_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `withdrawal_ts` timestamp NULL DEFAULT NULL,
  `hpo_id` varchar(100) DEFAULT NULL,
  `email_notified` varchar(2000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `participant_id` (`participant_id`),
  KEY `hpo_id` (`hpo_id`)
) DEFAULT CHARSET=utf8mb4;
