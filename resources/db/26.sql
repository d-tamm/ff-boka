-- Add active flag to categories
ALTER TABLE `categories` ADD `active` tinyint(1) NOT NULL;
UPDATE `categories` SET active = 1;

UPDATE config SET value=26 WHERE name='db-version';