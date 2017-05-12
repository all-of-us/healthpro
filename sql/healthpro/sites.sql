CREATE TABLE `sites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `google_group` varchar(255) NOT NULL,
  `mayolink_account` varchar(255) DEFAULT NULL,
  `timezone` varchar(100) DEFAULT NULL,
  `organization` varchar(100) DEFAULT NULL,
  `email` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4;
