CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `participant_id` int(11) NOT NULL,
  `created_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `mayo_id` varchar(100) NOT NULL,
  `printed_ts` timestamp NULL DEFAULT NULL,
  `collected_ts` timestamp NULL DEFAULT NULL,
  `collected_samples` varchar(255) DEFAULT NULL,
  `collected_notes` text,
  `processed_ts` timestamp NULL DEFAULT NULL,
  `processed_samples` varchar(255) DEFAULT NULL,
  `processed_notes` text,
  `finalized_ts` timestamp NULL DEFAULT NULL,
  `finalized_samples` varchar(255) DEFAULT NULL,
  `finalized_notes` text,
  PRIMARY KEY (`id`),
  KEY `participant_id` (`participant_id`),
  KEY `mayo_id` (`mayo_id`)
) DEFAULT CHARSET=utf8mb4;
