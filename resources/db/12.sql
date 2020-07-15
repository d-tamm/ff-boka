-- Add reference field to bookings
ALTER TABLE `bookings` ADD `ref` VARCHAR(255) NOT NULL DEFAULT '' AFTER `timestamp`; 

UPDATE config SET value=12 WHERE name='db-version';