ALTER TABLE `users` ADD INDEX(`sectionId`);
ALTER TABLE `users` ADD  FOREIGN KEY (`sectionId`) REFERENCES `sections`(`sectionId`) ON DELETE CASCADE ON UPDATE CASCADE;
UPDATE config SET value=2 WHERE name='db-version';