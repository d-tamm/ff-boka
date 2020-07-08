CREATE TABLE `assignments` (
	`assName` VARCHAR(255) NOT NULL,
	`sort` TINYINT UNSIGNED NOT NULL,
	`timestamp` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY( `assName`)
) ENGINE = InnoDB; 

INSERT INTO assignments SET assName="Valfritt uppdrag", sort=0, timestamp=NULL; 
INSERT INTO assignments SET assName="Ledare", sort=1, timestamp=NULL; 
INSERT INTO assignments SET assName="Grenledare", sort=1, timestamp=NULL; 
INSERT INTO assignments SET assName="Hjälpledare", sort=1, timestamp=NULL; 

CREATE TABLE `cat_perms` (
	`assName` VARCHAR(255) NOT NULL,
	`catId` INT UNSIGNED NOT NULL,
	`access` TINYINT UNSIGNED NOT NULL,
	UNIQUE( `catId`, `assName`),
	FOREIGN KEY (`assName`) REFERENCES `assignments`(`assName`) ON DELETE CASCADE ON UPDATE CASCADE,
	FOREIGN KEY (`catId`) REFERENCES `categories`(`catId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

UPDATE config SET value=11 WHERE name='db-version';