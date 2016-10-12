DROP TABLE IF EXISTS `recruitment_centers`;

CREATE TABLE `recruitment_centers` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) DEFAULT NULL,
  `latitude` varchar(255) DEFAULT NULL,
  `longitude` varchar(255) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

LOCK TABLES `recruitment_centers` WRITE;

INSERT INTO `recruitment_centers` (`id`, `label`, `latitude`, `longitude`, `category`)
VALUES
	(1,'Direct Volunteer','37.0000','-70.0000','Misc'),
	(2,'Veterans Administration Medical Center','33.0000','-71.4000','Misc'),
	(3,'Arizona','33.7712','-111.3877','RMC'),
	(4,'Illinois','40.3363','-89.0022','RMC'),
	(5,'Pitt','40.4397','-79.9765','RMC'),
	(6,'New York','42.1497','-74.9384','RMC'),
	(7,'Cherokee Health Systems, Knoxville, Tennessee','35.9708','-83.9464','FQHC'),
	(8,'Community Health Center, Inc., Middletown, Connecticut','41.5495','-72.6577','FQHC'),
	(9,'Eau Claire Cooperative Health Center, Columbia, South Carolina','34.0297','-80.8965','FQHC'),
	(10,'HRHCare, Peekskill, New York','41.2873','-73.9235','FQHC'),
	(11,'Jackson-Hinds Comprehensive Health Center, Jackson, Mississippi','32.3158','-90.2128','FQHC'),
	(12,'San Ysidro Health Center, San Ysidro, California','32.5556','-117.0470','FQHC');

UNLOCK TABLES;
