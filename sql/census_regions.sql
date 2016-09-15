
DROP TABLE IF EXISTS `census_regions`;

CREATE TABLE `census_regions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

LOCK TABLES `census_regions` WRITE;

INSERT INTO `census_regions` (`id`, `label`)
VALUES
	(1,'South'),
	(2,'West'),
	(3,'Midwest'),
	(4,'Northeast');

UNLOCK TABLES;
