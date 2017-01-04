ALTER TABLE users
ADD COLUMN `device_code` varchar(10) DEFAULT NULL,
ADD COLUMN `device_code_expires` datetime DEFAULT NULL;
