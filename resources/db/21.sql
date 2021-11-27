-- Add NULL to category base permissions
ALTER TABLE `categories`
    CHANGE `accessExternal` `accessExternal` TINYINT(3) UNSIGNED NULL DEFAULT NULL,
    CHANGE `accessMember` `accessMember` TINYINT(3) UNSIGNED NULL DEFAULT NULL,
    CHANGE `accessLocal` `accessLocal` TINYINT(3) UNSIGNED NULL DEFAULT NULL; 

-- Set all rows to NULL which until now were set to 0.
UPDATE categories SET accessExternal=NULL WHERE accessExternal=0;
UPDATE categories SET accessMember=NULL WHERE accessMember=0;
UPDATE categories SET accessLocal=NULL WHERE accessLocal=0;

UPDATE config SET value=21 WHERE name='db-version';