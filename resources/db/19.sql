-- Add postbook message for items
ALTER TABLE `items` ADD `postbookMsg` TEXT NOT NULL AFTER `description`;

INSERT INTO `news` (`newsId`, `date`, `caption`, `body`) VALUES (NULL, CURRENT_DATE(), 'Skicka med resursinformation', 'Det finns nu en ny inställning på resursnivå för att skicka med information med bokningen, likt den funktion som sedan tidigare har funnits på kategorinivå. Om ni t.ex. har låst era kajaker med individuella kodlås så kan ni skicka med aktuell kod vid bokningen.'); 

UPDATE config SET value=19 WHERE name='db-version';