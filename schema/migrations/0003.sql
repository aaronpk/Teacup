ALTER TABLE entries
ADD COLUMN `checkin_url` VARCHAR(512) NOT NULL DEFAULT '' AFTER `longitude`;
