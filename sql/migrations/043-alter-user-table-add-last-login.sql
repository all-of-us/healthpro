ALTER TABLE users
ADD COLUMN `last_login` datetime DEFAULT NULL AFTER `timezone`;
