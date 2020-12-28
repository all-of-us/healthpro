CREATE TABLE `ehr_withdrawal_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `participant_id` varchar(50) NOT NULL,
  `insert_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ehr_withdrawal_ts` timestamp NULL DEFAULT NULL,
  `awardee_id` varchar(100) DEFAULT NULL,
  `email_notified` varchar(2000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`participant_id`, `ehr_withdrawal_ts`),
  KEY `awardee_id` (`awardee_id`)
) DEFAULT CHARSET=utf8mb4;
