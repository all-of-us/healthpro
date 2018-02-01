ALTER TABLE orders
ADD COLUMN `processed_centrifuge_type` varchar(50) NULL DEFAULT NULL AFTER `processed_samples_ts`;
