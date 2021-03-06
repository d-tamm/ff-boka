-- Add statistics history table

CREATE TABLE `stats` (
    `statId` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `date` DATE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sectionId` INT UNSIGNED NULL DEFAULT NULL,
    `key` VARCHAR(255) NOT NULL DEFAULT '',
    `value` TEXT NOT NULL DEFAULT '',
    PRIMARY KEY (`statId`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

INSERT INTO `news` (`newsId`, `date`, `caption`, `body`) VALUES (NULL, CURRENT_DATE(), 'Användningsstatistik', 'Du som är administratör kan nu få en överblick över hur bokningssystemet används i din lokalavdelning. Kolla i Övrigt-fliken på admin-sidan.'); 

UPDATE config SET value=17 WHERE name='db-version';
