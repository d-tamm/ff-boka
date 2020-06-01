-- Add default values
ALTER TABLE booked_items
	CHANGE `start` DEFAULT 0,
	CHANGE `end` DEFAULT 0,
	CHANGE `status` DEFAULT 0;
ALTER TABLE bookings
	CHANGE `commentCust` DEFAULT '',
	CHANGE `commentIntern` DEFAULT '',
	CHANGE `paid` DEFAULT 0,
	CHANGE `extName` DEFAULT '',
	CHANGE `extPhone` DEFAULT '',
	CHANGE `extMail` DEFAULT '',
	CHANGE `token` DEFAULT '',
	CHANGE `confirmationSent` DEFAULT 0;
ALTER TABLE booking_answers
	CHANGE `question` DEFAULT '',
	CHANGE `answer` DEFAULT '';
ALTER TABLE categories
	CHANGE `caption` DEFAULT '',
	CHANGE `prebookMsg` DEFAULT '',
	CHANGE `postbookMsg` DEFAULT '',
	CHANGE `bufferAfterBooking` DEFAULT 0,
	CHANGE `sendAlertTo` DEFAULT '',
	CHANGE `contactName` DEFAULT '',
	CHANGE `contactPhone` DEFAULT '',
	CHANGE `contactMail` DEFAULT '',
	CHANGE `accessExternal` DEFAULT 0,
	CHANGE `accessMember` DEFAULT 0,
	CHANGE `accessLocal` DEFAULT 0,
	CHANGE `hideForExt` DEFAULT 0;
ALTER TABLE cat_admins
	CHANGE `access` DEFAULT 0;
ALTER TABLE cat_files
	CHANGE `filename` DEFAULT '',
	CHANGE `md5` DEFAULT '',
	CHANGE `caption` DEFAULT '';
ALTER TABLE items
	CHANGE `caption` DEFAULT '',
	CHANGE `description` DEFAULT '',
	CHANGE `note` DEFAULT '';
ALTER TABLE item_images
	CHANGE `caption` DEFAULT '';
ALTER TABLE logins
	CHANGE `ip` DEFAULT 0,
	CHANGE `userId` DEFAULT '',
	CHANGE `userAgent` DEFAULT '';	
ALTER TABLE persistent_logins
	CHANGE `userAgent` DEFAULT '',
	CHANGE `selector` DEFAULT '',
	CHANGE `authenticator` DEFAULT '',
	CHANGE `expires` DEFAULT 0;
ALTER TABLE questions
	CHANGE `caption` DEFAULT '',
	CHANGE `options` DEFAULT '';
ALTER TABLE sections
	CHANGE `name` DEFAULT '';
ALTER TABLE tokens
	CHANGE `useFor` DEFAULT '',
	CHANGE `forId` DEFAULT 0;
ALTER TABLE users
	CHANGE COLUMN `name` DEFAULT '',
	CHANGE `mail` DEFAULT '',
	CHANGE `phone` DEFAULT '',
	CHANGE `sectionId` DEFAULT '0';
ALTER TABLE user_agents
	CHANGE `uaHash` DEFAULT '',
	CHANGE `userAgent` DEFAULT '',
	CHANGE `platform` DEFAULT '',
	CHANGE `platform_version` DEFAULT '',
	CHANGE `platform_bits` DEFAULT '',
	CHANGE `browser` DEFAULT '',
	CHANGE `version` DEFAULT '',
	CHANGE `device_type` DEFAULT '';
	
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