-- Add mail queue for asynchronous sending of mails

CREATE TABLE `mailq` (
    `mailqId` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `to` VARCHAR(255) NOT NULL DEFAULT '',
    `fromName` VARCHAR(255) NOT NULL DEFAULT '',
    `replyTo` VARCHAR(255) NOT NULL DEFAULT '',
    `subject` VARCHAR(255) NOT NULL DEFAULT '',
    `body` TEXT NOT NULL DEFAULT '',
    `attachments` TEXT NOT NULL DEFAULT '' COMMENT 'json object with filename -> absolute_file_paths members',
    PRIMARY KEY (`mailqId`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

UPDATE config SET value=14 WHERE name='db-version';