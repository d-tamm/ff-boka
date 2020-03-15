-- Add table user_agents

CREATE TABLE `user_agents` (
  `uaHash` varchar(255) COLLATE utf8_swedish_ci NOT NULL COMMENT 'sha1 checksum of ua',
  `userAgent` varchar(511) COLLATE utf8_swedish_ci NOT NULL,
  `platform` varchar(255) COLLATE utf8_swedish_ci NOT NULL,
  `platform_version` varchar(255) COLLATE utf8_swedish_ci NOT NULL,
  `platform_bits` varchar(255) COLLATE utf8_swedish_ci NOT NULL,
  `browser` varchar(255) COLLATE utf8_swedish_ci NOT NULL,
  `version` varchar(255) COLLATE utf8_swedish_ci NOT NULL,
  `device_type` varchar(255) COLLATE utf8_swedish_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

ALTER TABLE `user_agents`
  ADD PRIMARY KEY (`uaHash`);
  
UPDATE config SET value=4 WHERE name='db-version';