-- ------------------------------
-- Структура базы данных 3WiFi --
-- ------------------------------

-- Дамп структуры базы данных 3wifi
CREATE DATABASE IF NOT EXISTS `3wifi` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `3wifi`;

-- Дамп структуры таблицы 3wifi.free
CREATE TABLE IF NOT EXISTS `free` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `comment` tinytext NOT NULL,
  `IP` varchar(15) NOT NULL,
  `Port` varchar(5) NOT NULL,
  `Authorization` tinytext NOT NULL,
  `name` tinytext NOT NULL,
  `RadioOff` varchar(5) NOT NULL,
  `Hidden` varchar(5) NOT NULL,
  `BSSID` char(17) NOT NULL,
  `ESSID` varchar(32) NOT NULL,
  `Security` varchar(20) NOT NULL,
  `WiFiKey` varchar(64) NOT NULL,
  `WPSPIN` varchar(9) NOT NULL,
  `LANIP` varchar(15) NOT NULL,
  `LANMask` varchar(15) NOT NULL,
  `WANIP` varchar(15) NOT NULL,
  `WANMask` varchar(15) NOT NULL,
  `WANGateway` varchar(15) NOT NULL,
  `DNS` varchar(50) NOT NULL,
  `latitude` varchar(11) NOT NULL DEFAULT 'none',
  `longitude` varchar(11) NOT NULL DEFAULT 'none',
  PRIMARY KEY (`id`),
  UNIQUE KEY `WIFI` (`BSSID`,`ESSID`,`WiFiKey`,`WPSPIN`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Дамп структуры таблицы 3wifi.ranges
CREATE TABLE IF NOT EXISTS `ranges` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `startIP` INT UNSIGNED NOT NULL,
  `endIP` INT UNSIGNED NOT NULL,
  `descr` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `RANGE` (`startIP`,`endIP`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Дамп структуры таблицы 3wifi.tasks
CREATE TABLE IF NOT EXISTS `tasks` (
  `tid` CHAR(32) NOT NULL,
  `tstate` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created` TIMESTAMP NOT NULL DEFAULT 0,
  `modified` TIMESTAMP NOT NULL DEFAULT 0 ON UPDATE CURRENT_TIMESTAMP,
  `ext` CHAR(4) NOT NULL,
  `comment` TINYTEXT NOT NULL,
  `checkexist` BIT(1) NOT NULL,
  `lines` INT UNSIGNED NOT NULL DEFAULT 0,
  `accepted` INT UNSIGNED NOT NULL DEFAULT 0,
  `onmap` INT UNSIGNED NOT NULL DEFAULT 0,
  `warns` TEXT NOT NULL,
  PRIMARY KEY (`tid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;