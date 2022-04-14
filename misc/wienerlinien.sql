-- phpMyAdmin SQL Dump
-- version 4.0.5
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Sep 03, 2013 at 01:10 AM
-- Server version: 5.5.31-0+wheezy1
-- PHP Version: 5.4.4-14+deb7u4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `wienerlinien_dev`
--

-- --------------------------------------------------------

--
-- Table structure for table `line`
--

CREATE TABLE IF NOT EXISTS `line` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `type` int(11) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  `timestamp_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `timestamp_deleted` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id` (`id`,`type`),
  KEY `type` (`type`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;

--
-- Dumping data for table `line`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `wl_line`
--

CREATE TABLE `wl_line` (
  `id` int(11) NOT NULL,
  `line` int(11) NOT NULL,
  `wl_id` int(11) NOT NULL,
  `wl_order` int(11) NOT NULL,
  `realtime` tinyint(4) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `timestamp_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `timestamp_deleted` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `wl_station`
--

CREATE TABLE `wl_station` (
  `id` int(11) NOT NULL,
  `station` int(11) NOT NULL,
  `wl_id` int(11) DEFAULT NULL,
  `wl_diva` int(11) DEFAULT NULL,
  `wl_lat` decimal(17,15) DEFAULT NULL,
  `wl_lon` decimal(17,15) DEFAULT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `timestamp_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `timestamp_deleted` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `line_color`
--

CREATE TABLE IF NOT EXISTS `line_color` (
  `line` int(11) NOT NULL,
  `color` varchar(6) NOT NULL,
  PRIMARY KEY (`line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `line_color`
--

-- --------------------------------------------------------

--
-- Table structure for table `line_segment`
--

CREATE TABLE IF NOT EXISTS `line_segment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `segment` int(11) NOT NULL,
  `line` int(11) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  `timestamp_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `timestamp_deleted` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `segment` (`segment`),
  KEY `line_segment_ibfk_2` (`line`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;

--
-- Dumping data for table `line_segment`
--

-- --------------------------------------------------------

--
-- Table structure for table `line_station`
--

CREATE TABLE IF NOT EXISTS `line_station` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `line` int(11) NOT NULL,
  `station` int(11) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  `timestamp_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `timestamp_deleted` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `line` (`line`),
  KEY `line_station_ibfk_2` (`station`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;

--
-- Dumping data for table `line_station`
--

-- --------------------------------------------------------

--
-- Table structure for table `line_type`
--

CREATE TABLE IF NOT EXISTS `line_type` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `color` varchar(6) NOT NULL,
  `line_thickness` int(11) NOT NULL,
  `pos` int(11) NOT NULL,
  `wl_name` varchar(20) DEFAULT NULL,
  `name_pattern` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pos` (`pos`),
  UNIQUE KEY `wl_name` (`wl_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `line_type`
--

INSERT INTO `line_type` (`id`, `name`, `color`, `line_thickness`, `pos`, `wl_name`, `name_pattern`) VALUES
(1, 'Straßenbahn', 'ff0000', 2, 2, 'ptTram', '/^([1-9][0-9]?|[A-Z])$/'),
(2, 'Autobus', '0000ff', 2, 4, 'ptBusCity', '/^[1-9][0-9]*[A-Z]$/'),
(3, 'Regionalbus', '00ab00', 2, 5, NULL, '/^[1-9][0-9][0-9]$/'),
(4, 'U-Bahn', 'ff0000', 4, 1, 'ptMetro', '/^U[1-9][0-9]*$/'),
(5, 'ÖBB', '000000', 4, 8, NULL, NULL),
(6, 'Badner Bahn', '003562', 3, 3, 'ptTramWLB', '/^WLB$/'),
(7, 'S-Bahn', '009ddc', 4, 7, 'ptTrainS', '/^S[1-9][0-9]*$/'),
(8, 'NightLine', '191364', 2, 6, 'ptBusNight', '/^N[1-9][0-9]*$/');

-- --------------------------------------------------------

--
-- Table structure for table `municipality`
--

CREATE TABLE IF NOT EXISTS `municipality` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wl_id` int(11) NOT NULL,
  `name` text NOT NULL,
  `timestamp_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;

--
-- Dumping data for table `municipality`
--

-- --------------------------------------------------------

--
-- Table structure for table `segment`
--

CREATE TABLE IF NOT EXISTS `segment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `point1` int(11) NOT NULL,
  `point2` int(11) NOT NULL,
  `timestamp_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `point1_2` (`point1`,`point2`),
  KEY `point1` (`point1`),
  KEY `point2` (`point2`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;

--
-- Dumping data for table `segment`
--

-- --------------------------------------------------------

--
-- Table structure for table `segment_point`
--

CREATE TABLE IF NOT EXISTS `segment_point` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lat` decimal(17,15) NOT NULL,
  `lon` decimal(17,15) NOT NULL,
  `timestamp_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lat` (`lat`,`lon`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;

--
-- Dumping data for table `segment_point`
--

-- --------------------------------------------------------

--
-- Table structure for table `station`
--

CREATE TABLE IF NOT EXISTS `station` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `short_name` text,
  `station_id` int(11) DEFAULT NULL,
  `lat` decimal(17,15) DEFAULT NULL,
  `lon` decimal(17,15) DEFAULT NULL,
  `municipality` int(11) NOT NULL,
  `wl_id` int(11) DEFAULT NULL,
  `wl_diva` int(11) DEFAULT NULL,
  `wl_lat` decimal(17,15) DEFAULT NULL,
  `wl_lon` decimal(17,15) DEFAULT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  `timestamp_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `timestamp_deleted` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `station_id` (`station_id`),
  KEY `municipality` (`municipality`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;

--
-- Dumping data for table `station`
--

-- --------------------------------------------------------

--
-- Table structure for table `station_id`
--

CREATE TABLE IF NOT EXISTS `station_id` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `lat` decimal(17,15) NOT NULL,
  `lon` decimal(17,15) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  `timestamp_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `timestamp_deleted` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `station_id`
--

-- --------------------------------------------------------

--
-- Table structure for table `traffic_info`
--

CREATE TABLE IF NOT EXISTS `traffic_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wl_id` varchar(100) NOT NULL,
  `timestamp_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `timestamp_deleted` timestamp NULL DEFAULT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  `category` int(11) NOT NULL,
  `priority` int(11) DEFAULT NULL,
  `owner` text,
  `title` text NOT NULL,
  `description` text NOT NULL,
  `last_description` text NOT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `resume_time` datetime DEFAULT NULL,
  `group` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category` (`category`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;

--
-- Dumping data for table `traffic_info`
--

-- --------------------------------------------------------

--
-- Table structure for table `traffic_info_category`
--

CREATE TABLE IF NOT EXISTS `traffic_info_category` (
  `id` int(11) NOT NULL,
  `group` int(11) NOT NULL,
  `name` text NOT NULL,
  `title` text NOT NULL,
  `short_name` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `traffic_info_category`
--

-- --------------------------------------------------------

--
-- Table structure for table `traffic_info_category_group`
--

CREATE TABLE IF NOT EXISTS `traffic_info_category_group` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `traffic_info_category_group`
--

-- --------------------------------------------------------

--
-- Table structure for table `traffic_info_elevator`
--

CREATE TABLE IF NOT EXISTS `traffic_info_elevator` (
  `id` int(11) NOT NULL,
  `reason` text,
  `location` text,
  `station` text,
  `status` text,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `towards` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `traffic_info_elevator`
--

-- --------------------------------------------------------

--
-- Table structure for table `traffic_info_line`
--

CREATE TABLE IF NOT EXISTS `traffic_info_line` (
  `traffic_info` int(11) NOT NULL,
  `line` int(11) NOT NULL,
  PRIMARY KEY (`traffic_info`,`line`),
  KEY `traffic_info` (`traffic_info`,`line`),
  KEY `line` (`line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `traffic_info_line`
--

-- --------------------------------------------------------

--
-- Table structure for table `traffic_info_platform`
--

CREATE TABLE IF NOT EXISTS `traffic_info_platform` (
  `traffic_info` int(11) NOT NULL,
  `platform` int(11) NOT NULL,
  PRIMARY KEY (`traffic_info`,`platform`),
  KEY `traffic_info` (`traffic_info`),
  KEY `platform` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `traffic_info_platform`
--

-- --------------------------------------------------------

--
-- Table structure for table `wl_platform`
--

CREATE TABLE IF NOT EXISTS `wl_platform` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `station` int(11) NOT NULL,
  `line` int(11) NOT NULL,
  `wl_id` int(11) NOT NULL,
  `direction` tinyint(4) NOT NULL,
  `pos` int(11) NOT NULL,
  `rbl` int(11) NULL,
  `area` text NOT NULL,
  `Platform` text NOT NULL,
  `lat` decimal(17,15) NOT NULL,
  `lon` decimal(17,15) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  `timestamp_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `timestamp_deleted` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `station` (`station`,`line`),
  KEY `station_2` (`station`),
  KEY `line` (`line`),
  KEY `rbl` (`rbl`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;

--
-- Dumping data for table `wl_platform`
--

-- --------------------------------------------------------

-- --------------------------------------------------------

--
-- Table structure for table `data_problem`
--

CREATE TABLE IF NOT EXISTS `data_problem` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `description` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

create index `idx_data_problem_timestamp` on data_problem(timestamp);

--
-- Table structure for table `log`
--

CREATE TABLE IF NOT EXISTS `log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `text` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `log`
--

-- --------------------------------------------------------

--
-- Table structure for table `wl_platform_keep`
--

CREATE TABLE IF NOT EXISTS `wl_platform_keep` (
  `id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `rbl_request`
--

CREATE TABLE `rbl_request` (
	  `id` int(11) NOT NULL,
	  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `rbl_request_item`
--

CREATE TABLE `rbl_request_item` (
	  `request_id` int(11) DEFAULT NULL,
	  `item` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `wl_platform_keep`
--

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE IF NOT EXISTS `settings` (
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `wl_line`
--
ALTER TABLE `wl_line`
  ADD PRIMARY KEY (`id`),
  ADD KEY `line` (`line`);

--
-- Indizes für die Tabelle `wl_station`
--
ALTER TABLE `wl_station`
  ADD PRIMARY KEY (`id`),
  ADD KEY `station` (`station`);

--
-- Indizes für die Tabelle `rbl_request`
--
ALTER TABLE `rbl_request`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `rbl_request_item`
--
ALTER TABLE `rbl_request_item`
  ADD KEY `request_id` (`request_id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `wl_line`
--
ALTER TABLE `wl_line`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `wl_station`
--
ALTER TABLE `wl_station`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `rbl_request`
--
ALTER TABLE `rbl_request`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `line`
--
ALTER TABLE `line`
  ADD CONSTRAINT `line_ibfk_1` FOREIGN KEY (`type`) REFERENCES `line_type` (`id`);

--
-- Constraints der Tabelle `wl_line`
--
ALTER TABLE `wl_line`
  ADD CONSTRAINT `wl_line_ibfk_1` FOREIGN KEY (`line`) REFERENCES `line` (`id`);

--
-- Constraints der Tabelle `wl_station`
--
ALTER TABLE `wl_station`
  ADD CONSTRAINT `wl_station_ibfk_1` FOREIGN KEY (`station`) REFERENCES `station` (`id`);

--
-- Constraints for table `line_color`
--
ALTER TABLE `line_color`
  ADD CONSTRAINT `line_color_ibfk_1` FOREIGN KEY (`line`) REFERENCES `line` (`id`);

--
-- Constraints for table `line_segment`
--
ALTER TABLE `line_segment`
  ADD CONSTRAINT `line_segment_ibfk_2` FOREIGN KEY (`line`) REFERENCES `line` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `line_segment_ibfk_1` FOREIGN KEY (`segment`) REFERENCES `segment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `line_station`
--
ALTER TABLE `line_station`
  ADD CONSTRAINT `line_station_ibfk_2` FOREIGN KEY (`station`) REFERENCES `station` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `line_station_ibfk_1` FOREIGN KEY (`line`) REFERENCES `line` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `segment`
--
ALTER TABLE `segment`
  ADD CONSTRAINT `segment_ibfk_2` FOREIGN KEY (`point2`) REFERENCES `segment_point` (`id`),
  ADD CONSTRAINT `segment_ibfk_1` FOREIGN KEY (`point1`) REFERENCES `segment_point` (`id`);

--
-- Constraints for table `station`
--
ALTER TABLE `station`
  ADD CONSTRAINT `station_ibfk_1` FOREIGN KEY (`station_id`) REFERENCES `station_id` (`id`),
  ADD CONSTRAINT `station_ibfk_2` FOREIGN KEY (`municipality`) REFERENCES `municipality` (`id`);

--
-- Constraints for table `traffic_info`
--
ALTER TABLE `traffic_info`
  ADD CONSTRAINT `traffic_info_ibfk_1` FOREIGN KEY (`category`) REFERENCES `traffic_info_category` (`id`);

--
-- Constraints for table `traffic_info_category`
--
ALTER TABLE `traffic_info_category`
  ADD CONSTRAINT `traffic_info_category_ibfk_1` FOREIGN KEY (`group`) REFERENCES `traffic_info_category_group` (`id`);

--
-- Constraints for table `traffic_info_elevator`
--
ALTER TABLE `traffic_info_elevator`
  ADD CONSTRAINT `traffic_info_elevator_ibfk_1` FOREIGN KEY (`id`) REFERENCES `traffic_info` (`id`);

--
-- Constraints for table `traffic_info_line`
--
ALTER TABLE `traffic_info_line`
  ADD CONSTRAINT `traffic_info_line_ibfk_2` FOREIGN KEY (`line`) REFERENCES `line` (`id`),
  ADD CONSTRAINT `traffic_info_line_ibfk_1` FOREIGN KEY (`traffic_info`) REFERENCES `traffic_info` (`id`);

--
-- Constraints for table `traffic_info_platform`
--
ALTER TABLE `traffic_info_platform`
  ADD CONSTRAINT `traffic_info_platform_ibfk_2` FOREIGN KEY (`platform`) REFERENCES `wl_platform` (`id`),
  ADD CONSTRAINT `traffic_info_platform_ibfk_1` FOREIGN KEY (`traffic_info`) REFERENCES `traffic_info` (`id`);

--
-- Constraints for table `wl_platform`
--
ALTER TABLE `wl_platform`
  ADD CONSTRAINT `wl_platform_ibfk_2` FOREIGN KEY (`line`) REFERENCES `line` (`id`),
  ADD CONSTRAINT `wl_platform_ibfk_1` FOREIGN KEY (`station`) REFERENCES `station` (`id`);

--
-- Constraints for table `wl_platform_keep`
--
ALTER TABLE `wl_platform_keep`
  ADD CONSTRAINT `wl_platform_keep_ibfk_1` FOREIGN KEY (`id`) REFERENCES `wl_platform` (`id`);

--
-- Constraints der Tabelle `rbl_request_item`
--
ALTER TABLE `rbl_request_item`
  ADD CONSTRAINT `rbl_request_item_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `rbl_request` (`id`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
