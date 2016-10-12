DROP TABLE IF EXISTS `age_groups`;

CREATE TABLE `age_groups` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `age_min` int(11) DEFAULT NULL,
  `age_max` int(11) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

LOCK TABLES `age_groups` WRITE;

INSERT INTO `age_groups` (`id`, `age_min`, `age_max`, `label`)
VALUES
	(1,18,25,'18-25'),
	(2,26,35,'26-35'),
	(3,36,45,'36-45'),
	(4,46,55,'36-55'),
	(5,56,65,'56-65'),
	(6,66,75,'66-75'),
	(7,76,85,'76-85'),
	(8,86,999,'85+');

UNLOCK TABLES;
