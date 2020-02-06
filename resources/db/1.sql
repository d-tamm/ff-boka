ALTER TABLE `config` ADD PRIMARY KEY( `name`);
INSERT INTO config SET name="last hourly cron run", value="0";
INSERT INTO config SET name="last daily cron run", value="0";
INSERT INTO config SET name="last weekly cron run", value="0";
INSERT INTO config SET name="last monthly cron run", value="0";