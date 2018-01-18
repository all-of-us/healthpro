ALTER TABLE evaluations 
ADD COLUMN `fhir_version` varchar(10) NULL DEFAULT NULL AFTER `version`;
