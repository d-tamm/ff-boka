-- Add internal notes to items
ALTER TABLE `items` ADD `note` VARCHAR(21000) NOT NULL AFTER `active`;

UPDATE config SET value=6 WHERE name='db-version';