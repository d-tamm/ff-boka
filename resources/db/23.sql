-- Add booking reminders

CREATE TABLE `ff-boka`.`cat_reminders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `catId` INT UNSIGNED NOT NULL,
    `offset` SMALLINT NOT NULL,
    `message` VARCHAR(10000) NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`catId`) REFERENCES `categories`(`catId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB; 

CREATE TABLE `ff-boka`.`item_reminders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `itemId` INT UNSIGNED NOT NULL,
    `offset` SMALLINT NOT NULL,
    `message` VARCHAR(10000) NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`itemId`) REFERENCES `items`(`itemId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB; 

ALTER TABLE `booked_items` ADD `remindersSent` VARCHAR(255) NOT NULL DEFAULT '[]'; 

UPDATE config SET value=23 WHERE name='db-version';