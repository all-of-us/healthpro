DROP TABLE IF EXISTS `state_census_regions`;

CREATE TABLE `state_census_regions` (
  `id` int(11) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `census_region_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

LOCK TABLES `state_census_regions` WRITE;

INSERT INTO `state_census_regions` (`id`, `state`, `census_region_id`)
VALUES
	(1,'AL',1),
	(2,'AK',2),
	(3,'AZ',2),
	(4,'AR',1),
	(5,'CA',2),
	(6,'CO',2),
	(7,'CT',4),
	(8,'DE',1),
	(9,'DC',1),
	(10,'FL',1),
	(11,'GA',1),
	(12,'HI',2),
	(13,'ID',2),
	(14,'IL',3),
	(15,'IN',3),
	(16,'IA',3),
	(17,'KS',3),
	(18,'KY',1),
	(19,'LA',1),
	(20,'ME',4),
	(21,'MD',1),
	(22,'MA',4),
	(23,'MI',3),
	(24,'MN',3),
	(25,'MS',1),
	(26,'MO',3),
	(27,'MT',2),
	(28,'NE',3),
	(29,'NV',2),
	(30,'NH',4),
	(31,'NJ',4),
	(32,'NM',2),
	(33,'NY',4),
	(34,'NC',1),
	(35,'ND',3),
	(36,'OH',3),
	(37,'OK',1),
	(38,'OR',2),
	(39,'PA',4),
	(40,'RI',4),
	(41,'SC',1),
	(42,'SD',3),
	(43,'TN',1),
	(44,'TX',1),
	(45,'UT',2),
	(46,'VT',4),
	(47,'VA',1),
	(48,'WA',2),
	(49,'WV',1),
	(50,'WI',3),
	(51,'WY',2);

UNLOCK TABLES;