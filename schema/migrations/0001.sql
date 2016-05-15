ALTER TABLE users
ADD COLUMN `micropub_media_endpoint` VARCHAR(255) NOT NULL DEFAULT '' AFTER `micropub_endpoint`;

ALTER TABLE entries
ADD COLUMN `photo_url` VARCHAR(255) NOT NULL DEFAULT '' AFTER `canonical_url`;
