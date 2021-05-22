CREATE TABLE `assignments` (
	`assName` VARCHAR(255) NOT NULL,
	`sort` TINYINT UNSIGNED NOT NULL,
	`timestamp` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY( `assName`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci; 

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
) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

INSERT INTO `news` (`newsId`, `date`, `caption`, `body`) VALUES (NULL, CURRENT_DATE(), 'Uppdragsbaserade behörigheter', 'Nu går det även att tilldela bokningsbehörighet baserat på uppdrag från aktivitetshanteraren. Så nu kan du t.ex. ställa in att alla kajakledare själva får boka era kajaker.');

UPDATE config SET value=11 WHERE name='db-version';