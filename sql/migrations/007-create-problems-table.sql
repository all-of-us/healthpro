CREATE TABLE `problems` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `participant_id` varchar(50) NOT NULL,
  `report_type` ENUM('physical', 'suicidal', 'verbal', 'misconduct'),
  `physical_injury_type` ENUM('baseline_related', 'baseline_unrelated'),
  `investigator_name` varchar(255) DEFAULT NULL,
  `problem_date` timestamp NULL DEFAULT NULL,
  `provider_aware_date` timestamp NULL DEFAULT NULL,
  `description` text DEFAULT NULL,
  `action_taken` varchar(512) DEFAULT NULL,
  `follow_up` varchar(512) DEFAULT NULL,
  `created_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4;
