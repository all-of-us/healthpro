CREATE TABLE `patient_status_import_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `participant_id` varchar(50) NOT NULL,
  `status` varchar(50) NOT NULL,
  `comments` text DEFAULT NULL,
  `import_id` int(11) NOT NULL,
  `import_status` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`import_id`)
  REFERENCES patient_status_import(`id`)
) DEFAULT CHARSET=utf8mb4;
