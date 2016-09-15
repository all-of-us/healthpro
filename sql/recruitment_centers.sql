# ************************************************************
# Sequel Pro SQL dump
# Version 4541
#
# http://www.sequelpro.com/
# https://github.com/sequelpro/sequelpro
#
# Host: 127.0.0.1 (MySQL 5.6.23)
# Database: pmi-drc-hpo-dev
# Generation Time: 2016-09-15 19:36:07 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table recruitment_centers
# ------------------------------------------------------------

DROP TABLE IF EXISTS `recruitment_centers`;

CREATE TABLE `recruitment_centers` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) DEFAULT NULL,
  `latitude` varchar(255) DEFAULT NULL,
  `longitude` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

LOCK TABLES `recruitment_centers` WRITE;
/*!40000 ALTER TABLE `recruitment_centers` DISABLE KEYS */;

INSERT INTO `recruitment_centers` (`id`, `label`, `latitude`, `longitude`)
VALUES
	(1,'Direct Volunteer','37.0000','-70.0000'),
	(2,'Veterans Administration Medical Center (VA)','33.0000','-71.4000'),
	(3,'RMC - Arizona','33.7712','-111.3877'),
	(4,'RMC - Illinois','40.3363','-89.0022'),
	(5,'RMC - Pitt','40.4397','-79.9765'),
	(6,'RMC - New York','42.1497','-74.9384'),
	(7,'FQHC - Cherokee Health Systems, Knoxville, Tennessee','35.9708','-83.9464'),
	(8,'FQHC - Community Health Center, Inc., Middletown, Connecticut','41.5495','-72.6577'),
	(9,'FQHC - Eau Claire Cooperative Health Center, Columbia, South Carolina','34.0297','-80.8965'),
	(10,'FQHC - HRHCare, Peekskill, New York','41.2873','-73.9235'),
	(11,'FQHC - Jackson-Hinds Comprehensive Health Center, Jackson, Mississippi','32.3158','-90.2128'),
	(12,'FQHC - San Ysidro Health Center, San Ysidro, California','32.5556','-117.0470');

/*!40000 ALTER TABLE `recruitment_centers` ENABLE KEYS */;
UNLOCK TABLES;



/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
