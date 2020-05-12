ALTER TABLE `logins` CHANGE `userId` `login` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT ''; 
ALTER TABLE `logins` ADD `userId` MEDIUMINT NULL DEFAULT NULL AFTER `login`; 

UPDATE config SET value=9 WHERE name='db-version';