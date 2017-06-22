TRUNCATE TABLE `recruitment_center_codes`;

INSERT INTO `recruitment_center_codes` (`code`, `label`, `latitude`, `longitude`, `category`, `recruitment_target`)
VALUES
	('CHEROKEE','Cherokee Health Systems, Knoxville, Tennessee','35.966588','-83.945360','FQHC',10000),
	('COMM_HEALTH','Community Health Center, Inc., Middletown, Connecticut','41.565116','-72.652886','FQHC',10000),
	('EAU_CLAIRE','Eau Claire Cooperative Health Center, Columbia, South Carolina','34.019236','-81.001482','FQHC',10000),
	('HRHCARE','HRHCare, Peekskill, New York','41.291588','-73.918287','FQHC',10000),
	('JACKSON','Jackson-Hinds Comprehensive Health Center, Jackson, Mississippi','32.329822','-90.194839','FQHC',10000),
	('SAN_YSIDRO','San Ysidro Health Center, San Ysidro, California','32.558651','-117.047597','FQHC',10000),
	('CAL_PMC','California Precision Medicine Consortium','32.880060','-117.234014','RMC',10000),
	('COLUMBIA','Columbia University Medical Center, New York City','40.840845','-73.941582','RMC',10000),
	('ILLINOIS','Illinois Precision Medicine Consortium','42.056459','-87.675267','RMC',10000),
	('NE_PMC','New England Precision Medicine Consortium','42.363176','-71.068830','RMC',10000),
	('TRANS_AM','Trans-American Consortium (TAC)','42.367411','-83.085083','RMC',10000),
	('AZ_TUCSON','University of Arizona','32.231885','-110.950109','RMC',10000),
	('PITT','University of Pittsburgh','40.444353','-79.960835','RMC',10000),
	('VA','U.S. Department of Veterans Affairs Medical Centers','33.0000','-71.4000','VA',150000),
	('QTC','QTC','33.0000','-71.5000','Direct Volunteer',10000),
	('WALGREENS','Walgreens','33.0000','-71.6000','Direct Volunteer',150000),
	('QUEST','Quest Labs','33.0000','-71.7000','Direct Volunteer',150000),
	('EMSI','EMSI','33.0000','-71.8000','Direct Volunteer',150000),
	('SDBB','SDBB','33.0000','-71.9000','Direct Volunteer',150000);
