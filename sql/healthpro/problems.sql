CREATE TABLE `problems` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `site` varchar(50) NOT NULL,
  `participant_id` varchar(50) NOT NULL,
  `problem_type` varchar(50) NOT NULL,
  `enrollment_site` varchar(255) NOT NULL,
  `staff_name` varchar(255) DEFAULT NULL,
  `problem_date` timestamp NULL DEFAULT NULL,
  `provider_aware_date` timestamp NULL DEFAULT NULL,
  `description` text DEFAULT NULL,
  `action_taken` varchar(255) DEFAULT NULL,
  `finalized_user_id` int(11) DEFAULT NULL,
  `finalized_site` varchar(50) DEFAULT NULL,
  `finalized_ts` TIMESTAMP NULL DEFAULT NULL,
  `created_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4;
