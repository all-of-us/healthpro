CREATE TABLE `patient_status_import` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) NOT NULL,
  `organization` varchar(50) NOT NULL,
  `awardee` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `site` varchar(50) NOT NULL,
  `created_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `import_status` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4;
