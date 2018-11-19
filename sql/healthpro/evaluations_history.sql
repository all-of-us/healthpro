CREATE TABLE `evaluations_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `site` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `reason` text NOT NULL,
  `created_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`evaluation_id`)
  REFERENCES evaluations(`id`)
) DEFAULT CHARSET=utf8mb4;
