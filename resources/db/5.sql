-- Remove full size images from database
ALTER TABLE `item_images` DROP `image`;
ALTER TABLE `categories` DROP `image`;
  
UPDATE config SET value=5 WHERE name='db-version';