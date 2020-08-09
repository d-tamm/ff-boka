-- Add field for repeating bookings
ALTER TABLE `bookings` ADD `repeatId` INT UNSIGNED NULL DEFAULT NULL AFTER `bookingId`;

INSERT INTO `news` (`newsId`, `date`, `caption`, `body`) VALUES (NULL, CURRENT_DATE(), 'Återkommande bokningar', 'Nu finns det möjlighet att lägga in enkla bokningsserier för dig som har behörighet att boka själv (utan bekräftelse av bokningsadmin). Du kan välja mellan daglig, veckovis och månadsvis upprepning. Skapa först en vanlig bokning till första tillfället. På sammanfattningssidan kan du sedan skapa upprepningarna.'); 

UPDATE config SET value=13 WHERE name='db-version';