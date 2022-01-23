-- Add booking reminders

CREATE TABLE `cat_reminders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `catId` INT UNSIGNED NOT NULL,
    `offset` INT NOT NULL,
    `anchor` ENUM('start','end') NOT NULL,
    `message` VARCHAR(10000) NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`catId`) REFERENCES `categories`(`catId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB; 

CREATE TABLE `item_reminders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `itemId` INT UNSIGNED NOT NULL,
    `offset` INT NOT NULL,
    `anchor` ENUM('start','end') NOT NULL,
    `message` VARCHAR(10000) NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`itemId`) REFERENCES `items`(`itemId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB; 

ALTER TABLE `booked_items` ADD `remindersSent` VARCHAR(255) NOT NULL DEFAULT '[]'; 

INSERT INTO `news` (`newsId`, `date`, `caption`, `body`) VALUES (NULL, CURRENT_DATE(), 'Bokningspåminnelser', 'Nu kan du som admin skapa meddelanden som automatiskt skickas till användarna i samband med att en bokning börjar eller slutar. Användbart t.ex. för att skicka ut koder till kombinationslås.'); 

UPDATE config SET value=23 WHERE name='db-version';