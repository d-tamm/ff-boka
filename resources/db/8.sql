-- Add default values
ALTER TABLE booked_items
	ALTER COLUMN `start` SET DEFAULT 0,
	ALTER COLUMN `end` SET DEFAULT 0,
	ALTER COLUMN `status` SET DEFAULT 0;
ALTER TABLE bookings
	ALTER COLUMN `commentCust` SET DEFAULT '',
	ALTER COLUMN `commentIntern` SET DEFAULT '',
	ALTER COLUMN `paid` SET DEFAULT 0,
	ALTER COLUMN `extName` SET DEFAULT '',
	ALTER COLUMN `extPhone` SET DEFAULT '',
	ALTER COLUMN `extMail` SET DEFAULT '',
	ALTER COLUMN `token` SET DEFAULT '',
	ALTER COLUMN `confirmationSent` SET DEFAULT 0;
ALTER TABLE booking_answers
	ALTER COLUMN `question` SET DEFAULT '',
	ALTER COLUMN `answer` SET DEFAULT '';
ALTER TABLE categories
	ALTER COLUMN `caption` SET DEFAULT '',
	ALTER COLUMN `prebookMsg` SET DEFAULT '',
	ALTER COLUMN `postbookMsg` SET DEFAULT '',
	ALTER COLUMN `bufferAfterBooking` SET DEFAULT 0,
	ALTER COLUMN `sendAlertTo` SET DEFAULT '',
	ALTER COLUMN `contactName` SET DEFAULT '',
	ALTER COLUMN `contactPhone` SET DEFAULT '',
	ALTER COLUMN `contactMail` SET DEFAULT '',
	ALTER COLUMN `accessExternal` SET DEFAULT 0,
	ALTER COLUMN `accessMember` SET DEFAULT 0,
	ALTER COLUMN `accessLocal` SET DEFAULT 0,
	ALTER COLUMN `hideForExt` SET DEFAULT 0;
ALTER TABLE cat_admins
	ALTER COLUMN `access` SET DEFAULT 0;
ALTER TABLE cat_files
	ALTER COLUMN `filename` SET DEFAULT '',
	ALTER COLUMN `md5` SET DEFAULT '',
	ALTER COLUMN `caption` SET DEFAULT '';
ALTER TABLE items
	ALTER COLUMN `caption` SET DEFAULT '',
	ALTER COLUMN `description` SET DEFAULT '',
	ALTER COLUMN `note` SET DEFAULT '';
ALTER TABLE item_images
	ALTER COLUMN `caption` SET DEFAULT '';
ALTER TABLE logins
	ALTER COLUMN `ip` SET DEFAULT 0,
	ALTER COLUMN `userId` SET DEFAULT '',
	ALTER COLUMN `userAgent` SET DEFAULT '';	
ALTER TABLE persistent_logins
	ALTER COLUMN `userAgent` SET DEFAULT '',
	ALTER COLUMN `selector` SET DEFAULT '',
	ALTER COLUMN `authenticator` SET DEFAULT '',
	ALTER COLUMN `expires` SET DEFAULT 0;
ALTER TABLE questions
	ALTER COLUMN `caption` SET DEFAULT '',
	ALTER COLUMN `options` SET DEFAULT '';
ALTER TABLE sections
	ALTER COLUMN `name` SET DEFAULT '';
ALTER TABLE tokens
	ALTER COLUMN `useFor` SET DEFAULT '',
	ALTER COLUMN `forId` SET DEFAULT 0;
ALTER TABLE users
	ALTER COLUMN `name` SET DEFAULT '',
	ALTER COLUMN `mail` SET DEFAULT '',
	ALTER COLUMN `phone` SET DEFAULT '',
	ALTER COLUMN `sectionId` SET DEFAULT '0';
ALTER TABLE user_agents
	ALTER COLUMN `uaHash` SET DEFAULT '',
	ALTER COLUMN `userAgent` SET DEFAULT '',
	ALTER COLUMN `platform` SET DEFAULT '',
	ALTER COLUMN `platform_version` SET DEFAULT '',
	ALTER COLUMN `platform_bits` SET DEFAULT '',
	ALTER COLUMN `browser` SET DEFAULT '',
	ALTER COLUMN `version` SET DEFAULT '',
	ALTER COLUMN `device_type` SET DEFAULT '';
	
-- Add Latest News table 
CREATE TABLE `news` (
	`newsId` INT NOT NULL AUTO_INCREMENT ,
	`date` DATE NOT NULL DEFAULT 0,
	`caption` VARCHAR(255) NOT NULL DEFAULT '',
	`body` TEXT NOT NULL DEFAULT '',
	PRIMARY KEY (`newsId`)
) ENGINE = InnoDB CHARSET=utf8 COLLATE utf8_swedish_ci; 

INSERT INTO news SET date="2020-02-17", caption="Inloggning med personnummer", body="Nu kan du även logga in med personnummer";
INSERT INTO news SET date="2020-03-04", caption="Ändring av personuppgifter", body="Från och med nu måste ändringar av användaruppgifter bekräftas med aktuellt lösenord.";
INSERT INTO news SET date="2020-03-11", caption="Enklare att komma igång", body="Nu ska det vara enklare för nya lokalavdelningar att komma igång. När ordförande loggar in första gången slipper hen lägga upp ett konto och skickas direkt till sidan för att skapa administratörer.";
INSERT INTO news SET date="2020-03-27", caption="Samma tid för tillagda resurser", body="Om du kompletterar en bokning med fler resurser så är start- och sluttiden nu förinställd att vara samma som för befintliga resurser i bokningen.";
INSERT INTO news SET date="2020-04-03", caption="Interna anteckningar för resurser", body="Nu går det att lägga in interna anteckningar till resurser. Användbart för att dokumentera t.ex. underhållsbehov eller annan intern information.";
INSERT INTO news SET date="2020-04-15", caption="Bilagor till resurser", body="Nu kan du visa och skicka med valfria bilagor vid bokningar, t.ex. kontraktmall, vägbeskrivning mm.";

UPDATE config SET value=8 WHERE name='db-version';