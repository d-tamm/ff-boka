-- Add flag that user has accepted to publish contact data. Default to FALSE for legacy bookings.
ALTER TABLE `bookings` ADD `okShowContactData` BOOLEAN NOT NULL DEFAULT FALSE AFTER `confirmationSent`; 

INSERT INTO `news` (`newsId`, `date`, `caption`, `body`) VALUES (NULL, CURRENT_DATE(), 'Nu kan du se kontaktinformation', 'Efter önskemål från er användare går det framöver att se kontaktinformationen till andras bokningar - OM du är inloggad. Syftet är att underlätta att ta kontakt med andra användare för att samordna logistiken kring bokningar. Nu kan du t.ex. ta kontakt med föregångaren vid kvarglömda saker eller om något av utrustningen saknas.'); 

UPDATE config SET value=16 WHERE name='db-version';