-- Shift up status to make place for new 'rejected' status
UPDATE booked_items SET status = status+1 WHERE status > 0;

-- Add field to remember confirmation mails
ALTER TABLE bookings ADD confirmationSent BOOLEAN NOT NULL AFTER token;

-- Add table for attached files
CREATE TABLE `cat_files`(
  `fileId` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `catId` INT UNSIGNED NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `md5` VARCHAR(32) NOT NULL,
  `caption` VARCHAR(255) NOT NULL,
  `displayLink` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'display link on booking page',
  `attachFile` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'attach file or attach link to booking confirmation?',
  PRIMARY KEY(`fileId`),
  UNIQUE (`md5`, `catId`),
  FOREIGN KEY(`catId`) REFERENCES `categories`(`catId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

UPDATE config SET value=7 WHERE name='db-version';