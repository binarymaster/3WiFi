<?php
require_once 'utils.php';

// Settings block
define('TRY_USE_MEMORY_TABLES', false);
define('DEBUG_SQLQUERY', false);
define('MEMORY_TABLES_RELEVANCE_EXPIRES', 60*60*6); // 6 hours

// Tables
define('BASE_TABLE', 'base');
define('GEO_TABLE', 'geo');
define('BASE_TABLE_CONST', BASE_TABLE);
define('GEO_TABLE_CONST', GEO_TABLE);
define('BASE_MEM_TABLE', 'mem_base');
define('GEO_MEM_TABLE', 'mem_geo');
define('STATS_TABLE', 'stats');

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
	/* Данные входа в БД */
	$db_serv = 'localhost';
	$db_name = '3wifi';
	$db_user = 'root';
	$db_pass = '';
	global $db;

	$result = false;
	$tries = 3;
	while (!$result && $tries--)
	{
		/* Подключаемся к БД */
		$db = mysqli_connect($db_serv, $db_user, $db_pass, $db_name);

		/* Проверка подключения */
		if($db->connect_errno)
		{
			return false;
		}
		else
		{
			$result = true;
		}
	}
	define('BASE_USE_MEMORY', false);
	define('GEO_USE_MEMORY', false);

	$DataBaseStatus = GetStatsValue(STATS_DATABASE_STATUS);
	if($DataBaseStatus == DATABASE_PREPARE) // Database not avaible
	{
		$db = NULL;
		$result = false;
	}

	if($DataBaseStatus == -1) // Service table not initialized
	{
		SetStatsValue(STATS_DATABASE_STATUS, DATABASE_PREPARE, true);
	}
	if(TRY_USE_MEMORY_TABLES)
	{
		MemoryDataBaseInit();
	}
	if($DataBaseStatus != DATABASE_ACTIVE)
	{
		SetStatsValue(STATS_DATABASE_STATUS, DATABASE_ACTIVE, true);
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
	if(!isset($db)) return false;

	$SqlType = SQL_UNKNOWN;
	$BaseTable = BASE_USE_MEMORY ? BASE_MEM_TABLE : BASE_TABLE;
	$GeoTable = GEO_USE_MEMORY ? GEO_MEM_TABLE : GEO_TABLE;

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
			if(BASE_USE_MEMORY)
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
			if(GEO_USE_MEMORY)
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

	$res = $db->query($sql);
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
	if ($res = QuerySql("SELECT * FROM tasks WHERE `tid`='$tid'"))
	{
		if ($row = $res->fetch_row())
		{
			$result = array();
			$result['id'] = $row[0];
			$result['state'] = (int)$row[1];
			$result['created'] = $row[2];
			$result['modified'] = $row[3];
			$result['ext'] = $row[4];
			$result['comment'] = $row[5];
			$result['checkexist'] = (bool)$row[6];
			$result['nowait'] = (bool)$row[7];
			$result['lines'] = (int)$row[8];
			$result['accepted'] = (int)$row[9];
			$result['onmap'] = (int)$row[10];
			$result['warns'] = $row[11];
		}
		$res->close();
	}
	return $result;
}

function getCommentVal($cmtid)
{
	if ($cmtid == null) return '';
	$res = QuerySql("SELECT `cmtval` FROM comments WHERE `cmtid`=$cmtid");
	$row = $res->fetch_row();
	$res->close();
	return $row[0];
}

function getCommentId($comment, $create = false)
{
	$result = null;
	if ($comment == '') return $result;
	global $db;
	$comment = $db->real_escape_string($comment);
	$res = QuerySql("SELECT `cmtid` FROM comments WHERE `cmtval`='$comment'");
	$row = $res->fetch_row();
	$res->close();
	if ($row[0] == null)
	{
		if ($create)
		{
			QuerySql("INSERT INTO comments (`cmtval`) VALUES ('$comment')");
			$res = QuerySql("SELECT `cmtid` FROM comments WHERE `cmtval`='$comment'");
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
	$MemTablesStatus = CheckRelevanceOfMemoryTables(true);
	define('BASE_USE_MEMORY', $MemTablesStatus['Base']);
	define('GEO_USE_MEMORY', $MemTablesStatus['Geo']);
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

?>