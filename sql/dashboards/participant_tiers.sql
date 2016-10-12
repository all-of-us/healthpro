DROP TABLE IF EXISTS `participant_tiers`;

CREATE TABLE `participant_tiers` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

LOCK TABLES `participant_tiers` WRITE;

INSERT INTO `participant_tiers` (`id`, `label`)
VALUES
	(1,'Interested Party'),
	(2,'Consented'),
	(3,'Engaged');

UNLOCK TABLES;
