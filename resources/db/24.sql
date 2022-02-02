-- Add flag showing whether there are new changes to be reviewed by an admin
ALTER TABLE `bookings` ADD `dirty` BOOLEAN NOT NULL DEFAULT FALSE; 

UPDATE config SET value=24 WHERE name='db-version';