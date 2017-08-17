CREATE TABLE `problem_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `problem_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `site` varchar(50) NOT NULL,
  `staff_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`problem_id`)
  REFERENCES problems(`id`)
) DEFAULT CHARSET=utf8mb4;