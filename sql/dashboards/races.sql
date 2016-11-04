DROP TABLE IF EXISTS `races`;

CREATE TABLE `races` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

LOCK TABLES `races` WRITE;

INSERT INTO `races` (`id`, `label`)
VALUES
	(1,'American Indian or Alaskan Native'),
	(2,'Asian'),
	(3,'Black or African American'),
	(4,'Native Hawaiian or Pacific Islander'),
	(5,'White'),
	(6,'Other'),
	(7,'Prefer not to answer');

UNLOCK TABLES;
