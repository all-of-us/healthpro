DROP TABLE IF EXISTS `dashboard_display_values`;

CREATE TABLE `dashboard_display_values` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `metric` varchar(255) DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `display_value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

LOCK TABLES `dashboard_display_values` WRITE;

INSERT INTO `dashboard_display_values` (`id`, `metric`, `code`, `display_value`)
VALUES
	(1,'Participant.race','AMERICAN_INDIAN_OR_ALASKA_NATIVE','American Indian or Alaska Native'),
	(2,'Participant.race','BLACK_OR_AFRICAN_AMERICAN','Black or African American'),
	(3,'Participant.race','ASIAN','Asian'),
	(4,'Participant.race','NATIVE_HAWAIIAN_OR_OTHER_PACIFIC_ISLANDER','Native Hawaiian or other Pacific Islander'),
	(5,'Participant.race','WHITE','White'),
	(6,'Participant.race','HISPANIC_LATINO_OR_SPANISH','Hispanic, Latino, or Spanish'),
	(7,'Participant.race','MIDDLE_EASTERN_OR_NORTH_AFRICAN','Middle Eastern or North African'),
	(8,'Participant.race','HLS_AND_WHITE','White and Hispanic, Latino, or Spanish'),
	(9,'Participant.race','HLS_AND_BLACK','Black and Hispanic, Latino, or Spanish'),
	(10,'Participant.race','HLS_AND_ONE_OTHER_RACE','One other race and Hispanic, Latino, or Spanish'),
	(11,'Participant.race','HLS_AND_MORE_THAN_ONE_OTHER_RACE','More than one race and Hispanic, Latino, or Spanish'),
	(12,'Participant.race','MORE_THAN_ONE_RACE','More than one race'),
	(13,'Participant.race','OTHER_RACE','Other race'),
	(14,'Participant.race','PREFER_NOT_TO_SAY','Prefer not to say'),
	(15,'Participant.genderIdentity','GenderIdentity_Transgender','Transgender'),
	(16,'Participant.genderIdentity','GenderIdentity_Woman','Woman'),
	(17,'Participant.genderIdentity','GenderIdentity_Man','Man'),
	(18,'Participant.genderIdentity','GenderIdentity_AdditionalOptions','Other/Additional Options'),
	(19,'Participant.genderIdentity','GenderIdentity_NonBinary','Non-Binary'),
	(20,'Participant.enrollmentStatus','FULL_PARTICIPANT','Full Participant'),
	(21,'Participant.enrollmentStatus','INTERESTED','Registered'),
	(22,'Participant.enrollmentStatus','MEMBER','Member');

UNLOCK TABLES;