-- ------------------------------
-- Структура базы данных 3WiFi --
-- ------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- Дамп структуры базы данных 3wifi_tk
CREATE DATABASE IF NOT EXISTS `3wifi_tk` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `3wifi_tk`;


-- Дамп структуры для таблица 3wifi_tk.free
CREATE TABLE IF NOT EXISTS `free` (
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
  `WPSPIN` tinytext NOT NULL,
  `LANIP` varchar(15) NOT NULL,
  `LANMask` varchar(15) NOT NULL,
  `WANIP` varchar(15) NOT NULL,
  `WANMask` varchar(15) NOT NULL,
  `WANGateway` varchar(15) NOT NULL,
  `DNS` varchar(50) NOT NULL,
  `latitude` varchar(11) NOT NULL DEFAULT 'none',
  `longitude` varchar(11) NOT NULL DEFAULT 'none',
  PRIMARY KEY (`BSSID`,`ESSID`,`WiFiKey`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
