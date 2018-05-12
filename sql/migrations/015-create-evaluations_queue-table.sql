CREATE TABLE `evaluations_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluation_id` int(11) NOT NULL,
  `evaluation_parent_id` int(11) NULL DEFAULT NULL,
  `old_rdr_id` varchar(50) NOT NULL,
  `new_rdr_id` varchar(50) NULL DEFAULT NULL,
  `fhir_version` int(11) NULL DEFAULT NULL,
  `queued_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_ts` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4;
