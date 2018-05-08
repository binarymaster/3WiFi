<?php
require_once 'utils.php';

// Stats table ids
define('STATS_DATABASE_STATUS', 1);
define('STATS_LAST_MEMORY_BASE_TABLE_SYNS', 10);
define('STATS_LAST_MEMORY_GEO_TABLE_SYNS', 11);
define('STATS_BASE_ROWN_NUMS', 20);
define('STATS_GEO_ROWN_NUMS', 21);
define('STATS_BASE_DAYSTART_NUM', 22);
define('STATS_GEO_DAYSTART_NUM', 23);

// Database status
define('DATABASE_PREPARE', 0);
define('DATABASE_ACTIVE', 1);

// Query type
define('SQL_UNKNOWN', -1);
define('SQL_BASE_INSERT', 0);
define('SQL_BASE_SELECT', 1);
define('SQL_GEO_INSERT', 2);
define('SQL_GEO_SELECT', 3);
define('SQL_STATS_INSERT', 4);
define('SQL_STATS_SELECT', 5);
define('SQL_GEO_UPDATE', 6);
define('SQL_BASE_UPDATE', 7);

function db_connect()
{
	global $db;
	global $dbUseMemory;

	$result = false;
	$tries = 3;
	while (!$result && $tries--)
	{
		/* Подключаемся к БД */
		$db = mysqli_connect(DB_SERV, DB_USER, DB_PASS, DB_NAME);

		/* Проверка подключения */
		if($db->connect_errno)
		{
			return false;
		}
		else
		{
			$db->set_charset('utf8');
			$result = true;
		}
	}

	$dbUseMemory = false;
	$DataBaseStatus = GetStatsValue(STATS_DATABASE_STATUS);
	if($DataBaseStatus == DATABASE_PREPARE) // Database not avaible (now loading to mem)
	{
		$db = NULL;
		$result = false;
	}

	if(!TRY_USE_MEMORY_TABLES && $DataBaseStatus != DATABASE_ACTIVE)
	{
		SetStatsValue(STATS_DATABASE_STATUS, DATABASE_ACTIVE, true);
	}
	else
	{
		if(TRY_USE_MEMORY_TABLES)
		{
			$dbUseMemory = true;
			/*if ($DataBaseStatus == -1)
			{	// Service table not initialized
				$db = NULL;
				$result = false;
			}*/
		}
	}
	return $result;
}

function GetStatsValue($StatId)
{
	$res = QuerySql('SELECT value FROM STATS_TABLE WHERE StatId='.$StatId);
	if(!$res) return false;
	if($res->num_rows < 1) return -1;

	$row = $res->fetch_row();
	$res->close();
	return $row[0];
}

function SetStatsValue($StatId, $Value, $Replace = false)
{
	if($Replace)
	{
		$res = QuerySql('REPLACE INTO STATS_TABLE SET StatId='.$StatId.', value='.$Value);
	}
	else
	{
		$res = QuerySql('UPDATE STATS_TABLE SET value='.$Value.' WHERE StatId='.$StatId);
	}
	return (bool)$res;
}

function FixSql($sql)
{
	// Easy fixes
	$sql = str_replace("\t", '', $sql);
	$sql = str_replace("\n", '', $sql);
	$sql = str_replace("\r", '', $sql);
	$sql = preg_replace("/( ){2,}/", ' ', $sql);
	$sql = preg_replace("/%{2,}/", '%', $sql); // %%... => %

	// Sql fixes
	$sql = preg_replace("/AND (\"|'|`)[A-z]* LIKE (\"|')%(\"|') /", '', $sql);
	$sql = preg_replace("/(\"|'|`)[A-z]* LIKE (\"|')%(\"|') AND/", '', $sql);

	return $sql;
}

function QuerySql($sql, &$affected_rows = NULL)
{
	global $db;
	global $dbUseMemory;
	if(!isset($db)) return false;

	$SqlType = SQL_UNKNOWN;
	$BaseTable = $dbUseMemory ? BASE_MEM_TABLE : BASE_TABLE;
	$GeoTable = $dbUseMemory ? GEO_MEM_TABLE : GEO_TABLE;

	$sql = FixSql($sql);

	if(preg_match("/SELECT .* FROM (\"|'|`| |)BASE_TABLE/i", $sql) > 0)
	{
		$SqlType = SQL_BASE_SELECT;
	}
	if(preg_match("/INSERT INTO (\"|'|`| |)BASE_TABLE/i", $sql) > 0)
	{
		$SqlType = SQL_BASE_INSERT;
	}
	if(preg_match("/SELECT .* FROM (\"|'|`| |)GEO_TABLE/i", $sql) > 0)
	{
		$SqlType = SQL_GEO_SELECT;
	}
	if(preg_match("/INSERT INTO (\"|'|`| |)GEO_TABLE/i", $sql) > 0)
	{
		$SqlType = SQL_GEO_INSERT;
	}
	if(preg_match("/SELECT .* FROM (\"|'|`| |)STATS_TABLE/i", $sql) > 0)
	{
		$SqlType = SQL_STATS_SELECT;
	}
	if(preg_match("/INSERT INTO (\"|'|`| |)STATS_TABLE/i", $sql) > 0)
	{
		$SqlType = SQL_STATS_INSERT;
	}
	if(preg_match("/UPDATE (\"|'|`| |)GEO_TABLE/i", $sql) > 0)
	{
		$SqlType = SQL_GEO_UPDATE;
	}
	if(preg_match("/UPDATE (\"|'|`| |)BASE_TABLE/i", $sql) > 0)
	{
		$SqlType = SQL_BASE_UPDATE;
	}
	switch($SqlType)
	{
		case SQL_BASE_UPDATE:
		case SQL_BASE_INSERT:
			if($dbUseMemory)
			{
				$RepeatSql = str_replace('BASE_TABLE', BASE_TABLE, $sql);
				QuerySql($RepeatSql);
			}
			if($SqlType == SQL_BASE_INSERT) SetStatsValue(STATS_BASE_ROWN_NUMS, 'value+1');
			break;
		case SQL_BASE_SELECT:
			break;
		case SQL_GEO_UPDATE:
		case SQL_GEO_INSERT:
			if($dbUseMemory)
			{
				$RepeatSql = str_replace('GEO_TABLE', GEO_TABLE, $sql);
				QuerySql($RepeatSql);
			}
			if($SqlType == SQL_GEO_INSERT) SetStatsValue(STATS_GEO_ROWN_NUMS, 'value+1');
			break;
		case SQL_GEO_SELECT:
			break;
		case SQL_STATS_INSERT:
			break;
		case SQL_STATS_SELECT:
			break;
		default:
			break;
	}

	$sql = str_replace('BASE_TABLE_CONST', BASE_TABLE_CONST, $sql);
	$sql = str_replace('GEO_TABLE_CONST', GEO_TABLE_CONST, $sql);

	$sql = str_replace('BASE_MEM_TABLE', BASE_MEM_TABLE, $sql);
	$sql = str_replace('GEO_MEM_TABLE', GEO_MEM_TABLE, $sql);

	$sql = str_replace('BASE_TABLE', $BaseTable, $sql);
	$sql = str_replace('GEO_TABLE', $GeoTable, $sql);

	$sql = str_replace('STATS_TABLE', STATS_TABLE, $sql);

	if(DEBUG_SQLQUERY) Debug($sql);

	set_time_limit(0);
	$res = $db->query($sql);
	if(DEBUG_SQLQUERY && !$res && $db->errno) Debug($db->errno.': '.$db->error);
	if($affected_rows != NULL)
	{
		$affected_rows = $db->affected_rows;
	}
	return $res;
}

function getTask($tid)
{
	global $db;
	$result = false;
	if ($res = $db->query("SELECT * FROM tasks WHERE `tid`='$tid'"))
	{
		if ($row = $res->fetch_row())
		{
			$result = array();
			$result['id'] = $row[0];
			$result['uid'] = $row[1];
			$result['state'] = (int)$row[2];
			$result['created'] = $row[3];
			$result['modified'] = $row[4];
			$result['ext'] = $row[5];
			$result['comment'] = $row[6];
			$result['checkexist'] = (bool)$row[7];
			$result['nowait'] = (bool)$row[8];
			$result['lines'] = (int)$row[9];
			$result['accepted'] = (int)$row[10];
			$result['onmap'] = (int)$row[11];
			$result['warns'] = $row[12];
		}
		$res->close();
	}
	return $result;
}

function getCommentVal($cmtid)
{
	if ($cmtid == null) return '';
	global $db;
	$res = $db->query("SELECT `cmtval` FROM comments WHERE `cmtid`=$cmtid");
	$row = $res->fetch_row();
	$res->close();
	return $row[0];
}

function getCommentId($comment, $create = false)
{
	$result = 'null';
	if ($comment == '') return $result;
	global $db;
	$comment = $db->real_escape_string($comment);
	$res = $db->query("SELECT `cmtid` FROM comments WHERE `cmtval`='$comment'");
	$row = $res->fetch_row();
	$res->close();
	if ($row[0] == null)
	{
		if ($create)
		{
			$db->query("INSERT INTO comments (`cmtval`) VALUES ('$comment')");
			$res = $db->query("SELECT `cmtid` FROM comments WHERE `cmtval`='$comment'");
			$row = $res->fetch_row();
			$res->close();
			$result = (int)$row[0];
		}
	}
	else
		$result = (int)$row[0];
	return $result;
}

function MemoryDataBaseInit()
{
	global $dbUseMemory;
	$MemTablesStatus = CheckRelevanceOfMemoryTables(true);
	$dbUseMemory = ($MemTablesStatus['Base'] && $MemTablesStatus['Geo']);
}

function CheckRelevanceOfMemoryTables($UseFix)
{
	$Result = Array('Base'=> true, 'Geo'=> true,
		'BaseLastFixTime'=> NULL, 'GeoLastFixTime'=> NULL,
		'BaseNeedFix'=> false, 'GeoNeedFix'=> false);

	$Stat = GetStatsValue(STATS_LAST_MEMORY_BASE_TABLE_SYNS);

	if($Stat === FALSE || $Stat == -1)
	{
		if($Stat == -1)
		{
			QuerySql('INSERT INTO STATS_TABLE SET StatId='.STATS_LAST_MEMORY_BASE_TABLE_SYNS);
			$Stat = GetStatsValue(STATS_LAST_MEMORY_BASE_TABLE_SYNS);
			if($Stat === FALSE || $Stat == -1) $Result['Base'] = false;
		}
		else $Result['Base'] = false;
	}
	$Result['BaseLastFixTime'] = ($Stat !== FALSE && $Stat != -1) ? true : false;

	if($Stat < time())
	{
		// Recopy bases
		if($UseFix)
		{
			QuerySql('TRUNCATE BASE_MEM_TABLE');
			$AffectedRows = -1;
			QuerySql('INSERT INTO BASE_MEM_TABLE SELECT * FROM BASE_TABLE_CONST ORDER BY time DESC', $AffectedRows);
			SetStatsValue(STATS_BASE_ROWN_NUMS, $AffectedRows, true);
			SetStatsValue(STATS_LAST_MEMORY_BASE_TABLE_SYNS, (time()+MEMORY_TABLES_RELEVANCE_EXPIRES), true);
			$Result['BaseLastFixTime'] = time();
		}
		else
		{
			$Result['Base'] = false;
			$Result['BaseNeedFix'] = true;
		}
	}

	$Stat = GetStatsValue(STATS_LAST_MEMORY_GEO_TABLE_SYNS);
	if($Stat === FALSE || $Stat == -1)
	{
		if($Stat == -1)
		{
			QuerySql('INSERT INTO STATS_TABLE SET StatId='.STATS_LAST_MEMORY_GEO_TABLE_SYNS);
			$Stat = GetStatsValue(STATS_LAST_MEMORY_GEO_TABLE_SYNS);
			if($Stat === FALSE || $Stat == -1) $Result['Geo'] = false;
		}
		else $Result['Geo'] = false;
	}
	$Result['GeoLastFixTime'] = ($Stat !== FALSE && $Stat != -1) ? true : false;
	if($Stat < time())
	{
		if($UseFix)
		{
			QuerySql('TRUNCATE GEO_MEM_TABLE');
			$AffectedRows = -1;
			QuerySql('INSERT INTO GEO_MEM_TABLE SELECT * FROM GEO_TABLE_CONST', $AffectedRows);
			SetStatsValue(STATS_GEO_ROWN_NUMS, $AffectedRows, true);
			SetStatsValue(STATS_LAST_MEMORY_GEO_TABLE_SYNS, (time()+MEMORY_TABLES_RELEVANCE_EXPIRES), true);
			$Result['GeoLastFixTime'] = time();
		}
		else
		{
			$Result['Geo'] = false;
			$Result['GeoNeedFix'] = true;
		}
	}
	return $Result;
}

function db_add_ap($row, $cmtid, $uid)
{
	global $checkexist;
	global $db;
	global $aps;

	// Отбираем только валидные точки доступа
	$addr = $row[0];
	$port = $row[1];
	if ($addr == 'IP Address' && $port == 'Port')
	{
		return 1;
	}
	$bssid = $row[8];
	$essid = $row[9];
	if (strlen($essid) > 32) $essid = substr($essid, 0, 32);
	$sec = $row[10];
	$key = $row[11];
	if (strlen($key) > 64) $key = substr($key, 0, 64);
	$wps = preg_replace('~\D+~', '', $row[12]); // Оставляем только цифры

	if ($bssid == '<no wireless>')
		return 2;

	if (ismac($bssid)) // Проверка MAC-адреса
	{
		$NoBSSID = 0;
		$bssid = mac2dec($bssid);
	} else {
		$NoBSSID = 1;
		if ($bssid == '<access denied>')
			$NoBSSID = 2;
		if ($bssid == '<not accessible>')
			$NoBSSID = 3;
		if ($bssid == '<not implemented>')
			$NoBSSID = 4;
		$bssid = 0;
	}
	if (($NoBSSID || $wps == '')
	&& ($essid == '' || $sec == '' || $sec == '-' || $key == '' || $key == '-'))
	{
		if ($NoBSSID == 0
		|| $essid != ''
		|| $sec != ''
		|| $key != ''
		|| $wps != '')
		{ return 3; } // Недостаточно полезных данных для добавления
		else { return 1; } // Вообще не содержит данных
	}
	$emptydup = false;
	if (empty($key))
		$emptydup = db_ap_checkempty($NoBSSID, $bssid, $essid, $sec, $wps);
	if ($emptydup)
		return 4; // Точка с непустым ключом уже есть в базе
	if ($checkexist)
		if (db_ap_exist($NoBSSID, $bssid, $essid, $key))
		{
			return 4; // Уже есть в базе, пропускаем
		}
	if ($NoBSSID == 0) // Корректный BSSID
	{
		$aps[] = $bssid; // Записываем в очередь ожидания
		$chkgeo = QuerySql("SELECT `BSSID` FROM GEO_TABLE WHERE `BSSID`=$bssid LIMIT 1");
		if ($chkgeo->num_rows == 0)
		{
			// Добавляем новый BSSID с координатами NULL
			QuerySql("INSERT INTO GEO_TABLE (`BSSID`) VALUES ($bssid)");
		}
		$chkgeo->close();
	}
	if ($cmtid == null) $cmtid = 'NULL';
	$addr = _ip2long($addr); // IP Address
	if ($addr == 0 || $addr == -1) $addr = 'NULL';
	$port = (($port == '') ? 'NULL' : (int)$port); // Port
	$auth = ($row[4] == '' ? 'NULL' : '\''.$db->real_escape_string($row[4]).'\''); // Authorization
	$name = '\''.$db->real_escape_string($row[5]).'\''; // Device Name
	$radio = (($row[6] == '[X]') ? 1 : 0); // RadioOff
	$hide = (($row[7] == '[X]') ? 1 : 0); // Hidden
	$essid = '\''.$db->real_escape_string($essid).'\''; // ESSID
	$sec = str2sec($sec); // Security
	$key = '\''.$db->real_escape_string($key).'\''; // Wi-Fi Key
	$wps = (($wps == '') ? 1 : (int)$wps); // WPS PIN
	$lanip = _ip2long($row[13]); // LAN IP
	if ($lanip == 0 || $lanip == -1) $lanip = 'NULL';
	$lanmsk = _ip2long($row[14]); // LAN Mask
	if ($lanmsk == 0) $lanmsk = 'NULL';
	$wanip = _ip2long($row[15]); // WAN IP
	if ($wanip == 0 || $wanip == -1) $wanip = 'NULL';
	$wanmsk = _ip2long($row[16]); // WAN Mask
	if ($wanmsk == 0) $wanmsk = 'NULL';
	$gate = _ip2long($row[17]); // WAN Gateway
	if ($gate == 0 || $gate == -1) $gate = 'NULL';
	$DNS = explode(' ', $row[18]); // DNS (up to 3 servers)
	for ($i = 0; $i < count($DNS); $i++)
	{
		$DNS[$i] = _ip2long($DNS[$i]);
		if ($DNS[$i] == 0 || $DNS[$i] == -1) $DNS[$i] = 'NULL';
	}
	for ($i = 0; $i <= 3; $i++)
		if (!isset($DNS[$i])) $DNS[$i] = 'NULL';
	$data = trim(preg_replace('/\s+/', ' ', $row[21])); // Comment
	QuerySql("INSERT INTO BASE_TABLE (`cmtid`,`IP`,`Port`,`Authorization`,`name`,`RadioOff`,`Hidden`,`NoBSSID`,`BSSID`,`ESSID`,`Security`,`WiFiKey`,`WPSPIN`,`LANIP`,`LANMask`,`WANIP`,`WANMask`,`WANGateway`,`DNS1`,`DNS2`,`DNS3`)
			VALUES ($cmtid, $addr, $port, $auth, $name, $radio, $hide, $NoBSSID, $bssid, $essid, $sec, $key, $wps, $lanip, $lanmsk, $wanip, $wanmsk, $gate, $DNS[0], $DNS[1], $DNS[2])
			ON DUPLICATE KEY UPDATE
			`cmtid`=$cmtid,`IP`=$addr,`Port`=$port,`Authorization`=$auth,`name`=$name,`RadioOff`=$radio,`Hidden`=$hide,`NoBSSID`=$NoBSSID,`BSSID`=$bssid,`ESSID`=$essid,`Security`=$sec,`WiFiKey`=$key,`WPSPIN`=$wps,`LANIP`=$lanip,`LANMask`=$lanmsk,`WANIP`=$wanip,`WANMask`=$wanmsk,`WANGateway`=$gate,`DNS1`=$DNS[0],`DNS2`=$DNS[1],`DNS3`=$DNS[2];");
	if (!is_null($uid))
	{
		// Берём id точки из таблицы base в любом случае (могут быть расхождения с mem_base)
		$res = $db->query("SELECT id FROM ".BASE_TABLE." WHERE NoBSSID=$NoBSSID AND BSSID=$bssid AND ESSID=$essid AND Security=$sec AND WiFiKey=$key AND WPSPIN=$wps");
		$row = $res->fetch_row();
		$res->close();
		$id = (int)$row[0];
		// Выясняем, если кто-то уже загрузил такую точку
		$res = $db->query("SELECT COUNT(uid) FROM uploads WHERE id=$id");
		$row = $res->fetch_row();
		$res->close();
		$creator = ($row[0] > 0 ? 0 : 1);
		// Привязываем загруженную точку к аккаунту
		$db->query("INSERT IGNORE INTO uploads (uid, id, creator) VALUES ($uid, $id, $creator)");
	}
	if (!empty($data))
	{
		// Собираем доп. информацию о точке (может быть серийник и что-либо ещё)
		$res = $db->query("SELECT id FROM ".BASE_TABLE." WHERE NoBSSID=$NoBSSID AND BSSID=$bssid AND ESSID=$essid AND Security=$sec AND WiFiKey=$key AND WPSPIN=$wps");
		$row = $res->fetch_row();
		$res->close();
		$id = (int)$row[0];
		// Добавляем информацию
		$data = '\''.$db->real_escape_string($data).'\'';
		$db->query("INSERT INTO extinfo (`id`, `data`) VALUES ($id, $data) ON DUPLICATE KEY UPDATE `data` = $data");
	}
	return 0;
}

function db_ap_exist($NoBSSID, $bssid, $essid, $key)
{
	global $db;
	$result = 0;
	$essid = $db->real_escape_string($essid);
	$key = $db->real_escape_string($key);
	// Проверяем, есть ли эта точка в базе (по BSSID/ESSID/WiFiKey)
	if ($res = QuerySql("SELECT `id` FROM BASE_TABLE WHERE `NoBSSID`=$NoBSSID AND `BSSID`=$bssid AND `ESSID`='$essid' AND `WiFiKey`='$key' LIMIT 1"))
	{
		$result = $res->num_rows;
		$res->close();
	}
	return $result > 0;
}

function db_ap_checkempty($NoBSSID, $bssid, $essid, $sec, $wps)
{
	global $db;
	$result = 0;
	$essid = $db->real_escape_string($essid);
	$sec = str2sec($sec);
	$wps = (($wps == '') ? 1 : (int)$wps);
	// Проверяем, есть ли эта точка в базе (с непустым ключом)
	if ($res = QuerySql("SELECT `id` FROM BASE_TABLE WHERE `NoBSSID`=$NoBSSID AND `BSSID`=$bssid AND `ESSID`='$essid' AND `Security`=$sec AND `WiFiKey`!='' AND `WPSPIN`=$wps LIMIT 1"))
	{
		$result = $res->num_rows;
		$res->close();
	}
	return $result > 0;
}

function quote($var) 
{
	global $db;
	return $db->real_escape_string($var);
}

?>