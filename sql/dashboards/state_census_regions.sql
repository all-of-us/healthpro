DROP TABLE IF EXISTS `state_census_regions`;

CREATE TABLE `state_census_regions` (
  `id` int(11) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `census_region_id` int(11) DEFAULT NULL,
  `recruitment_target` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

LOCK TABLES `state_census_regions` WRITE;

INSERT INTO `state_census_regions` (`id`, `state`, `census_region_id`, `recruitment_target`)
VALUES
	(1,'AL',1,15932),
	(2,'AK',2,2367),
	(3,'AZ',2,21307),
	(4,'AR',1,9720),
	(5,'CA',2,124180),
	(6,'CO',2,16764),
	(7,'CT',4,11914),
	(8,'DE',1,2993),
	(9,'DC',1,2006),
	(10,'FL',1,62671),
	(11,'GA',1,32292),
	(12,'HI',2,4534),
	(13,'ID',2,5225),
	(14,'IL',3,42769),
	(15,'IN',3,21613),
	(16,'IA',3,10155),
	(17,'KS',3,9510),
	(18,'KY',1,14465),
	(19,'LA',1,15111),
	(20,'ME',4,4428),
	(21,'MD',1,19245),
	(22,'MA',4,21825),
	(23,'MI',3,32945),
	(24,'MN',3,17680),
	(25,'MS',1,9891),
	(26,'MO',3,19963),
	(27,'MT',2,3298),
	(28,'NE',3,6088),
	(29,'NV',2,9002),
	(30,'NH',4,4388),
	(31,'NJ',4,29306),
	(32,'NM',2,6864),
	(33,'NY',4,64594),
	(34,'NC',1,31785),
	(35,'ND',3,2242),
	(36,'OH',3,38455),
	(37,'OK',1,12505),
	(38,'OR',2,12770),
	(39,'PA',4,42341),
	(40,'RI',4,3509),
	(41,'SC',1,15418),
	(42,'SD',3,2714),
	(43,'TN',1,21154),
	(44,'TX',1,83819),
	(45,'UT',2,9213),
	(46,'VT',4,2086),
	(47,'VA',1,26670),
	(48,'WA',2,22415),
	(49,'WV',1,6177),
	(50,'WI',3,18957),
	(51,'WY',2,1879);

UNLOCK TABLES;