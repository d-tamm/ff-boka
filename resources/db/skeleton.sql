-- phpMyAdmin SQL Dump
-- version 4.5.4.1deb2ubuntu2.1
-- http://www.phpmyadmin.net
--
-- Värd: localhost
-- Tid vid skapande: 31 jan 2020 kl 22:00
-- Serverversion: 10.0.38-MariaDB-0ubuntu0.16.04.1
-- PHP-version: 7.0.33-0ubuntu0.16.04.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Databas: `ff-boka`
--

-- --------------------------------------------------------

--
-- Tabellstruktur `booked_items`
--

CREATE TABLE `booked_items` (
  `bookedItemId` int(10) UNSIGNED NOT NULL,
  `bookingId` int(10) UNSIGNED DEFAULT NULL,
  `itemId` int(10) UNSIGNED DEFAULT NULL,
  `start` datetime NOT NULL,
  `end` datetime NOT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL,
  `price` int(11) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

--
-- RELATIONER FÖR TABELL `booked_items`:
--   `itemId`
--       `items` -> `itemId`
--   `bookingId`
--       `bookings` -> `bookingId`
--

-- --------------------------------------------------------

--
-- Tabellstruktur `bookings`
--

CREATE TABLE `bookings` (
  `bookingId` int(10) UNSIGNED NOT NULL,
  `sectionId` int(10) UNSIGNED DEFAULT NULL,
  `userId` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL for external user',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `commentCust` text COLLATE utf8_swedish_ci NOT NULL,
  `commentIntern` text COLLATE utf8_swedish_ci NOT NULL,
  `paid` int(10) UNSIGNED NOT NULL,
  `extName` varchar(50) COLLATE utf8_swedish_ci NOT NULL,
  `extPhone` varchar(15) COLLATE utf8_swedish_ci NOT NULL,
  `extMail` varchar(50) COLLATE utf8_swedish_ci NOT NULL,
  `token` varchar(40) COLLATE utf8_swedish_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

--
-- RELATIONER FÖR TABELL `bookings`:
--   `userId`
--       `users` -> `userId`
--   `sectionId`
--       `sections` -> `sectionId`
--

-- --------------------------------------------------------

--
-- Tabellstruktur `booking_answers`
--

CREATE TABLE `booking_answers` (
  `answerId` int(10) UNSIGNED NOT NULL,
  `bookingId` int(10) UNSIGNED NOT NULL,
  `question` varchar(255) COLLATE utf8_swedish_ci NOT NULL,
  `answer` text COLLATE utf8_swedish_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

--
-- RELATIONER FÖR TABELL `booking_answers`:
--   `bookingId`
--       `bookings` -> `bookingId`
--

-- --------------------------------------------------------

--
-- Tabellstruktur `categories`
--

CREATE TABLE `categories` (
  `catId` int(10) UNSIGNED NOT NULL,
  `parentId` int(10) UNSIGNED DEFAULT NULL,
  `sectionId` int(10) UNSIGNED NOT NULL COMMENT 'section_id from API',
  `caption` varchar(255) COLLATE utf8_swedish_ci NOT NULL,
  `image` mediumblob,
  `thumb` blob,
  `prebookMsg` text COLLATE utf8_swedish_ci NOT NULL COMMENT 'shown before booking',
  `postbookMsg` text COLLATE utf8_swedish_ci NOT NULL,
  `bufferAfterBooking` int(10) UNSIGNED NOT NULL COMMENT 'Buffer time in hours after a booking during which no new booking may happen',
  `sendAlertTo` varchar(255) COLLATE utf8_swedish_ci NOT NULL,
  `contactUserId` int(10) UNSIGNED DEFAULT NULL,
  `contactName` varchar(100) COLLATE utf8_swedish_ci NOT NULL,
  `contactPhone` varchar(50) COLLATE utf8_swedish_ci NOT NULL,
  `contactMail` varchar(100) COLLATE utf8_swedish_ci NOT NULL,
  `accessExternal` tinyint(3) UNSIGNED NOT NULL,
  `accessMember` tinyint(3) UNSIGNED NOT NULL,
  `accessLocal` tinyint(3) UNSIGNED NOT NULL,
  `hideForExt` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci COMMENT='Categories are defined on section level';

--
-- RELATIONER FÖR TABELL `categories`:
--   `parentId`
--       `categories` -> `catId`
--   `sectionId`
--       `sections` -> `sectionId`
--   `contactUserId`
--       `users` -> `userId`
--

-- --------------------------------------------------------

--
-- Tabellstruktur `cat_admins`
--

CREATE TABLE `cat_admins` (
  `catId` int(10) UNSIGNED NOT NULL,
  `userId` int(10) UNSIGNED NOT NULL,
  `access` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci COMMENT='Access to categories based on member ID';

--
-- RELATIONER FÖR TABELL `cat_admins`:
--   `catId`
--       `categories` -> `catId`
--   `userId`
--       `users` -> `userId`
--

-- --------------------------------------------------------

--
-- Tabellstruktur `cat_admin_noalert`
--

CREATE TABLE `cat_admin_noalert` (
  `userId` int(10) UNSIGNED DEFAULT NULL,
  `catId` int(10) UNSIGNED DEFAULT NULL,
  `notify` set('no','confirmOnly') COLLATE utf8_swedish_ci NOT NULL DEFAULT 'no'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci COMMENT='Categories where admin user does not want to receive alerts on new booking';

--
-- RELATIONER FÖR TABELL `cat_admin_noalert`:
--   `catId`
--       `categories` -> `catId`
--   `userId`
--       `users` -> `userId`
--

-- --------------------------------------------------------

--
-- Tabellstruktur `cat_questions`
--

CREATE TABLE `cat_questions` (
  `questionId` int(10) UNSIGNED NOT NULL,
  `catId` int(10) UNSIGNED NOT NULL,
  `required` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

--
-- RELATIONER FÖR TABELL `cat_questions`:
--   `catId`
--       `categories` -> `catId`
--   `questionId`
--       `questions` -> `questionId`
--

-- --------------------------------------------------------

--
-- Tabellstruktur `config`
--

CREATE TABLE `config` (
  `name` varchar(255) COLLATE utf8_swedish_ci NOT NULL,
  `value` mediumtext COLLATE utf8_swedish_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

--
-- RELATIONER FÖR TABELL `config`:
--

--
-- Dumpning av Data i tabell `config`
--

INSERT INTO `config` (`name`, `value`) VALUES
('db-version', '0');

-- --------------------------------------------------------

--
-- Tabellstruktur `items`
--

CREATE TABLE `items` (
  `itemId` int(10) UNSIGNED NOT NULL,
  `catId` int(10) UNSIGNED NOT NULL COMMENT 'Category',
  `caption` varchar(255) COLLATE utf8_swedish_ci NOT NULL,
  `description` text COLLATE utf8_swedish_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `imageId` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

--
-- RELATIONER FÖR TABELL `items`:
--   `catId`
--       `categories` -> `catId`
--   `imageId`
--       `item_images` -> `imageId`
--

-- --------------------------------------------------------

--
-- Tabellstruktur `item_images`
--

CREATE TABLE `item_images` (
  `imageId` int(10) UNSIGNED NOT NULL,
  `itemId` int(10) UNSIGNED NOT NULL,
  `image` mediumblob,
  `thumb` blob,
  `caption` varchar(1000) COLLATE utf8_swedish_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

--
-- RELATIONER FÖR TABELL `item_images`:
--   `itemId`
--       `items` -> `itemId`
--

-- --------------------------------------------------------

--
-- Tabellstruktur `logins`
--

CREATE TABLE `logins` (
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` bigint(10) UNSIGNED NOT NULL,
  `userId` varchar(255) COLLATE utf8_swedish_ci NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `userAgent` varchar(255) COLLATE utf8_swedish_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci COMMENT='used against brute force attacs';

--
-- RELATIONER FÖR TABELL `logins`:
--

-- --------------------------------------------------------

--
-- Tabellstruktur `persistent_logins`
--

CREATE TABLE `persistent_logins` (
  `userId` int(10) UNSIGNED DEFAULT NULL,
  `userAgent` varchar(255) COLLATE utf8_swedish_ci NOT NULL,
  `selector` varchar(40) COLLATE utf8_swedish_ci NOT NULL,
  `authenticator` varchar(255) COLLATE utf8_swedish_ci NOT NULL COMMENT 'saved as sha256 hash',
  `expires` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

--
-- RELATIONER FÖR TABELL `persistent_logins`:
--   `userId`
--       `users` -> `userId`
--

-- --------------------------------------------------------

--
-- Tabellstruktur `questions`
--

CREATE TABLE `questions` (
  `questionId` int(10) UNSIGNED NOT NULL,
  `sectionId` int(10) UNSIGNED NOT NULL,
  `type` set('radio','checkbox','text','number') COLLATE utf8_swedish_ci NOT NULL DEFAULT 'text',
  `caption` varchar(255) COLLATE utf8_swedish_ci NOT NULL,
  `options` text COLLATE utf8_swedish_ci NOT NULL COMMENT 'json string'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

--
-- RELATIONER FÖR TABELL `questions`:
--   `sectionId`
--       `sections` -> `sectionId`
--

-- --------------------------------------------------------

--
-- Tabellstruktur `sections`
--

CREATE TABLE `sections` (
  `sectionId` int(10) UNSIGNED NOT NULL COMMENT 'cint_nummer',
  `name` varchar(255) COLLATE utf8_swedish_ci NOT NULL COMMENT 'cint_name',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

--
-- RELATIONER FÖR TABELL `sections`:
--

--
-- Dumpning av Data i tabell `sections`
--

INSERT INTO `sections` (`sectionId`, `name`, `timestamp`) VALUES
(1, 'Karlshamn', '2019-10-18 15:26:44'),
(2, 'Karlskrona', '2019-10-18 15:26:44'),
(4, 'Ronneby', '2019-10-18 15:26:44'),
(5, 'Sölvesborg', '2019-10-18 15:26:44'),
(6, 'Bengtsfors', '2019-10-18 15:26:44'),
(7, 'Dals Ed', '2019-10-18 15:26:44'),
(8, 'Ljungskile', '2019-10-18 15:26:44'),
(9, 'Lysekil', '2019-10-18 15:26:44'),
(10, 'Munkedal', '2019-10-18 15:26:44'),
(11, 'Orust', '2019-10-18 15:26:44'),
(12, 'Stenungsund', '2019-10-18 15:26:44'),
(13, 'Strömstad', '2019-10-18 15:26:44'),
(14, 'Sörbygden', '2019-10-18 15:26:44'),
(15, 'Tanumshede', '2019-10-18 15:26:44'),
(16, 'Tjörn', '2019-10-18 15:26:44'),
(17, 'Uddevalla', '2019-10-18 15:26:44'),
(18, 'Åmål-Säffle', '2019-10-18 15:26:44'),
(19, 'Stora Kornö', '2019-10-18 15:26:44'),
(20, 'Avesta', '2019-10-18 15:26:44'),
(21, 'Borlänge', '2019-10-18 15:26:44'),
(22, 'Falun', '2019-10-18 15:26:44'),
(23, 'Gagnef', '2019-10-18 15:26:44'),
(24, 'Leksand', '2019-10-18 15:26:44'),
(25, 'Ludvika', '2019-10-18 15:26:44'),
(26, 'Långshyttan', '2019-10-18 15:26:44'),
(27, 'Mora-Orsa', '2019-10-18 15:26:44'),
(28, 'Runnskrinnarna', '2019-10-18 15:26:44'),
(29, 'Rättvik', '2019-10-18 15:26:44'),
(30, 'Smedjebacken', '2019-10-18 15:26:44'),
(31, 'Svärdsjö', '2019-10-18 15:26:44'),
(32, 'Säter', '2019-10-18 15:26:44'),
(33, 'Gotland', '2019-10-18 15:26:44'),
(34, 'Gävle', '2019-10-18 15:26:44'),
(35, 'Hofors', '2019-10-18 15:26:44'),
(36, 'Ockelbo', '2019-10-18 15:26:44'),
(37, 'Sandviken', '2019-10-18 15:26:44'),
(38, 'Göteborg-Lärjedalen', '2019-10-18 15:26:44'),
(41, 'Centrala Göteborg', '2019-10-18 15:26:44'),
(42, 'Hisingen', '2019-10-18 15:26:44'),
(43, 'Västra Göteborg', '2019-10-18 15:26:44'),
(44, 'Kortedala', '2019-10-18 15:26:44'),
(45, 'Kungälv', '2019-10-18 15:26:44'),
(46, 'Kusten', '2019-10-18 15:26:44'),
(47, 'Landvetter', '2019-10-18 15:26:44'),
(48, 'Lerum', '2019-10-18 15:26:44'),
(49, 'Sandsjöbacka', '2019-10-18 15:26:44'),
(50, 'Lödösebygden', '2019-10-18 15:26:44'),
(51, 'Löftadalen', '2019-10-18 15:26:44'),
(52, 'Mölndal', '2019-10-18 15:26:44'),
(53, 'Mölnlycke', '2019-10-18 15:26:44'),
(54, 'Onsala', '2019-10-18 15:26:44'),
(55, 'Partille', '2019-10-18 15:26:44'),
(56, 'Rävlanda-Hindås', '2019-10-18 15:26:44'),
(58, 'Torslanda', '2019-10-18 15:26:44'),
(59, 'Öckerö', '2019-10-18 15:26:44'),
(60, 'Falkenberg', '2019-10-18 15:26:44'),
(61, 'HK Vandraren', '2019-10-18 15:26:44'),
(62, 'Halmstad', '2019-10-18 15:26:44'),
(63, 'Knäred', '2019-10-18 15:26:44'),
(64, 'Laholm', '2019-10-18 15:26:44'),
(65, 'Nissadalen', '2019-10-18 15:26:44'),
(66, 'Simlångsdalen', '2019-10-18 15:26:44'),
(67, 'Torup', '2019-10-18 15:26:44'),
(68, 'Getinge-Treklövern', '2019-10-18 15:26:44'),
(69, 'Hylte', '2019-10-18 15:26:44'),
(70, 'Varberg', '2019-10-18 15:26:44'),
(71, 'Viske', '2019-10-18 15:26:44'),
(72, 'Arbrå', '2019-10-18 15:26:44'),
(73, 'Bollnäs', '2019-10-18 15:26:44'),
(74, 'Delsbo', '2019-10-18 15:26:44'),
(75, 'Edsbyn', '2019-10-18 15:26:44'),
(76, 'Forsa', '2019-10-18 15:26:44'),
(77, 'Hudiksvall', '2019-10-18 15:26:44'),
(78, 'IBH Nordanstig', '2019-10-18 15:26:44'),
(80, 'Järvsö', '2019-10-18 15:26:44'),
(81, 'Ljusdal', '2019-10-18 15:26:44'),
(82, 'Nordanstig-Östra', '2019-10-18 15:26:44'),
(83, 'Norrbo', '2019-10-18 15:26:44'),
(84, 'Söderhamn', '2019-10-18 15:26:44'),
(85, 'Vallsta', '2019-10-18 15:26:44'),
(87, 'Frösön', '2019-10-18 15:26:44'),
(88, 'Härjedalen', '2019-10-18 15:26:44'),
(90, 'Kälarne', '2019-10-18 15:26:44'),
(91, 'Odensala-Torvalla', '2019-10-18 15:26:44'),
(92, 'Oviken', '2019-10-18 15:26:44'),
(93, 'Revsund', '2019-10-18 15:26:44'),
(94, 'Åredalen', '2019-10-18 15:26:44'),
(95, 'Östersund', '2019-10-18 15:26:44'),
(96, 'Alnö', '2019-10-18 15:26:44'),
(97, 'Hässjö', '2019-10-18 15:26:44'),
(98, 'Matfors', '2019-10-18 15:26:44'),
(99, 'Njurunda', '2019-10-18 15:26:44'),
(100, 'Stöde', '2019-10-18 15:26:44'),
(101, 'Sundsvall', '2019-10-18 15:26:44'),
(102, 'Timrå', '2019-10-18 15:26:44'),
(103, 'Torp', '2019-10-18 15:26:44'),
(104, 'Ånge', '2019-10-18 15:26:44'),
(105, 'Arjeplog', '2019-10-18 15:26:44'),
(106, 'Arvidsjaur', '2019-10-18 15:26:44'),
(107, 'Boden', '2019-10-18 15:26:44'),
(108, 'Gällivare-Malmberget', '2019-10-18 15:26:44'),
(109, 'Haparanda', '2019-10-18 15:26:44'),
(110, 'Kalix', '2019-10-18 15:26:44'),
(111, 'Karungi', '2019-10-18 15:26:44'),
(112, 'Kiruna', '2019-10-18 15:26:44'),
(113, 'Luleå', '2019-10-18 15:26:44'),
(114, 'Pajala', '2019-10-18 15:26:44'),
(115, 'Pite Älvdal', '2019-10-18 15:26:44'),
(116, 'Råneå', '2019-10-18 15:26:44'),
(117, 'Älvsbyn', '2019-10-18 15:26:44'),
(118, 'Överkalix', '2019-10-18 15:26:44'),
(119, 'Övertorneå', '2019-10-18 15:26:44'),
(120, 'Hällefors', '2019-10-18 15:26:44'),
(121, 'Karlskoga', '2019-10-18 15:26:44'),
(122, 'Kumla', '2019-10-18 15:26:44'),
(123, 'Lindesberg', '2019-10-18 15:26:44'),
(124, 'Örebro', '2019-10-18 15:26:44'),
(125, 'Utlandsavdelning', '2019-10-18 15:26:44'),
(126, 'Barabygden', '2019-10-18 15:26:44'),
(127, 'Bjerred-Lomma', '2019-10-18 15:26:44'),
(128, 'Blentarp-Sövde', '2019-10-18 15:26:44'),
(129, 'Eslöv', '2019-10-18 15:26:44'),
(130, 'Genarp', '2019-10-18 15:26:44'),
(131, 'Helsingborg', '2019-10-18 15:26:44'),
(132, 'Husie', '2019-10-18 15:26:44'),
(133, 'Höör-Hörby', '2019-10-18 15:26:44'),
(134, 'Österlen', '2019-10-18 15:26:44'),
(135, 'Klippan-Ljungbyhed', '2019-10-18 15:26:44'),
(136, 'Kristianstad', '2019-10-18 15:26:44'),
(137, 'Landskrona', '2019-10-18 15:26:44'),
(138, 'Lund', '2019-10-18 15:26:44'),
(139, 'Lödde-Kävlinge', '2019-10-18 15:26:44'),
(140, 'Malmö', '2019-10-18 15:26:44'),
(141, 'Osby', '2019-10-18 15:26:44'),
(142, 'Perstorp', '2019-10-18 15:26:44'),
(143, 'Sjöbo', '2019-10-18 15:26:44'),
(144, 'Skurup', '2019-10-18 15:26:44'),
(145, 'Skåne-Tranås', '2019-10-18 15:26:44'),
(146, 'Staffanstorp', '2019-10-18 15:26:44'),
(147, 'Svedala', '2019-10-18 15:26:44'),
(148, 'Söderåsen', '2019-10-18 15:26:44'),
(149, 'Veberöd', '2019-10-18 15:26:44'),
(150, 'Ystad', '2019-10-18 15:26:44'),
(151, 'Ängelholm-Båstad', '2019-10-18 15:26:44'),
(152, 'Åkarp', '2019-10-18 15:26:44'),
(153, 'Åstorp', '2019-10-18 15:26:44'),
(155, 'Alvesta', '2019-10-18 15:26:44'),
(156, 'Aneby', '2019-10-18 15:26:44'),
(157, 'Braås', '2019-10-18 15:26:44'),
(158, 'Eksjö', '2019-10-18 15:26:44'),
(159, 'Gemla', '2019-10-18 15:26:44'),
(160, 'Gnosjö', '2019-10-18 15:26:44'),
(161, 'Ingelstad', '2019-10-18 15:26:44'),
(162, 'Jönköping', '2019-10-18 15:26:44'),
(163, 'Kalmar', '2019-10-18 15:26:44'),
(164, 'Lessebo-Hovmantorp', '2019-10-18 15:26:44'),
(165, 'Lindås', '2019-10-18 15:26:44'),
(166, 'Ljungby', '2019-10-18 15:26:44'),
(167, 'Mörlunda-Vimmerby', '2019-10-18 15:26:44'),
(168, 'Norrhult', '2019-10-18 15:26:44'),
(169, 'Nybro', '2019-10-18 15:26:44'),
(170, 'Nässjö', '2019-10-18 15:26:44'),
(171, 'Oskarshamn', '2019-10-18 15:26:44'),
(172, 'Sävsjö', '2019-10-18 15:26:44'),
(173, 'Öland', '2019-10-18 15:26:44'),
(174, 'Torsås', '2019-10-18 15:26:44'),
(175, 'Tranås ', '2019-10-18 15:26:44'),
(176, 'Vetlanda', '2019-10-18 15:26:44'),
(177, 'Värnamo', '2019-10-18 15:26:44'),
(178, 'Västbo', '2019-10-18 15:26:44'),
(179, 'Västervik', '2019-10-18 15:26:44'),
(180, 'Växjö', '2019-10-18 15:26:44'),
(181, 'Öxnegården', '2019-10-18 15:26:44'),
(182, 'Boo', '2019-10-18 15:26:44'),
(183, 'Botkyrka', '2019-10-18 15:26:44'),
(184, 'Djursholm', '2019-10-18 15:26:44'),
(185, 'Enskede', '2019-10-18 15:26:44'),
(186, 'Haninge', '2019-10-18 15:26:44'),
(187, 'Huddinge', '2019-10-18 15:26:44'),
(188, 'Hägersten-Skärholmen', '2019-10-18 15:26:44'),
(189, 'Järfälla', '2019-10-18 15:26:44'),
(190, 'Kungsängen-Bro', '2019-10-18 15:26:44'),
(191, 'Lidingö', '2019-10-18 15:26:44'),
(192, 'Mälaröarna', '2019-10-18 15:26:44'),
(193, 'Nacka-Saltsjöbaden', '2019-10-18 15:26:44'),
(194, 'Norra Järva', '2019-10-18 15:26:44'),
(195, 'Norrviken', '2019-10-18 15:26:44'),
(196, 'Nynäshamn', '2019-10-18 15:26:44'),
(197, 'Salem', '2019-10-18 15:26:44'),
(198, 'Sollentuna', '2019-10-18 15:26:44'),
(199, 'Solna-Sundbyberg', '2019-10-18 15:26:44'),
(200, 'Stockholm', '2019-10-18 15:26:44'),
(202, 'Södra Roslagen', '2019-10-18 15:26:44'),
(203, 'Tyresö', '2019-10-18 15:26:44'),
(204, 'Värmdö', '2019-10-18 15:26:44'),
(205, 'Västerort', '2019-10-18 15:26:44'),
(206, 'Älta', '2019-10-18 15:26:44'),
(207, 'Österåker', '2019-10-18 15:26:44'),
(208, 'Björnlunda-Gnesta', '2019-10-18 15:26:44'),
(209, 'Eskilstuna', '2019-10-18 15:26:44'),
(210, 'Flen', '2019-10-18 15:26:44'),
(211, 'Katrineholm', '2019-10-18 15:26:44'),
(212, 'Mariefred', '2019-10-18 15:26:44'),
(213, 'Nyköping', '2019-10-18 15:26:44'),
(214, 'Oxelösund', '2019-10-18 15:26:44'),
(215, 'Strängnäs', '2019-10-18 15:26:44'),
(216, 'Södertälje', '2019-10-18 15:26:44'),
(217, 'Vingåker', '2019-10-18 15:26:44'),
(219, 'Almunge-Länna', '2019-10-18 15:26:44'),
(220, 'Björklinge', '2019-10-18 15:26:44'),
(221, 'Bålsta', '2019-10-18 15:26:44'),
(222, 'Enköping', '2019-10-18 15:26:44'),
(223, 'Knivsta', '2019-10-18 15:26:44'),
(224, 'Långhundra', '2019-10-18 15:26:44'),
(225, 'Märsta-Sigtuna', '2019-10-18 15:26:44'),
(226, 'Norrtälje', '2019-10-18 15:26:44'),
(227, 'Rimbo', '2019-10-18 15:26:44'),
(228, 'Skutskär-Älvkarleby', '2019-10-18 15:26:44'),
(229, 'Storvreta', '2019-10-18 15:26:44'),
(230, 'Tierp', '2019-10-18 15:26:44'),
(231, 'Upplands Väsby', '2019-10-18 15:26:44'),
(232, 'Uppsala', '2019-10-18 15:26:44'),
(233, 'Vittinge-Morgongåva', '2019-10-18 15:26:44'),
(234, 'Vänge', '2019-10-18 15:26:44'),
(235, 'Örbyhus', '2019-10-18 15:26:44'),
(236, 'Arvika-Sunne', '2019-10-18 15:26:44'),
(237, 'Ekshärad', '2019-10-18 15:26:44'),
(238, 'Filipstad', '2019-10-18 15:26:44'),
(239, 'Hagfors', '2019-10-18 15:26:44'),
(240, 'Hammarö', '2019-10-18 15:26:44'),
(241, 'Karlstad', '2019-10-18 15:26:44'),
(242, 'Kil', '2019-10-18 15:26:44'),
(243, 'Kristinehamn', '2019-10-18 15:26:44'),
(245, 'Årjäng', '2019-10-18 15:26:44'),
(246, 'Bastuträsk', '2019-10-18 15:26:44'),
(248, 'Bjurholm', '2019-10-18 15:26:44'),
(250, 'Burträsk', '2019-10-18 15:26:44'),
(251, 'Bygdsiljum', '2019-10-18 15:26:44'),
(252, 'Dorotea', '2019-10-18 15:26:44'),
(253, 'Frostkåge', '2019-10-18 15:26:44'),
(254, 'Gafsele', '2019-10-18 15:26:44'),
(255, 'Gunnarn', '2019-10-18 15:26:44'),
(256, 'Hjoggböle', '2019-10-18 15:26:44'),
(257, 'Kågedalen', '2019-10-18 15:26:44'),
(258, 'Ljusvattnet', '2019-10-18 15:26:44'),
(259, 'Lycksele', '2019-10-18 15:26:44'),
(260, 'Lövånger', '2019-10-18 15:26:44'),
(261, 'Malå', '2019-10-18 15:26:44'),
(262, 'Nordmaling', '2019-10-18 15:26:44'),
(263, 'Norsjö', '2019-10-18 15:26:44'),
(264, 'Rusele', '2019-10-18 15:26:44'),
(265, 'Skelleftehamn', '2019-10-18 15:26:44'),
(266, 'Skellefteå', '2019-10-18 15:26:44'),
(267, 'Storbrännan', '2019-10-18 15:26:44'),
(268, 'Storuman', '2019-10-18 15:26:44'),
(269, 'Umeå', '2019-10-18 15:26:44'),
(270, 'Varuträsk', '2019-10-18 15:26:44'),
(271, 'Vindeln', '2019-10-18 15:26:44'),
(272, 'Vännäs', '2019-10-18 15:26:44'),
(273, 'Västerbotten', '2019-10-18 15:26:44'),
(274, 'Åsele', '2019-10-18 15:26:44'),
(275, 'Åskilje', '2019-10-18 15:26:44'),
(276, 'Överklinten', '2019-10-18 15:26:44'),
(277, 'Övre Kågedalen', '2019-10-18 15:26:44'),
(278, 'Alingsås', '2019-10-18 15:26:44'),
(279, 'Borås', '2019-10-18 15:26:44'),
(280, 'Falköping', '2019-10-18 15:26:44'),
(281, 'Gullspång', '2019-10-18 15:26:44'),
(282, 'Herrljunga', '2019-10-18 15:26:44'),
(283, 'Hjo', '2019-10-18 15:26:44'),
(284, 'Karlsborg', '2019-10-18 15:26:44'),
(285, 'Kinnekulle', '2019-10-18 15:26:44'),
(286, 'Lidköping', '2019-10-18 15:26:44'),
(287, 'Mariestad', '2019-10-18 15:26:44'),
(288, 'Mark', '2019-10-18 15:26:44'),
(289, 'Mullsjö', '2019-10-18 15:26:44'),
(291, 'Skövde', '2019-10-18 15:26:44'),
(292, 'Södra Kind', '2019-10-18 15:26:44'),
(293, 'Trollhättan', '2019-10-18 15:26:44'),
(294, 'Ulricehamn', '2019-10-18 15:26:44'),
(295, 'Vara', '2019-10-18 15:26:44'),
(296, 'Vänersborg', '2019-10-18 15:26:44'),
(297, 'Arboga', '2019-10-18 15:26:44'),
(298, 'Fagersta', '2019-10-18 15:26:44'),
(299, 'Hallstahammar', '2019-10-18 15:26:44'),
(300, 'Kungsör', '2019-10-18 15:26:44'),
(301, 'Sala', '2019-10-18 15:26:44'),
(302, 'Skinnskatteberg', '2019-10-18 15:26:44'),
(303, 'Västerås', '2019-10-18 15:26:44'),
(304, 'Östervåla', '2019-10-18 15:26:44'),
(305, 'Fjällgruppen', '2019-10-18 15:26:44'),
(306, 'Bollstabruk', '2019-10-18 15:26:44'),
(307, 'Härnösand', '2019-10-18 15:26:44'),
(308, 'Höga Kusten', '2019-10-18 15:26:44'),
(309, 'Högsjö', '2019-10-18 15:26:44'),
(310, 'Nyland', '2019-10-18 15:26:44'),
(311, 'Sollefteå', '2019-10-18 15:26:44'),
(312, 'Örnsköldsvik', '2019-10-18 15:26:44'),
(313, 'Borensberg', '2019-10-18 15:26:44'),
(314, 'Finspång', '2019-10-18 15:26:44'),
(315, 'Jursla-Åby', '2019-10-18 15:26:44'),
(316, 'Kolmården', '2019-10-18 15:26:44'),
(317, 'Linköping', '2019-10-18 15:26:44'),
(318, 'Mantorp', '2019-10-18 15:26:44'),
(319, 'Motala', '2019-10-18 15:26:44'),
(320, 'Norrköping', '2019-10-18 15:26:44'),
(321, 'Kinda', '2019-10-18 15:26:44'),
(322, 'Söderköping', '2019-10-18 15:26:44'),
(323, 'Trehörna', '2019-10-18 15:26:44'),
(324, 'Vadstena', '2019-10-18 15:26:44'),
(325, 'Valdemarsvik', '2019-10-18 15:26:44'),
(326, 'Ydre', '2019-10-18 15:26:44'),
(327, 'Sjöstaden-Sickla', '2019-10-18 15:26:44'),
(328, 'Västerdalarna', '2019-10-18 15:26:44'),
(329, 'Surahammar', '2019-10-18 15:26:44'),
(555, 'Schweiz', '2019-10-18 15:26:44'),
(794, 'Damljunga', '2019-10-18 15:26:44');

-- --------------------------------------------------------

--
-- Tabellstruktur `section_admins`
--

CREATE TABLE `section_admins` (
  `sectionId` int(10) UNSIGNED NOT NULL,
  `userId` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci COMMENT='assignments which shall give admin access to section (in addition to cfg setting)';

--
-- RELATIONER FÖR TABELL `section_admins`:
--   `sectionId`
--       `sections` -> `sectionId`
--   `userId`
--       `users` -> `userId`
--

-- --------------------------------------------------------

--
-- Tabellstruktur `tokens`
--

CREATE TABLE `tokens` (
  `token` varchar(40) COLLATE utf8_swedish_ci NOT NULL COMMENT 'SHA1-hashed',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ttl` mediumint(8) UNSIGNED NOT NULL DEFAULT '86400',
  `useFor` varchar(255) COLLATE utf8_swedish_ci NOT NULL,
  `forId` int(10) UNSIGNED NOT NULL,
  `data` varchar(255) COLLATE utf8_swedish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci COMMENT='used for password-less one time actions';

--
-- RELATIONER FÖR TABELL `tokens`:
--

-- --------------------------------------------------------

--
-- Tabellstruktur `users`
--

CREATE TABLE `users` (
  `userId` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `name` varchar(100) COLLATE utf8_swedish_ci NOT NULL,
  `mail` varchar(100) COLLATE utf8_swedish_ci NOT NULL,
  `phone` varchar(15) COLLATE utf8_swedish_ci NOT NULL,
  `sectionId` int(10) UNSIGNED NOT NULL,
  `lastLogin` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci COMMENT='GDPR sensitive user data which we do not get from API';

--
-- RELATIONER FÖR TABELL `users`:
--

--
-- Index för dumpade tabeller
--

--
-- Index för tabell `booked_items`
--
ALTER TABLE `booked_items`
  ADD PRIMARY KEY (`bookedItemId`),
  ADD KEY `itemId` (`itemId`),
  ADD KEY `bookingId` (`bookingId`);

--
-- Index för tabell `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`bookingId`),
  ADD KEY `userId` (`userId`) USING BTREE,
  ADD KEY `sectionId` (`sectionId`);

--
-- Index för tabell `booking_answers`
--
ALTER TABLE `booking_answers`
  ADD PRIMARY KEY (`answerId`),
  ADD KEY `bookingId` (`bookingId`);

--
-- Index för tabell `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`catId`) USING BTREE,
  ADD KEY `parentId` (`parentId`) USING BTREE,
  ADD KEY `sectionId` (`sectionId`) USING BTREE,
  ADD KEY `contactUserId` (`contactUserId`);

--
-- Index för tabell `cat_admins`
--
ALTER TABLE `cat_admins`
  ADD UNIQUE KEY `catId` (`catId`,`userId`),
  ADD KEY `userId` (`userId`);

--
-- Index för tabell `cat_admin_noalert`
--
ALTER TABLE `cat_admin_noalert`
  ADD UNIQUE KEY `user_cat` (`userId`,`catId`),
  ADD KEY `catId` (`catId`);

--
-- Index för tabell `cat_questions`
--
ALTER TABLE `cat_questions`
  ADD UNIQUE KEY `questionId` (`questionId`,`catId`),
  ADD KEY `catId` (`catId`);

--
-- Index för tabell `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`itemId`),
  ADD KEY `imageId` (`imageId`) USING BTREE,
  ADD KEY `catId` (`catId`) USING BTREE;

--
-- Index för tabell `item_images`
--
ALTER TABLE `item_images`
  ADD PRIMARY KEY (`imageId`),
  ADD KEY `itemId` (`itemId`) USING BTREE;

--
-- Index för tabell `persistent_logins`
--
ALTER TABLE `persistent_logins`
  ADD KEY `userId` (`userId`);

--
-- Index för tabell `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`questionId`),
  ADD KEY `sectionId` (`sectionId`);

--
-- Index för tabell `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`sectionId`);

--
-- Index för tabell `section_admins`
--
ALTER TABLE `section_admins`
  ADD UNIQUE KEY `sectionId` (`sectionId`,`userId`),
  ADD KEY `section_admins_ibfk_1` (`userId`);

--
-- Index för tabell `tokens`
--
ALTER TABLE `tokens`
  ADD PRIMARY KEY (`token`),
  ADD UNIQUE KEY `usefor` (`useFor`,`forId`,`data`);

--
-- Index för tabell `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userId`);

--
-- AUTO_INCREMENT för dumpade tabeller
--

--
-- AUTO_INCREMENT för tabell `booked_items`
--
ALTER TABLE `booked_items`
  MODIFY `bookedItemId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;
--
-- AUTO_INCREMENT för tabell `bookings`
--
ALTER TABLE `bookings`
  MODIFY `bookingId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;
--
-- AUTO_INCREMENT för tabell `booking_answers`
--
ALTER TABLE `booking_answers`
  MODIFY `answerId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=171;
--
-- AUTO_INCREMENT för tabell `categories`
--
ALTER TABLE `categories`
  MODIFY `catId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;
--
-- AUTO_INCREMENT för tabell `items`
--
ALTER TABLE `items`
  MODIFY `itemId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;
--
-- AUTO_INCREMENT för tabell `item_images`
--
ALTER TABLE `item_images`
  MODIFY `imageId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;
--
-- AUTO_INCREMENT för tabell `questions`
--
ALTER TABLE `questions`
  MODIFY `questionId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
--
-- Restriktioner för dumpade tabeller
--

--
-- Restriktioner för tabell `booked_items`
--
ALTER TABLE `booked_items`
  ADD CONSTRAINT `booked_items_ibfk_1` FOREIGN KEY (`itemId`) REFERENCES `items` (`itemId`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `booked_items_ibfk_2` FOREIGN KEY (`bookingId`) REFERENCES `bookings` (`bookingId`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restriktioner för tabell `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`sectionId`) REFERENCES `sections` (`sectionId`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restriktioner för tabell `booking_answers`
--
ALTER TABLE `booking_answers`
  ADD CONSTRAINT `booking_answers_ibfk_1` FOREIGN KEY (`bookingId`) REFERENCES `bookings` (`bookingId`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restriktioner för tabell `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parentId`) REFERENCES `categories` (`catId`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `categories_ibfk_2` FOREIGN KEY (`sectionId`) REFERENCES `sections` (`sectionId`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `categories_ibfk_3` FOREIGN KEY (`contactUserId`) REFERENCES `users` (`userId`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restriktioner för tabell `cat_admins`
--
ALTER TABLE `cat_admins`
  ADD CONSTRAINT `cat_admins_ibfk_1` FOREIGN KEY (`catId`) REFERENCES `categories` (`catId`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `cat_admins_ibfk_2` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restriktioner för tabell `cat_admin_noalert`
--
ALTER TABLE `cat_admin_noalert`
  ADD CONSTRAINT `cat_admin_noalert_ibfk_1` FOREIGN KEY (`catId`) REFERENCES `categories` (`catId`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `cat_admin_noalert_ibfk_2` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restriktioner för tabell `cat_questions`
--
ALTER TABLE `cat_questions`
  ADD CONSTRAINT `cat_questions_ibfk_1` FOREIGN KEY (`catId`) REFERENCES `categories` (`catId`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `cat_questions_ibfk_2` FOREIGN KEY (`questionId`) REFERENCES `questions` (`questionId`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restriktioner för tabell `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`catId`) REFERENCES `categories` (`catId`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `items_ibfk_2` FOREIGN KEY (`imageId`) REFERENCES `item_images` (`imageId`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restriktioner för tabell `item_images`
--
ALTER TABLE `item_images`
  ADD CONSTRAINT `item_images_ibfk_1` FOREIGN KEY (`itemId`) REFERENCES `items` (`itemId`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restriktioner för tabell `persistent_logins`
--
ALTER TABLE `persistent_logins`
  ADD CONSTRAINT `persistent_logins_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restriktioner för tabell `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`sectionId`) REFERENCES `sections` (`sectionId`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restriktioner för tabell `section_admins`
--
ALTER TABLE `section_admins`
  ADD CONSTRAINT `section_admins_ibfk_1` FOREIGN KEY (`sectionId`) REFERENCES `sections` (`sectionId`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `section_admins_ibfk_2` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`) ON DELETE CASCADE ON UPDATE CASCADE;

