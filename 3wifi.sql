-- ------------------------------
-- Структура базы данных 3WiFi --
-- ------------------------------

-- Дамп структуры базы данных 3wifi
CREATE DATABASE IF NOT EXISTS `3wifi` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `3wifi`;

-- Дамп структуры таблицы 3wifi.base
CREATE TABLE `base` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`cmtid` INT(10) UNSIGNED NULL DEFAULT NULL,
	`IP` INT(10) NULL DEFAULT NULL,
	`Port` SMALLINT(5) UNSIGNED NULL DEFAULT NULL,
	`Authorization` TINYTEXT NULL,
	`name` TINYTEXT NOT NULL,
	`RadioOff` BIT(1) NOT NULL DEFAULT b'0',
	`Hidden` BIT(1) NOT NULL DEFAULT b'0',
	`NoBSSID` TINYINT(3) UNSIGNED NOT NULL,
	`BSSID` BIGINT(15) UNSIGNED NOT NULL,
	`ESSID` VARCHAR(32) NOT NULL,
	`Security` TINYINT(1) UNSIGNED NOT NULL,
	`WiFiKey` VARCHAR(64) NOT NULL,
	`WPSPIN` INT(8) UNSIGNED NOT NULL,
	`LANIP` INT(10) NULL DEFAULT NULL,
	`LANMask` INT(10) NULL DEFAULT NULL,
	`WANIP` INT(10) NULL DEFAULT NULL,
	`WANMask` INT(10) NULL DEFAULT NULL,
	`WANGateway` INT(10) NULL DEFAULT NULL,
	`DNS1` INT(10) NULL DEFAULT NULL,
	`DNS2` INT(10) NULL DEFAULT NULL,
	`DNS3` INT(10) NULL DEFAULT NULL,
	PRIMARY KEY (`id`),
	INDEX `BSSID` (`BSSID`),
	INDEX `ESSID` (`ESSID`),
	INDEX `Time` (`time`),
	UNIQUE INDEX `WIFI` (`NoBSSID`, `BSSID`, `ESSID`, `WiFiKey`, `WPSPIN`)
) COLLATE='utf8_general_ci' ENGINE=InnoDB;

-- Дамп структуры таблицы 3wifi.geo
CREATE TABLE `geo` (
	`BSSID` BIGINT(15) UNSIGNED NOT NULL,
	`latitude` FLOAT(12,8) NULL DEFAULT NULL,
	`longitude` FLOAT(12,8) NULL DEFAULT NULL,
	`quadkey` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
	PRIMARY KEY (`BSSID`),
	INDEX `quadkey` (`quadkey`),
	INDEX `latitude` (`latitude`)
) COLLATE='utf8_general_ci' ENGINE=InnoDB;

-- Дамп структуры для таблицы 3wifi.invites
CREATE TABLE IF NOT EXISTS `invites` (
	`invite` CHAR(12) NOT NULL,
	`puid` INT(11) UNSIGNED NOT NULL,
	`time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`uid` INT(11) UNSIGNED NULL DEFAULT NULL,
	`level` TINYINT(4) NOT NULL DEFAULT '1',
	PRIMARY KEY (`invite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Дамп структуры таблицы 3wifi.comments
CREATE TABLE `comments` (
	`cmtid` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`cmtval` VARCHAR(127) NOT NULL,
	PRIMARY KEY (`cmtid`),
	UNIQUE INDEX `comment` (`cmtval`)
) COLLATE='utf8_general_ci' ENGINE=InnoDB;

-- Дамп структуры таблицы 3wifi.tasks
CREATE TABLE `tasks` (
	`tid` CHAR(32) NOT NULL,
	`uid` INT(11) UNSIGNED NULL DEFAULT NULL,
	`tstate` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
	`created` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
	`modified` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
	`ext` CHAR(4) NOT NULL,
	`comment` TINYTEXT NOT NULL,
	`checkexist` BIT(1) NOT NULL,
	`nowait` BIT(1) NOT NULL,
	`lines` INT(10) UNSIGNED NOT NULL DEFAULT 0,
	`accepted` INT(10) UNSIGNED NOT NULL DEFAULT 0,
	`onmap` INT(10) UNSIGNED NOT NULL DEFAULT 0,
	`warns` TEXT NULL DEFAULT NULL,
	PRIMARY KEY (`tid`),
	INDEX `task_state` (`tstate`),
	INDEX `created_time` (`created`)
)
COLLATE='utf8_general_ci' ENGINE=InnoDB;

-- Дамп структуры таблицы 3wifi.ranges
CREATE TABLE `ranges` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`startIP` INT(10) UNSIGNED NOT NULL,
	`endIP` INT(10) UNSIGNED NOT NULL,
	`netname` TINYTEXT NOT NULL,
	`descr` TINYTEXT NOT NULL,
	`country` CHAR(2) NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE INDEX `RANGE` (`startIP`, `endIP`)
) COLLATE='utf8_general_ci' ENGINE=InnoDB;

-- Дамп структуры таблицы 3wifi.stats
CREATE TABLE `stats` (
	`StatId` INT(15) UNSIGNED NOT NULL,
	`Value` INT(10) UNSIGNED NOT NULL DEFAULT '0',
	`LastUpdate` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`StatId`)
) COLLATE='utf8_general_ci' ENGINE=MEMORY;

-- Дамп структуры таблицы 3wifi.mem_base
CREATE TABLE `mem_base` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`cmtid` INT(10) UNSIGNED NULL DEFAULT NULL,
	`IP` INT(10) NULL DEFAULT NULL,
	`Port` SMALLINT(5) UNSIGNED NULL DEFAULT NULL,
	`Authorization` VARCHAR(64) NULL DEFAULT NULL,
	`name` VARCHAR(64) NOT NULL,
	`RadioOff` BIT(1) NOT NULL DEFAULT b'0',
	`Hidden` BIT(1) NOT NULL DEFAULT b'0',
	`NoBSSID` TINYINT(3) UNSIGNED NOT NULL,
	`BSSID` BIGINT(15) UNSIGNED NOT NULL,
	`ESSID` VARCHAR(32) NOT NULL,
	`Security` TINYINT(1) UNSIGNED NOT NULL,
	`WiFiKey` VARCHAR(64) NOT NULL,
	`WPSPIN` INT(8) UNSIGNED NOT NULL,
	`LANIP` INT(10) NULL DEFAULT NULL,
	`LANMask` INT(10) NULL DEFAULT NULL,
	`WANIP` INT(10) NULL DEFAULT NULL,
	`WANMask` INT(10) NULL DEFAULT NULL,
	`WANGateway` INT(10) NULL DEFAULT NULL,
	`DNS1` INT(10) NULL DEFAULT NULL,
	`DNS2` INT(10) NULL DEFAULT NULL,
	`DNS3` INT(10) NULL DEFAULT NULL,
	PRIMARY KEY (`id`),
	INDEX `BSSID` (`BSSID`),
	INDEX `ESSID` (`ESSID`),
	INDEX `Time` (`time`),
	UNIQUE INDEX `WIFI` (`NoBSSID`, `BSSID`, `ESSID`, `WiFiKey`, `WPSPIN`)
) COLLATE='utf8_general_ci' ENGINE=MEMORY;

-- Дамп структуры таблицы 3wifi.mem_geo
CREATE TABLE `mem_geo` (
	`BSSID` BIGINT(15) UNSIGNED NOT NULL,
	`latitude` FLOAT(12,8) NULL DEFAULT NULL,
	`longitude` FLOAT(12,8) NULL DEFAULT NULL,
	`quadkey` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
	PRIMARY KEY (`BSSID`),
	INDEX `quadkey` (`quadkey`),
	INDEX `latitude` (`latitude`)
) COLLATE='utf8_general_ci' ENGINE=MEMORY;

-- Дамп структуры для таблицы 3wifi.users
CREATE TABLE `users` (
	`uid` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`regdate` TIMESTAMP NULL DEFAULT NULL,
	`lastupdate` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	`puid` INT(11) NOT NULL DEFAULT '0',
	`login` VARCHAR(30) NOT NULL,
	`nick` VARCHAR(30) NOT NULL,
	`pass_hash` CHAR(32) NOT NULL,
	`autologin` CHAR(32) NOT NULL,
	`salt` CHAR(32) NOT NULL,
	`level` TINYINT(4) NOT NULL DEFAULT '0',
	`ip_hash` CHAR(32) NOT NULL,
	`invites` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
	`rapikey` CHAR(32) NULL DEFAULT NULL,
	`wapikey` CHAR(32) NULL DEFAULT NULL,
	`querytime` TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY (`uid`),
	UNIQUE INDEX `login` (`login`),
	UNIQUE INDEX `nick` (`nick`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Дамп структуры для таблицы 3wifi.logauth
CREATE TABLE `logauth` (
	`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`IP` INT(15) UNSIGNED NOT NULL,
	`uid` INT(11) UNSIGNED NULL DEFAULT NULL,
	`action` TINYINT(3) UNSIGNED NOT NULL,
	`data` CHAR(64) NOT NULL DEFAULT '',
	`status` BIT(1) NOT NULL DEFAULT b'0',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Дамп структуры для таблицы 3wifi.uploads
CREATE TABLE `uploads` (
	`uid` INT(10) UNSIGNED NOT NULL,
	`id` INT(10) UNSIGNED NOT NULL,
	`creator` BIT(1) NOT NULL DEFAULT b'0',
	UNIQUE INDEX `upload` (`uid`, `id`),
	INDEX `FK_uploads_base` (`id`),
	INDEX `uid` (`uid`),
	CONSTRAINT `FK_uploads_users` FOREIGN KEY (`uid`) REFERENCES `users` (`uid`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `FK_uploads_base` FOREIGN KEY (`id`) REFERENCES `base` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Дамп структуры для таблицы 3wifi.favorites
CREATE TABLE `favorites` (
	`uid` INT(10) UNSIGNED NOT NULL,
	`id` INT(10) UNSIGNED NOT NULL,
	UNIQUE INDEX `favorite` (`uid`, `id`),
	INDEX `FK_favorites_base` (`id`),
	INDEX `uid` (`uid`),
	CONSTRAINT `FK_favorites_users` FOREIGN KEY (`uid`) REFERENCES `users` (`uid`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `FK_favorites_base` FOREIGN KEY (`id`) REFERENCES `base` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Дамп структуры для таблицы 3wifi.locations
CREATE TABLE `locations` (
	`uid` INT(10) UNSIGNED NOT NULL,
	`latitude` FLOAT(12,8) NOT NULL,
	`longitude` FLOAT(12,8) NOT NULL,
	`comment` VARCHAR(127) NOT NULL,
	UNIQUE INDEX `uniq` (`uid`, `latitude`, `longitude`),
	INDEX `uid` (`uid`),
	INDEX `coords` (`latitude`, `longitude`),
	CONSTRAINT `FK_locations_users` FOREIGN KEY (`uid`) REFERENCES `users` (`uid`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Дамп данных таблицы 3wifi.users
INSERT INTO `users` SET
	`regdate`=CURRENT_TIMESTAMP,
	`login`='admin',
	`nick`='Administrator',
	`salt`='2p8a!m%EFHr).djHO1uuIA^x82X$(988',
	`pass_hash`=MD5(CONCAT('admin',`salt`)),
	`autologin`='',
	`level`=3,
	`ip_hash`='',
	`invites`=65535;

-- Дамп структуры для таблицы 3wifi.extinfo
CREATE TABLE `extinfo` (
	`id` INT(11) NOT NULL,
	`data` VARCHAR(255) NOT NULL,
	`sn1` VARCHAR(50) NULL DEFAULT NULL,
	`sn2` VARCHAR(50) NULL DEFAULT NULL,
	`cable_mac` BIGINT(15) NULL DEFAULT NULL,
	PRIMARY KEY (`id`),
	INDEX `data_index` (`data`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
