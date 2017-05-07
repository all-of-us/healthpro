DROP TABLE IF EXISTS `census_regions`;

CREATE TABLE `census_regions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) DEFAULT NULL,
  `recruitment_target` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

LOCK TABLES `census_regions` WRITE;

INSERT INTO `census_regions` (`id`, `label`, `recruitment_target`)
VALUES
	(1,'South',381854),
	(2,'West',239818),
	(3,'Midwest',223091),
	(4,'Northeast',184391);

UNLOCK TABLES;