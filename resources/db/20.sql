-- Add lookup counter to user agent table for more efficient lookups
ALTER TABLE `user_agents` ADD `lookups` INT UNSIGNED NOT NULL AFTER `device_type`; 

UPDATE config SET value=20 WHERE name='db-version';