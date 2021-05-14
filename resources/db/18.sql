-- Add setting to show contact details already in the booking dialog
ALTER TABLE `categories` ADD `showContactWhenBooking` TINYINT UNSIGNED NOT NULL DEFAULT '0' AFTER `contactMail`; 

UPDATE config SET value=18 WHERE name='db-version';