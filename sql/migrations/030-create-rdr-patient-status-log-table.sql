CREATE TABLE `rdr_patient_status_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_status_id` int(11) NOT NULL,
  `created_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4;
