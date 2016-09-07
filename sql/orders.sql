CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `participant_id` int(11) NOT NULL,
  `created_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `mayo_id` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `participant_id` (`participant_id`),
  KEY `mayo_id` (`mayo_id`)
) DEFAULT CHARSET=utf8mb4;
