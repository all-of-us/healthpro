CREATE TABLE `deceased_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `participant_id` varchar(50) NOT NULL,
  `insert_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deceased_ts` timestamp NULL DEFAULT NULL,
  `organization_id` varchar(100) DEFAULT NULL,
  `email_notified` varchar(2000) DEFAULT NULL,
  `deceased_status` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `deceased_log_unique` (`participant_id`, `organization_id`, `deceased_status`)
) DEFAULT CHARSET=utf8mb4;
