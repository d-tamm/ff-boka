-- Add setting to show contact details already in the booking dialog
ALTER TABLE `categories` ADD `showContactWhenBooking` TINYINT UNSIGNED NOT NULL DEFAULT '0' AFTER `contactMail`; 

INSERT INTO `news` (`newsId`, `date`, `caption`, `body`) VALUES (NULL, CURRENT_DATE(), 'Kontaktinformation till bokningsansvarig', 'Nu kan du välja att visa kontaktinformationen till kategorier redan i bokningsflödet, så att folk vet vem de ska vända sig till vid frågor redan då. Aktiveras i den nya fliken Kontaktuppgifter i varje kategori. Det har även blivit tydligare vilka inställningar som gäller för övrigt för kategoriers kontaktuppgifter.'); 

UPDATE config SET value=18 WHERE name='db-version';