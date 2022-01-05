-- Add target group parameter to polls
ALTER TABLE `polls` ADD `targetGroup` TINYINT UNSIGNED NOT NULL DEFAULT '4' AFTER `expires`; 

UPDATE config SET value=22 WHERE name='db-version';