-- Add default values
ALTER TABLE booked_items
	ALTER `start` SET DEFAULT 0,
	ALTER `end` SET DEFAULT 0,
	ALTER `status` SET DEFAULT 0;
ALTER TABLE bookings
	ALTER `commentCust` SET DEFAULT '',
	ALTER `commentIntern` SET DEFAULT '',
	ALTER `paid` SET DEFAULT 0,
	ALTER `extName` SET DEFAULT '',
	ALTER `extPhone` SET DEFAULT '',
	ALTER `extMail` SET DEFAULT '',
	ALTER `token` SET DEFAULT '',
	ALTER `confirmationSent` SET DEFAULT 0;
ALTER TABLE booking_answers
	ALTER `question` SET DEFAULT '',
	ALTER `answer` SET DEFAULT '';
ALTER TABLE categories
	ALTER `caption` SET DEFAULT '',
	ALTER `prebookMsg` SET DEFAULT '',
	ALTER `postbookMsg` SET DEFAULT '',
	ALTER `bufferAfterBooking` SET DEFAULT 0,
	ALTER `sendAlertTo` SET DEFAULT '',
	ALTER `contactName` SET DEFAULT '',
	ALTER `contactPhone` SET DEFAULT '',
	ALTER `contactMail` SET DEFAULT '',
	ALTER `accessExternal` SET DEFAULT 0,
	ALTER `accessMember` SET DEFAULT 0,
	ALTER `accessLocal` SET DEFAULT 0,
	ALTER `hideForExt` SET DEFAULT 0;
ALTER TABLE cat_admins
	ALTER `access` SET DEFAULT 0;
ALTER TABLE cat_files
	ALTER `filename` SET DEFAULT '',
	ALTER `md5` SET DEFAULT '',
	ALTER `caption` SET DEFAULT '';
ALTER TABLE items
	ALTER `caption` SET DEFAULT '',
	ALTER `description` SET DEFAULT '',
	ALTER `note` SET DEFAULT '';
ALTER TABLE item_images
	ALTER `caption` SET DEFAULT '';
ALTER TABLE logins
	ALTER `ip` SET DEFAULT 0,
	ALTER `userId` SET DEFAULT '',
	ALTER `userAgent` SET DEFAULT '';	
ALTER TABLE persistent_logins
	ALTER `userAgent` SET DEFAULT '',
	ALTER `selector` SET DEFAULT '',
	ALTER `authenticator` SET DEFAULT '',
	ALTER `expires` SET DEFAULT 0;
ALTER TABLE questions
	ALTER `caption` SET DEFAULT '',
	ALTER `options` SET DEFAULT '';
ALTER TABLE sections
	ALTER `name` SET DEFAULT '';
ALTER TABLE tokens
	ALTER `useFor` SET DEFAULT '',
	ALTER `forId` SET DEFAULT 0;
ALTER TABLE users
	ALTER COLUMN `name` SET DEFAULT '',
	ALTER `mail` SET DEFAULT '',
	ALTER `phone` SET DEFAULT '',
	ALTER `sectionId` SET DEFAULT '0';
ALTER TABLE user_agents
	ALTER `uaHash` SET DEFAULT '',
	ALTER `userAgent` SET DEFAULT '',
	ALTER `platform` SET DEFAULT '',
	ALTER `platform_version` SET DEFAULT '',
	ALTER `platform_bits` SET DEFAULT '',
	ALTER `browser` SET DEFAULT '',
	ALTER `version` SET DEFAULT '',
	ALTER `device_type` SET DEFAULT '';
	
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