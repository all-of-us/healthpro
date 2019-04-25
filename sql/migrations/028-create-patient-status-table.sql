CREATE TABLE `patient_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `participant_id` varchar(50) NOT NULL,
  `organization` varchar(50) NOT NULL,
  `awardee` varchar(50) NOT NULL,
  `history_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `history_id` (`history_id`)
) DEFAULT CHARSET=utf8mb4;


CREATE TABLE `patient_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_status_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `site` varchar(50) NOT NULL,
  `status` varchar(50) NOT NULL,
  `comments` text DEFAULT NULL,
  `created_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`patient_status_id`)
  REFERENCES patient_status(`id`)
) DEFAULT CHARSET=utf8mb4;

