DROP TABLE IF EXISTS `lifecycle_phases`;

CREATE TABLE `lifecycle_phases` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

LOCK TABLES `lifecycle_phases` WRITE;

INSERT INTO `lifecycle_phases` (`id`, `label`)
VALUES
	(1,'Interested Party'),
	(2,'Consented'),
	(3,'Consented - PPI Completed'),
	(4,'Physical Exam Scheduled'),
	(5,'Physical Exam Completed'),
	(6,'Biosample Received'),
	(7,'Biosample Recorded'),
	(8,'Full Participant');

UNLOCK TABLES;