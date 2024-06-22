-- Change login table to allow for IPv6 addresses
ALTER TABLE `logins` ADD `ip6` tinytext NOT NULL AFTER `ip`;
UPDATE `logins` SET ip6 = INET_NTOA(ip);
ALTER TABLE `logins` DROP `ip`;
ALTER TABLE `logins` RENAME COLUMN `ip6` TO `ip`;

UPDATE config SET value=25 WHERE name='db-version';