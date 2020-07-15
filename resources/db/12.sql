-- Add reference field to bookings
ALTER TABLE `bookings` ADD `ref` VARCHAR(255) NOT NULL DEFAULT '' AFTER `timestamp`; 

-- Add a news item
INSERT INTO `news` (`newsId`, `date`, `caption`, `body`) VALUES (NULL, CURRENT_DATE(), 'Bokningsreferens', 'När du lägger en bokning kan du nu lägga till en referens så du lättare kan hålla isär dina bokningar på Min Sida.');

UPDATE config SET value=12 WHERE name='db-version';