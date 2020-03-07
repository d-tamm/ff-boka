-- Need to revert 2.sql and remove foreign key constraint between user and section, 
-- because we sometimes add users without knowing their home section
ALTER TABLE users DROP FOREIGN KEY users_ibfk_1;
ALTER TABLE `users` DROP INDEX `sectionId`;