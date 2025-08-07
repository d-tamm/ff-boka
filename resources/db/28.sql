UPDATE config SET `value`=DATE_FORMAT(FROM_UNIXTIME(value), '%Y%m%d%H') WHERE `name`='last hourly cron run';
UPDATE config SET `value`=DATE_FORMAT(FROM_UNIXTIME(value), '%Y%m%d') WHERE `name`='last daily cron run';
UPDATE config SET `value`=DATE_FORMAT(FROM_UNIXTIME(value), '%x%v') WHERE `name`='last weekly cron run';
UPDATE config SET `value`=DATE_FORMAT(FROM_UNIXTIME(value), '%Y%m') WHERE `name`='last monthly cron run';
INSERT INTO config SET `value`=0, `name`="current cron job";
UPDATE config SET `value`=28 WHERE `name`='db-version';