-- Add statistics history table

CREATE TABLE `stats` (
    `statId` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `date` DATE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sectionId` INT UNSIGNED NULL DEFAULT NULL,
    `key` VARCHAR(255) NOT NULL DEFAULT '',
    `value` TEXT NOT NULL DEFAULT '',
    PRIMARY KEY (`statId`)
) ENGINE = InnoDB;


UPDATE config SET value=17 WHERE name='db-version';