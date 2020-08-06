CREATE TABLE `patient_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `participant_id` varchar(50) NOT NULL,
  `organization` varchar(50) NOT NULL,
  `awardee` varchar(50) NOT NULL,
  `history_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `history_id` (`history_id`)
) DEFAULT CHARSET=utf8mb4;
