ALTER TABLE evaluations 
ADD COLUMN `fhir_version` int(11) NULL DEFAULT NULL AFTER `version`;
