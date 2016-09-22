DROP TABLE IF EXISTS `ethnicities`;

CREATE TABLE `ethnicities` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

LOCK TABLES `ethnicities` WRITE;

INSERT INTO `ethnicities` (`id`, `label`)
VALUES
	(1,'Hispanic, Latino or Spanish Origin'),
	(2,'Not of Hispanic, Latino or Spanish Origin'),
	(3,'Prefer not to answer');

UNLOCK TABLES;
