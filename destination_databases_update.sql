-- Add destination_type column to existing table
ALTER TABLE `destination_databases` ADD COLUMN `destination_type` VARCHAR(50) NOT NULL DEFAULT 'database' AFTER `connection_name`;

-- Update existing records to have 'database' as destination_type
UPDATE `destination_databases` SET `destination_type` = 'database' WHERE `destination_type` = 'database';

-- Make db_port nullable for SFTP destinations
ALTER TABLE `destination_databases` MODIFY COLUMN `db_port` VARCHAR(255) DEFAULT NULL;

-- Make db_name nullable for SFTP destinations
ALTER TABLE `destination_databases` MODIFY COLUMN `db_name` VARCHAR(255) DEFAULT NULL;

-- Add index for performance
ALTER TABLE `destination_databases` ADD INDEX `idx_destination_type` (`destination_type`);
