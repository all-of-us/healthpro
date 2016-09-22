DROP TABLE IF EXISTS `gender_identities`;

CREATE TABLE `gender_identities` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

LOCK TABLES `gender_identities` WRITE;

INSERT INTO `gender_identities` (`id`, `label`)
VALUES
	(1,'Female'),
	(2,'Male'),
	(3,'Female to Male Transgender'),
	(4,'Male to Female Transgender'),
	(5,'Intersex'),
	(6,'Other'),
	(7,'Prefer not to Answer');

UNLOCK TABLES;
