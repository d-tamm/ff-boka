-- Add alphanumerical booking number to bookings

-- Drop relations from booked_items and booking_answers
ALTER TABLE `booked_items` DROP FOREIGN KEY `booked_items_ibfk_2`;
ALTER TABLE `booking_answers` DROP FOREIGN KEY `booking_answers_ibfk_1`;

-- Change data type to string
ALTER TABLE `booked_items` CHANGE `bookingId` `bookingId` char(5) NULL;
ALTER TABLE `booking_answers` CHANGE `bookingId` `bookingId` char(5) NOT NULL;
ALTER TABLE `bookings` CHANGE `bookingId` `bookingId` char(5) NOT NULL;

-- Rebuild relations
ALTER TABLE `booked_items`
ADD FOREIGN KEY (`bookingId`) REFERENCES `bookings` (`bookingId`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `booking_answers`
ADD FOREIGN KEY (`bookingId`) REFERENCES `bookings` (`bookingId`) ON DELETE CASCADE ON UPDATE CASCADE;

UPDATE config SET value=27 WHERE name='db-version';