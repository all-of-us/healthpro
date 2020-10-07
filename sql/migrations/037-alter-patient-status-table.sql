ALTER TABLE `patient_status` ADD UNIQUE KEY `participant_organization_unique` (`participant_id`, `organization`);
