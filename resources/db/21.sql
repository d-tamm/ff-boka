-- Add NULL to category base permissions
ALTER TABLE `categories`
    CHANGE `accessExternal` `accessExternal` TINYINT(3) UNSIGNED NULL DEFAULT NULL,
    CHANGE `accessMember` `accessMember` TINYINT(3) UNSIGNED NULL DEFAULT NULL,
    CHANGE `accessLocal` `accessLocal` TINYINT(3) UNSIGNED NULL DEFAULT NULL; 

UPDATE config SET value=21 WHERE name='db-version';