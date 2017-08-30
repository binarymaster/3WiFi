<?php
if (!isset($_GET['a']))
{
	Header('HTTP/1.0 303 See Other');
	Header('Location: /');
	exit;
}
include 'config.php';
require_once 'user.class.php';
require_once 'utils.php';
require_once 'db.php';
require_once 'quadkey.php';


$action = $_GET['a'];

$time = microtime(true);
$json = array();
$json['result'] = false;

if ($action != 'hash')
{
	$UserManager = new User();
	$UserManager->loadSession();
}

switch ($action)
{
	// Проверка для Router Scan и других приложений
	case 'hash':
	$json['result'] = true;
	$json['hash']['state'] = false;
	$magic = '3wifi-magic-word';
	if (isset($_GET['check']))
	{
		$check = $_GET['check'];
		if (strlen($check) == 32)
		{
			$json['hash']['data'] = md5($check . ':' . $magic);
			$json['hash']['state'] = true;
		}
	}
	break;

	// Координаты точек на карте (с кластеризацией)
	case 'map':
	set_time_limit(10);
	list($tile_x1, $tile_y1, $tile_x2, $tile_y2) = explode(',', $_GET['tileNumber']);
	$tile_x1 = (int)$tile_x1;
	$tile_y1 = (int)$tile_y1;
	$tile_x2 = (int)$tile_x2;
	$tile_y2 = (int)$tile_y2;
	$zoom = (int)$_GET['zoom'];
	$callback = $_GET['callback'];
	$clat = (float)$_GET['clat'];
	$clon = (float)$_GET['clon'];
	$scat = (isset($_GET['scat']) ? (bool)$_GET['scat'] : false);

	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}

	$res = get_clusters($db, $tile_x1, $tile_y1, $tile_x2, $tile_y2, $zoom, $scat);

	unset($json); // здесь используется JSON-P
	Header('Content-Type: application/javascript');
	$json['error'] = null;
	$json['data']['type'] = 'FeatureCollection';
	$json['data']['features'] = array();
	$bssid = '';
	$get_info_stmt = $db->prepare('SELECT time, ESSID, WiFiKey FROM ' . BASE_TABLE . ' WHERE `BSSID`=?');
	$get_info_stmt->bind_param('s', $bssid);
	foreach ($res as $quadkey => $cluster)
	{
		if ($cluster['count'] == 1)
		{
			$ap['type'] = 'Feature';
		}
		else
		{
			$ap['type'] = 'Cluster';
			$ap['number'] = (int)$cluster['count'];
			$ap['bbox'] = get_tile_bbox($quadkey);
		}
		$ap['id'] = $quadkey;
		$ap['geometry']['type'] = 'Point';
		$ap['geometry']['coordinates'][0] = (float)$cluster['lat'];
		$ap['geometry']['coordinates'][1] = (float)$cluster['lon'];

		if (isset($ap['options'])) unset($ap['options']);
		// Colorize single selected AP
		if ($clat == $ap['geometry']['coordinates'][0] &&
			$clon == $ap['geometry']['coordinates'][1])
		{
			$ap['options']['iconColor'] = '#FF1E1E';
		}
		// Colorize selected AP in cluster
		if ($ap['type'] == 'Cluster' &&
			$clat >= $ap['bbox'][0][0] &&
			$clat <= $ap['bbox'][1][0] &&
			$clon >= $ap['bbox'][0][1] &&
			$clon <= $ap['bbox'][1][1])
		{
			$ap['options']['iconColor'] = '#FF1E1E';
		}

		$ap['properties']['hintContent'] = '';
		if (!empty($cluster['bssids']))
		{
			$hints = array();
			foreach ($cluster['bssids'] as $bssid)
			{
				if (!$get_info_stmt->execute()) continue;

				$get_info_stmt->bind_result($time, $essid, $key);
				while ($get_info_stmt->fetch())
				{
					$aphint = array();

					$xbssid = htmlspecialchars(dec2mac($bssid));
					$xessid = str_replace(' ', '&nbsp;', htmlspecialchars($essid));
					$xwifikey = str_replace(' ', '&nbsp;', htmlspecialchars($key));

					if ($UserManager->Level >= 0) $aphint[] = $time;
					$aphint[] = $xbssid;
					$aphint[] = $xessid;
					if ($UserManager->Level >= 0) $aphint[] = $xwifikey;
					$hints[] = implode('<br>', $aphint);
				}
			}
			$ap['properties']['hintContent'] = implode('<hr>', $hints);
		}
		$json['data']['features'][] = $ap;
	}
	$get_info_stmt->close();
	echo 'typeof '.$callback.' === \'function\' && '.$callback.'('.json_encode($json).');';
	exit;
	break;

	// Поиск по базе
	case 'find':
	$json['result'] = true;
	$json['auth'] = $UserManager->Level >= 0;
	if (!$json['auth']) break;

	function HasWildcards($str, $wc)
	{
		return StrInStr($str, $wc[0]) || StrInStr($str, $wc[1]);
	}
	function FilterWildcards($str, $wc, $strict = true)
	{
		if ($strict)
			$str = str_replace($wc[0], '', $str);
		$str = str_replace($wc[1], '', $str);
		return $str;
	}
	function UniStrWildcard($str, $wc)
	{
		$str = str_replace('_', '\\_', $str);
		$str = str_replace('%', '\\%', $str);
		$str = str_replace($wc[0], '_', $str);
		$str = str_replace($wc[1], '%', $str);
		return $str;
	}
	function GenerateFindQuery($cmtid, $ipaddr, $BSSID, $ESSID, $Auth, $Name, $Key, $WPS, $sens, $Page, $Limit)
	{
		if(!isset($_SESSION['Search'])) $_SESSION['Search'] = array();
		if(!isset($_SESSION['Search']['ArgsHash'])) $_SESSION['Search']['ArgsHash'] = '';
		if(!isset($_SESSION['Search']['LastRowsNum'])) $_SESSION['Search']['LastRowsNum'] = -1;
		if(!isset($_SESSION['Search']['LastId'])) $_SESSION['Search']['FirstId'] = -1;
		if(!isset($_SESSION['Search']['LastId'])) $_SESSION['Search']['LastId'] = -1;
		if(!isset($_SESSION['Search']['LastPage'])) $_SESSION['Search']['LastPage'] = 1;

		$isLimitedRequest = false;
		$DiffPage = 0;
		$NextPageStartId = 0;

		$TestBSSID = preg_replace("/\*{2,}/", '*', $BSSID);
		$SplitCount = substr_count($TestBSSID, ':') + substr_count($TestBSSID, '.')+ substr_count($TestBSSID, '-');
		$UnkCount = substr_count($TestBSSID, '*');

		$Wildcards = array('□','◯');

		global $UserManager;
		$uid = $UserManager->uID;

		if(($UserManager->Level < 2) && ($SplitCount < $UnkCount || $TestBSSID == '*' || $BSSID == '') && (FilterWildcards($ESSID, $Wildcards) == ''))
		{
			$isLimitedRequest = true;
		}

		$binary = ($sens ? 'BINARY' : '');

		if($Page == 1) 
		{
			$_SESSION['Search']['FirstId'] = -1;
			$_SESSION['Search']['LastId'] = -1;
		}

		$sql = 'SELECT SQL_CALC_FOUND_ROWS 
				B.`id`,`time`,
				`cmtid`,`cmtval`,
				`IP`,`Port`,`Authorization`,`name`,
				`RadioOff`,`Hidden`,
				`NoBSSID`,`BSSID`,`ESSID`,`Security`,
				`WiFiKey`,`WPSPIN`,`WANIP`,
				`latitude`,`longitude`, uid IS NOT NULL fav 
				FROM `BASE_TABLE` AS B 
				LEFT JOIN `comments` USING(cmtid) 
				LEFT JOIN `GEO_TABLE` USING(BSSID) 
				LEFT JOIN `favorites` AS F ON B.id = F.id AND uid = '.$uid.' 
				WHERE 1';
		if ($cmtid != -1)
		{
			$sql .= ' AND (`cmtid` '.($cmtid == 0 ? 'IS NULL)' : "= $cmtid)");
		}
		$ipaddr = ip2long($ipaddr) !== false ? ip2long($ipaddr) : '';
		if ($ipaddr != '')
		{
			$sql .= " AND `IP` = $ipaddr";
		}
		if (str_replace('*', '', $BSSID) != '')
		{
			if (StrInStr($BSSID, '*'))
			{
				if (preg_replace("/F+0+/", 'F0', mac_mask($BSSID, false)) == 'F0')
				{
					$mmac = mac_mask($BSSID);
					$mask = mac_mask($BSSID, false);
					$n_mask = str_replace('0', 'F', ltrim($mask, 'F'));
					$sql .= " AND `BSSID` BETWEEN (0x$mmac & 0x$mask) AND (0x$mmac | 0x$n_mask)";
				}
				else
				{
					$mmac = mac2dec(mac_mask($BSSID));
					$mask = mac2dec(mac_mask($BSSID, false));
					$sql .= " AND (`BSSID` & $mask = $mmac)";
				}
			}
			else $sql .= ' AND `BSSID` = '.mac2dec($BSSID).'';
		}
		if (FilterWildcards($ESSID, $Wildcards, false) != '' || empty($ESSID))
		{
			if (HasWildcards($ESSID, $Wildcards)) $sql .= ' AND '.$binary.' `ESSID` LIKE \''.UniStrWildcard($ESSID, $Wildcards).'\'';
			else $sql .= ' AND '.$binary.' `ESSID` = \''.$ESSID.'\'';
		}
		if (FilterWildcards($Auth, $Wildcards, false) != '' || empty($Auth))
		{
			if (HasWildcards($Auth, $Wildcards)) $sql .= ' AND '.$binary.' `Authorization` LIKE \''.UniStrWildcard($Auth, $Wildcards).'\'';
			else $sql .= ' AND '.$binary.' `Authorization` = \''.$Auth.'\'';
		}
		if (FilterWildcards($Name, $Wildcards, false) != '' || empty($Name))
		{
			if (HasWildcards($Name, $Wildcards)) $sql .= ' AND '.$binary.' `name` LIKE \''.UniStrWildcard($Name, $Wildcards).'\'';
			else $sql .= ' AND '.$binary.' `name` = \''.$Name.'\'';
		}
		if (FilterWildcards($Key, $Wildcards, false) != '' || empty($Key))
		{
			if (HasWildcards($Key, $Wildcards)) $sql .= ' AND '.$binary.' `WiFiKey` LIKE \''.UniStrWildcard($Key, $Wildcards).'\'';
			else $sql .= ' AND '.$binary.' `WiFiKey` = \''.$Key.'\'';
		}
		if (FilterWildcards($WPS, $Wildcards, false) != '' || empty($WPS))
		{
			if (HasWildcards($WPS, $Wildcards)) $sql .= ' AND WPSPIN != 1 AND LPAD(WPSPIN, 8, "0") LIKE \''.UniStrWildcard($WPS, $Wildcards).'\'';
			else $sql .= (empty($WPS) ? ' AND `WPSPIN` = 1' : ' AND `WPSPIN` = \''.$WPS.'\'');
		}

		if($_SESSION['Search']['ArgsHash'] == md5($cmtid.$ipaddr.$BSSID.$ESSID.$Auth.$Name.$Key.$WPS.$binary))
		{
			$sql = str_replace('SQL_CALC_FOUND_ROWS', '', $sql);
		}
		else
		{
			$_SESSION['Search']['LastRowsNum'] = -1;
			$_SESSION['Search']['FirstId'] = -1;
			$_SESSION['Search']['LastId'] = -1;
			$_SESSION['Search']['LastPage'] = 1;
		}

		$Sign = '<';
		$DiffPage = ((int)$Page-$_SESSION['Search']['LastPage']);

		if($isLimitedRequest || $_SESSION['Search']['LastId'] == -1 || $_SESSION['Search']['FirstId'] == -1) 
		{
			$NextPageStartId = 4294967295;
		}
		else
		{
			if($DiffPage < 0)
			{
				$Sign = '>';
				$NextPageStartId = (int)$_SESSION['Search']['FirstId'];
			}
			else
			{
				$NextPageStartId = (int)$_SESSION['Search']['LastId'];
			}
		}

		$DiffPage = abs($DiffPage);
		if($DiffPage > 0) $DiffPage--;

		if($isLimitedRequest)
		{
			$DiffPage = 0;
		}

		$sql .= ' AND B.`id` '.$Sign.' '.$NextPageStartId;
		if (!TRY_USE_MEMORY_TABLES)
			$sql .= ' ORDER BY `time` DESC';
		$sql .= ' LIMIT '.($DiffPage * 100).', '.$Limit;

		$_SESSION['Search']['ArgsHash'] = md5($cmtid.$BSSID.$ESSID.$Auth.$Name.$Key.$WPS.$binary);
		$_SESSION['Search']['LastPage'] = $Page;

		return $sql;
	}
	$comment = '*';
	$cmtid = -1;
	$ipaddr = '';
	$auth = '◯';
	$name = '◯';
	$bssid = '';
	$essid = '◯';
	$key = '◯';
	$wps = '◯';
	$sens = false;
	if (isset($_POST['bssid'])) $bssid = $_POST['bssid'];
	if (isset($_POST['essid'])) $essid = $_POST['essid'];
	$bssid = str_replace('-', ':', $bssid);
	if ($UserManager->Level > 1)
	{
		if (isset($_POST['comment'])) $comment = $_POST['comment'];
		if (isset($_POST['ipaddr'])) $ipaddr = $_POST['ipaddr'];
		if (isset($_POST['auth'])) $auth = $_POST['auth'];
		if (isset($_POST['name'])) $name = $_POST['name'];
	}
	if (isset($_POST['key'])) $key = $_POST['key'];
	if (isset($_POST['wps'])) $wps = $_POST['wps'];
	if (isset($_POST['sens'])) $sens = in_array($_POST['sens'], array('1', 'on', 'true'), true);
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	$json['data'] = array();

	if ($comment != '*')
	{
		if ($comment == '')
		{
			$cmtid = 0;
		}
		else
		{
			$comment = $db->real_escape_string($comment);
			$res = $db->query('SELECT `cmtid` FROM comments WHERE `cmtval`=\''.$comment.'\'');
			if ($res->num_rows > 0)
			{
				$row = $res->fetch_row();
				$cmtid = (int)$row[0];
			}
			else
			{
				$cmtid = -2;
			}
			$res->close();
		}
	}
	$auth = $db->real_escape_string($auth);
	$name = $db->real_escape_string($name);
	$essid = $db->real_escape_string($essid);
	$key = $db->real_escape_string($key);
	$wps = $db->real_escape_string($wps);

	$cur_page = 1;
	$per_page = 100;
	if (isset($_POST['page'])) $cur_page = (int)$_POST['page'];
	if ($cur_page < 1) $cur_page = 1;

	$sql = GenerateFindQuery($cmtid, $ipaddr, $bssid, $essid, $auth, $name, $key, $wps, $sens, $cur_page, $per_page);
	if ($res = QuerySql($sql))
	{
		if($_SESSION['Search']['LastRowsNum'] == -1)
		{
			$res_rows = QuerySql('SELECT FOUND_ROWS()');
			$t = $res_rows->fetch_row();
			$_SESSION['Search']['LastRowsNum'] = (int)$t[0];
		}
		if(isset($_SESSION['Search']['LastRowsNum']))
		{
			$rows = (int)$_SESSION['Search']['LastRowsNum'];
			$pages = ceil($rows / $per_page);
			$json['found'] = $rows;
			$json['page']['current'] = $cur_page;
			$json['page']['count'] = $pages;
		}

		$FirstId = -1;
		$LastId = -1;
		while ($row = $res->fetch_assoc())
		{
			if ($FirstId == -1) $FirstId = (int)$row['id'];
			$LastId = (int)$row['id'];

			$entry = array();
			if ($UserManager->Level >= 1) $entry['id'] = (int)$row['id'];
			$entry['time'] = $row['time'];
			$entry['comment'] = ($row['cmtid'] == null ? '' : $row['cmtval']);
			$ip = _long2ip($row['IP']);
			$wanip = _long2ip($row['WANIP']);
			if ($UserManager->Level > 1)
			{
				$entry['ipport'] = ($ip != '' ? $ip : ($wanip != '' ? $wanip : ''));
				if (isLocalIP($entry['ipport'])
				&& $entry['ipport'] != $wanip
				&& isValidIP($wanip)
				&& !isLocalIP($wanip))
				{
					$entry['ipport'] = $wanip;
				}
				if ($entry['ipport'] != '' && $row['Port'] != null) $entry['ipport'] .= ':'.$row['Port'];
				$entry['auth'] = $row['Authorization'];
				$entry['name'] = $row['name'];
			} else {
				$entry['range'] = ($ip != '' ? $ip : ($wanip != '' ? $wanip : ''));
				if (isLocalIP($entry['range'])
				&& $entry['range'] != $wanip
				&& isValidIP($wanip)
				&& !isLocalIP($wanip))
				{
					$entry['range'] = $wanip;
				}
				if (isValidIP($entry['range']))
				{
					$oct = explode('.', $entry['range']);
					array_pop($oct);
					array_pop($oct);
					$entry['range'] = implode('.', $oct).'.0.0/16';
				} else
					$entry['range'] = '';
			}
			$entry['nowifi'] = (bool)$row['RadioOff'];
			$entry['hidden'] = (bool)$row['Hidden'];
			$entry['bssid'] = '';
			if ((int)$row['NoBSSID'] == 0) $entry['bssid'] = dec2mac($row['BSSID']);
			$entry['essid'] = $row['ESSID'];
			$entry['sec'] = sec2str((int)$row['Security']);
			$entry['key'] = $row['WiFiKey'];
			$entry['wps'] = ($row['WPSPIN'] == 1 ? '' : str_pad($row['WPSPIN'], 8, '0', STR_PAD_LEFT));
			$entry['lat'] = 'none';
			$entry['lon'] = 'none';
			if ((int)$row['NoBSSID'] == 0 && $row['latitude'] !== null)
			{
				$entry['lat'] = (float)$row['latitude'];
				$entry['lon'] = (float)$row['longitude'];
				if ($entry['lat'] == 0 && $entry['lon'] == 0)
				{
					$entry['lat'] = 'not found';
					$entry['lon'] = 'not found';
				}
			}
			$entry['fav'] = (bool)$row['fav'];

			$json['data'][] = $entry;
			unset($entry);
		}
		$res->close();

		$_SESSION['Search']['FirstId'] = $FirstId;
		$_SESSION['Search']['LastId'] = $LastId;
	}
	$db->close();
	break;

	// Поиск диапазонов IP
	case 'ranges':
	$json['result'] = true;
	$json['auth'] = $UserManager->Level >= 0;
	if (!$json['auth']) break;

	$lat = ''; $lon = '';
	if (isset($_POST['latitude'])) $lat = $_POST['latitude'];
	if (isset($_POST['longitude'])) $lon = $_POST['longitude'];
	if ($lat == '')
	{
		$json['error'] = 'form';
		break;
	}
	if ($lon == '')
	{
		$json['error'] = 'form';
		break;
	}
	$lat = (float)$lat;
	$lon = (float)$lon;
	if ($lat < -90 || $lat > 90)
	{
		$json['error'] = 'form';
		break;
	}
	if ($lon < -180 || $lon > 180)
	{
		$json['error'] = 'form';
		break;
	}

	$radius = '';
	if (isset($_POST['radius'])) $radius = $_POST['radius'];
	if ($radius == '')
	{
		$json['error'] = 'form';
		break;
	}
	$radius = (float)$radius;
	if ($radius < 0 || $radius > 25)
	{
		$json['error'] = 'form';
		break;
	}

	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	require 'ipext.php';
	$json['data'] = API_get_ranges($lat, $lon, $radius);
	$db->close();
	break;

	// Определение устройства по MAC
	case 'devicemac':
	$json['result'] = true;
	$json['auth'] = $UserManager->Level >= 0;
	if (!$json['auth']) break;

	$bssid = '';
	if (isset($_POST['bssid'])) $bssid = $_POST['bssid'];
	if(!ismac($bssid))
	{
		$json['result'] = false;
		$json['error'] = 'form';
		break;
	}
	$mac = mac2dec($bssid);
	$oui = bcdiv($mac, bcpow('2', '24'));
	$mac = base_convert($mac, 10, 16);
	$oui = base_convert($oui, 10, 16);
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql("
		SELECT name, COUNT(name) cnt, ABS(BSSID - 0x$mac) diff 
		FROM ( 
			SELECT name, BSSID 
			FROM `BASE_TABLE` 
			WHERE BSSID BETWEEN 0x{$oui}000000 AND 0x{$oui}FFFFFF AND name != '' 
			ORDER BY ABS(BSSID - 0x$mac) 
		) T 
		GROUP BY name HAVING(cnt > 1) 
		ORDER BY ABS(BSSID - 0x$mac) 
		LIMIT 10
	"))
	{
		$devs = array();
		while ($row = $res->fetch_assoc())
		{
			$devs[] = $row;
		}
		$res->close();
	}
	$db->close();
	$json['scores'] = array();
	foreach($devs as $dev)
	{
		$entry = array();
		$entry['name'] = $dev['name'];
		$entry['score'] = 1 - pow((int)$dev['diff'] / 0xFFFFFF, 1 / 8);
		$json['scores'][] = $entry;
	}
	break;

	// Определение WPS PIN по MAC
	case 'wpspin':
	$json['result'] = true;
	$json['auth'] = $UserManager->Level >= 0;
	if (!$json['auth'])
	{
		break;
	}
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}

	$bssid = '';
	if (isset($_POST['bssid']))
	{
		$bssid = $_POST['bssid'];
	}
	$bssid = preg_replace('/[^0-9A-Fa-f]/', '', $bssid);

	if (strlen($bssid) != 12)
	{
		$json['result'] = false;
		$json['error'] = 'bssid';
		break;
	}
	require_once 'wpspin.php';
	$result = API_pin_search($bssid);
	$json = array_merge($json, $result);
	$db->close();
	break;

	// Загрузка отчётов в базу
	case 'upload':
	$json['result'] = true;

	$json['upload']['state'] = false;
	$json['upload']['processing'] = false;
	$error = array();
	// Извлекаем тип данных, игнорируя кодировку
	$contentType = explode('; ', $_SERVER['CONTENT_TYPE']);
	$contentType = $contentType[0];
	// Проверяем всё необходимое
	if ($_SERVER['REQUEST_METHOD'] == 'POST'
	&& ($contentType == 'text/plain'
	|| $contentType == 'text/csv')
	&& isset($HTTP_RAW_POST_DATA)
	&& strlen($HTTP_RAW_POST_DATA) > 0
	&& strlen($HTTP_RAW_POST_DATA) < 5000000)
	{
		if (!db_connect())
		{
			$json['result'] = false;
			$json['error'] = 'database';
			break;
		}
		$tid = '';
		if (isset($_GET['tid'])) $tid = $_GET['tid'];
		$tid = $db->real_escape_string($tid);
		$comment = '';
		if (isset($_GET['comment'])) $comment = trim(preg_replace('/\s+/', ' ', $_GET['comment']));
		$checkexist = isset($_GET['checkexist']) && ($_GET['checkexist'] == '1');
		$checkexist = ($checkexist ? 1 : 0);
		$nowait = isset($_GET['nowait']) && ($_GET['nowait'] == '1');
		$nowait = ($nowait ? 1 : 0);
		$done = isset($_GET['done']) && ($_GET['done'] == '1');
		$key = (isset($_GET['key']) ? $_GET['key'] : null);
		if ($contentType == 'text/csv') $ext = '.csv';
		if ($contentType == 'text/plain') $ext = '.txt';
		if ($tid == '')
		{
			function randhex($length)
			{
				$alpha = '0123456789abcdef';
				$len = strlen($alpha);
				$str = '';
				for ($i = 0; $i < $length; $i++)
					$str .= $alpha[rand(0, $len - 1)];
				return $str;
			}
			// Создание нового задания
			$task = true;
			while ($task !== false)
			{
				$tid = randhex(32);
				$task = getTask($tid);
			}
			// Сохраняем файл
			$filename = 'uploads/'.$tid.$ext;
			if (($handle = fopen($filename, 'ab')) !== false)
			{
				fwrite($handle, $HTTP_RAW_POST_DATA);
				fclose($handle);
			}
			// Проверка на валидность
			$valid = false;

			if (($handle = fopen($filename, 'r')) !== false)
			{
				switch ($ext)
				{
					case '.csv':
					if (($row = fgetcsv($handle, 1000, ';')) !== false)
						$valid = ValidHeaderCSV($row);
					if (!$valid) $error[] = 6; // Неправильный CSV
					break;
					case '.txt':
					if (($row = fgets($handle)) !== false)
						$valid = ValidHeaderTXT($row);
					if (!$valid) $error[] = 7; // Неправильный TXT
					break;
				}
				fclose($handle);
			}
			if ($valid)
			{
				$comment = $db->real_escape_string($comment);
				$useapi = false;
				if (!is_null($key))
					$useapi = $UserManager->AuthByApiKey($key, true);
				$uid = $UserManager->uID;
				if (is_null($uid) || $UserManager->Level < 1 || ($useapi && $UserManager->ApiAccess != 'write'))
					$uid = 'NULL';
				if ($db->query('INSERT INTO tasks (`tid`,`created`,`modified`,`ext`,`comment`,`checkexist`,`nowait`,`uid`) VALUES (\''.$tid.'\', now(), now(), \''.$ext.'\', \''.$comment.'\', '.$checkexist.', '.$nowait.', '.$uid.')'))
				{
					$json['upload']['state'] = true;
					$json['upload']['tid'] = $tid;
				}
			} else
				unlink($filename);
		} else {
			// Обновление существующего задания
			$task = getTask($tid);
			if ($task === false)
			{
				$error[] = 2; // Задание не существует
			} else {
				if ($task['state'] > 0)
				{
					$error[] = 3; // В процессе обработки, невозможно внести изменения
					$json['upload']['processing'] = true;
				} else {
					$json['upload']['state'] = true;
					$json['upload']['tid'] = $tid;
					$filename = 'uploads/'.$tid.$task['ext'];
					$comment = $db->real_escape_string($comment);
					$db->query('UPDATE tasks SET `modified`=now(),`comment`=\''.$comment.'\',`checkexist`='.$checkexist.',`nowait`='.$nowait.' WHERE `tid`=\''.$tid.'\')');
					if ($task['ext'] != $ext)
					{
						$error[] = 4; // Несовпадение с форматом файла задания
					} else {
						if (filesize($filename) > 500000000)
						{
							$error[] = 5; // Превышен максимально допустимый объём задания
							$done = true;
						} else
							if (($handle = fopen($filename, 'ab')) !== false)
							{
								fwrite($handle, $HTTP_RAW_POST_DATA);
								fclose($handle);
							}
					}
				}
			}
		}
		if ($json['upload']['state'] && $done)
		{
			// Запуск обработки задания
			$json['upload']['processing'] = $db->query('UPDATE tasks SET `tstate`=1 WHERE `tid`=\''.$tid.'\'');
		}
		$db->close();
	} else
		$error[] = 1; // Неверные заголовки или размер данных
	$json['upload']['error'] = $error;
	break;

	// Запрос всей информации о точке в базе
	case 'queryall':
	$json['result'] = false;
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level != 3)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$id = isset($_GET['id']) ? $_GET['id'] : null;
	if(is_null($id) || !is_numeric($id))
	{
		$json['error'] = 'form';
		break;
	}
	$id = (int)$id;
	$sql = "SELECT 
				* 
			FROM 
				`BASE_TABLE` 
				INNER JOIN `GEO_TABLE` USING(BSSID) 
				LEFT JOIN `comments` USING(cmtid) 
			WHERE 
				id = $id";
	$res = QuerySql($sql);
	if ($res->num_rows < 1)
	{
		$json['error'] = 'notfound';
		break;
	}
	$row = $res->fetch_assoc();
	$json['result'] = true;
	$json['data'] = array(
		'id'      => (int)$row['id'],
		'time'    => $row['time'],
		'comment' => $row['cmtval'],
		'ip'      => _long2ip($row['IP']),
		'port'    => (int)$row['Port'],
		'auth'    => $row['Authorization'],
		'name'    => $row['name'],
		'radioOff' => (bool)$row['RadioOff'],
		'hidden'  => (bool)$row['Hidden'],
		'bssid'   => ($row['NoBSSID'] ? '' : dec2mac($row['BSSID'])),
		'essid'   => $row['ESSID'],
		'sec'     => sec2str($row['Security']),
		'key'     => $row['WiFiKey'],
		'wps'     => ($row['WPSPIN'] == 1 ? '' : str_pad($row['WPSPIN'], 8, '0', STR_PAD_LEFT)),
		'lan_ip'  => _long2ip($row['LANIP']),
		'lan_mask' => _long2ip($row['LANMask']),
		'wan_ip'  => _long2ip($row['WANIP']),
		'wan_mask' => _long2ip($row['WANMask']),
		'wan_gw'  => _long2ip($row['WANGateway']),
		'dns1'    => _long2ip($row['DNS1']),
		'dns2'    => _long2ip($row['DNS2']),
		'dns3'    => _long2ip($row['DNS3']),
		'lat'     => 'none',
		'lon'     => 'none',
		'quadkey' => $row['quadkey'],
	);
	if ($row['NoBSSID'] == 0)
	{
		if ($row['latitude'] !== null)
		{
			$json['data']['lat'] = (float)$row['latitude'];
			$json['data']['lon'] = (float)$row['longitude'];
			if ($json['data']['lat'] == 0 && $json['data']['lon'] == 0)
			{
				$json['data']['lat'] = 'not found';
				$json['data']['lon'] = 'not found';
			}
		}
		else
		{
			$json['data']['lat'] = 'in progress';
			$json['data']['lon'] = 'in progress';
		}
	}
	$res->close();

	$sql = "SELECT 
				* 
			FROM 
				uploads 
				INNER JOIN users USING(uid) 
			WHERE 
				id = $id";
	$res = QuerySql($sql);
	$json['uploaders'] = array();
	while ($row = $res->fetch_assoc())
	{
		$entry = array(
			'uid'        => (int)$row['uid'],
			'login'      => $row['login'],
			'nick'       => $row['nick'],
			'creator' => (bool)$row['creator'],
		);
		$json['uploaders'][] = $entry;
	}
	$res->close();

	$sql = "SELECT 
				* 
			FROM 
				favorites 
				INNER JOIN users USING(uid) 
			WHERE 
				id = $id";
	$res = QuerySql($sql);
	$json['stars'] = array();
	while ($row = $res->fetch_assoc())
	{
		$entry = array(
			'uid'        => (int)$row['uid'],
			'login'      => $row['login'],
			'nick'       => $row['nick'],
		);
		$json['stars'][] = $entry;
	}
	$res->close();
	break;

	// Удаление точек из базы
	case 'delete':
	$json['result'] = false;
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level != 3)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	if (!$UserManager->checkToken($_GET['token']))
	{
		$json['error'] = 'token';
		break;
	}
	$id = isset($_GET['id']) ? $_GET['id'] : null;
	if(is_null($id) || !is_numeric($id))
	{
		$json['error'] = 'form';
		break;
	}
	$id = (int)$id;
	QuerySql('DELETE FROM BASE_TABLE WHERE id = ' . $id);
	if (defined('TRY_USE_MEMORY_TABLES'))
		QuerySql('DELETE FROM BASE_TABLE_CONST WHERE id = ' . $id);
	$json['result'] = true;
	break;

	// Обновление координат точек
	case 'geoupdate':
	$json['result'] = false;
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level != 3)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	if (!$UserManager->checkToken($_GET['token']))
	{
		$json['error'] = 'token';
		break;
	}
	$bssid = isset($_GET['bssid']) ? $_GET['bssid'] : null;
	if(!ismac($bssid))
	{
		$json['error'] = 'form';
		break;
	}
	$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 0;
	$lon = isset($_GET['lon']) ? (float)$_GET['lon'] : 0;
	require_once 'geoext.php';
	if ($lat == 0 && $lon == 0)
	{
		$coords = GeoLocateAP($bssid);
	}
	else
	{
		$coords = "$lat;$lon;manual";
	}
	if ($coords == '')
	{
		$json['error'] = 'notfound';
		break;
	}
	$json['result'] = true;
	$bssid = mac2dec($bssid);
	$coords = explode(';', $coords);
	$latitude = $coords[0];
	$longitude = $coords[1];
	$quadkey = base_convert(latlon_to_quadkey($latitude, $longitude, MAX_ZOOM_LEVEL), 2, 10);
	QuerySql("UPDATE GEO_TABLE SET `latitude`=$latitude,`longitude`=$longitude,`quadkey`=$quadkey WHERE `BSSID`=$bssid");
	$json['lat'] = (float)$latitude;
	$json['lon'] = (float)$longitude;
	$json['provider'] = $coords[2];
	break;

	// Проверка состояния загрузки
	case 'upstat':
	$json['result'] = true;

	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	$tid = '';
	if (isset($_GET['tid'])) $tid = $_GET['tid'];
	$tid = $db->real_escape_string($tid);
	$task = getTask($tid);
	$db->close();
	if ($task !== false && $task['state'] > 0)
	{
		$json['upstat']['state'] = $task['state'];
		$json['upstat']['lines'] = $task['lines'];
		$json['upstat']['accepted'] = $task['accepted'];
		$json['upstat']['onmap'] = $task['onmap'];
		$json['upstat']['warns'] = $task['warns'];
	} else
		$json['upstat']['state'] = -1;
	break;

	// Подсказка по комментарию
	case 'commhint':
	$json['result'] = true;
	$json['hint'] = array();

	$comm = '';
	if (isset($_GET['comm'])) $comm = trim(preg_replace('/\s+/', ' ', $_GET['comm']));
	if ($comm == '' || strlen($comm) > 127) break;
	$html = false;
	if (isset($_GET['html'])) $html = $_GET['html'] == '1';

	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	$sql = 'SELECT cmtval FROM comments WHERE 1 ';
	$comm = array_unique(explode(' ', $comm));

	foreach ($comm as $cval)
		$sql .= ' AND cmtval LIKE \'%'.$db->real_escape_string($cval).'%\'';

	$sql .= ' ORDER BY cmtval';
	if ($res = $db->query($sql))
	{
		while ($row = $res->fetch_row())
			$json['hint'][] = $row[0];

		$res->close();
	}
	$db->close();

	function highlightWords($text, array $words)
	{
		$words = array_map(preg_quote, $words);
		return preg_replace('/('. implode('|', $words) .')/isu', '<b>$1</b>', $text);
	}
	if ($html)
		for ($i = 0; $i < count($json['hint']); $i++)
		{
			$json['hint'][$i] = highlightWords(htmlspecialchars($json['hint'][$i]), $comm);
		}
	break;

	// Общая статистика
	case 'stat':
	set_time_limit(30);
	$json['result'] = true;
	date_default_timezone_set('UTC');
	$json['stat']['date'] = date('Y.m.d H:i:s');
	$mode = (isset($_GET['mode']) ? (int)$_GET['mode'] : 0);
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($mode == 0 || $mode == 1)
	{
		$json['stat']['total'] = GetStatsValue(STATS_BASE_ROWN_NUMS);
		if(1)
		{
			if ($res = QuerySql('SELECT COUNT(id) FROM BASE_TABLE'))
			{
				$row = $res->fetch_row();
				$json['stat']['total'] = (int)$row[0];
				$res->close();
			}
		}
		if ($res = QuerySql('SELECT COUNT(BSSID) FROM GEO_TABLE WHERE (`quadkey` IS NOT NULL)'))
		{
			$row = $res->fetch_row();
			$json['stat']['onmap'] = (int)$row[0];
			$res->close();
		}
	}
	if ($mode == 0)
	{
		if ($res = QuerySql('SELECT COUNT(id) FROM BASE_TABLE WHERE NoBSSID = 0'))
		{
			$row = $res->fetch_row();
			$json['stat']['bssids'] = (int)$row[0];
			$res->close();
		}
		if ($res = QuerySql('SELECT COUNT(BSSID) FROM GEO_TABLE'))
		{
			$row = $res->fetch_row();
			$json['stat']['uniqbss'] = (int)$row[0];
			$res->close();
		}
	}
	if ($mode == 0 || $mode == 2)
	{
		if ($res = QuerySql('SELECT COUNT(BSSID) FROM GEO_TABLE WHERE latitude IS NULL'))
		{
			$row = $res->fetch_row();
			$json['stat']['geoloc'] = (int)$row[0];
			$res->close();
		}
		if ($res = $db->query('SELECT COUNT(tid) FROM tasks WHERE tstate = 0'))
		{
			$row = $res->fetch_row();
			$json['stat']['tasks']['uploading'] = (int)$row[0];
			$res->close();
		}
		if ($res = $db->query('SELECT COUNT(tid) FROM tasks WHERE tstate > 0 AND tstate < 3'))
		{
			$row = $res->fetch_row();
			$json['stat']['tasks']['processing'] = (int)$row[0];
			$res->close();
		}
		if ($res = $db->query('SELECT comment FROM tasks WHERE tstate > 0 AND tstate < 3 ORDER BY created LIMIT 1'))
		{
			$row = $res->fetch_row();
			$json['stat']['tasks']['comment'] = $row[0];
			$res->close();
		}
	}
	$db->close();
	break;

	// Динамика загрузок
	case 'loads':
	set_time_limit(30);
	$json['result'] = true;
	date_default_timezone_set('UTC');
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	$json['data'] = array();
	if ($res = QuerySql('SELECT DATE_FORMAT(time,\'%Y.%m.%d\'), COUNT(id) FROM BASE_TABLE GROUP BY DATE_FORMAT(time,\'%Y%m%d\') ORDER BY id DESC LIMIT 30'))
	{
		while ($row = $res->fetch_row())
			$json['data'][] = array($row[0], (int)$row[1]);

		$res->close();
	}
	$db->close();
	$json['data'] = array_reverse($json['data']);
	break;

	// Комментарии
	case 'stcmt':
	set_time_limit(30);
	$json['result'] = true;
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql('SELECT `cmtid`, COUNT(cmtid) FROM BASE_TABLE GROUP BY `cmtid` ORDER BY COUNT(cmtid) DESC'))
	{
		$json['stat']['data'] = array();
		while ($row = $res->fetch_row())
		{
			$data = array();
			$data[] = (int)$row[1];
			$data[] = ($row[0] == null ? 'no comment' : getCommentVal((int)$row[0]));
			$json['stat']['data'][] = $data;
		}
		$res->close();
	}
	$db->close();
	break;

	// Названия устройств
	case 'stdev':
	set_time_limit(30);
	$json['result'] = true;
	$json['stat']['top'] = TOP_NAME;
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql("SELECT COUNT(DISTINCT `name`) FROM BASE_TABLE WHERE `name` != ''"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT `name`, COUNT(name) FROM BASE_TABLE WHERE `name` != '' GROUP BY `name` ORDER BY COUNT(name) DESC LIMIT ".TOP_NAME))
	{
		$json['stat']['data'] = array();
		while ($row = $res->fetch_row())
		{
			$data = array();
			$data[] = (int)$row[1];
			$data[] = $row[0];
			$json['stat']['data'][] = $data;
		}
		$res->close();
	}
	$db->close();
	break;

	// Порты
	case 'stport':
	set_time_limit(30);
	$json['result'] = true;
	$json['stat']['top'] = TOP_PORT;
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql("SELECT COUNT(DISTINCT `Port`) FROM BASE_TABLE WHERE NOT(`Port` IS NULL)"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT `Port`, COUNT(Port) FROM BASE_TABLE WHERE NOT(`Port` IS NULL) GROUP BY `Port` ORDER BY COUNT(Port) DESC LIMIT ".TOP_PORT))
	{
		$json['stat']['data'] = array();
		while ($row = $res->fetch_row())
		{
			$data = array();
			$data[] = (int)$row[1];
			$data[] = $row[0];
			$json['stat']['data'][] = $data;
		}
		$res->close();
	}
	$db->close();
	break;

	// Данные авторизации
	case 'stauth':
	set_time_limit(30);
	$json['result'] = true;
	$json['stat']['top'] = TOP_AUTH;
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql("SELECT COUNT(DISTINCT `Authorization`) FROM BASE_TABLE WHERE `Authorization`!=''"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT `Authorization`, COUNT(Authorization) FROM BASE_TABLE WHERE `Authorization`!='' GROUP BY `Authorization` ORDER BY COUNT(Authorization) DESC LIMIT ".TOP_AUTH))
	{
		$json['stat']['data'] = array();
		while ($row = $res->fetch_row())
		{
			$data = array();
			$data[] = (int)$row[1];
			$data[] = $row[0];
			$json['stat']['data'][] = $data;
		}
		$res->close();
	}
	$db->close();
	break;

	// BSSID точек доступа
	case 'stbss':
	set_time_limit(30);
	$json['result'] = true;
	$json['stat']['top'] = TOP_BSSID;
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql("SELECT COUNT(DISTINCT `BSSID`) FROM BASE_TABLE WHERE `NoBSSID`=0"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT `BSSID`, COUNT(BSSID) FROM BASE_TABLE WHERE `NoBSSID`=0 GROUP BY `BSSID` ORDER BY COUNT(BSSID) DESC LIMIT ".TOP_BSSID))
	{
		$json['stat']['data'] = array();
		while ($row = $res->fetch_row())
		{
			$data = array();
			$data[] = (int)$row[1];
			$data[] = dec2mac($row[0]);
			$json['stat']['data'][] = $data;
		}
		$res->close();
	}
	$db->close();
	break;

	// ESSID точек доступа
	case 'stess':
	set_time_limit(30);
	$json['result'] = true;
	$json['stat']['top'] = TOP_ESSID;
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql('SELECT COUNT(DISTINCT `ESSID`) FROM BASE_TABLE'))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql('SELECT `ESSID`, COUNT(ESSID) FROM BASE_TABLE GROUP BY `ESSID` ORDER BY COUNT(ESSID) DESC LIMIT '.TOP_ESSID))
	{
		$json['stat']['data'] = array();
		while ($row = $res->fetch_row())
		{
			$data = array();
			$data[] = (int)$row[1];
			$data[] = $row[0];
			$json['stat']['data'][] = $data;
		}
		$res->close();
	}
	$db->close();
	break;

	// Тип защиты точек доступа
	case 'stsec':
	set_time_limit(30);
	$json['result'] = true;
	$json['stat']['top'] = TOP_SECURITY;
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql('SELECT COUNT(DISTINCT `Security`) FROM BASE_TABLE'))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql('SELECT `Security`, COUNT(Security) FROM BASE_TABLE GROUP BY `Security` ORDER BY COUNT(Security) DESC LIMIT '.TOP_SECURITY))
	{
		$json['stat']['data'] = array();
		while ($row = $res->fetch_row())
		{
			$data = array();
			$data[] = (int)$row[1];
			$data[] = sec2str($row[0]);
			$json['stat']['data'][] = $data;
		}
		$res->close();
	}
	$db->close();
	break;

	// Ключи точек доступа
	case 'stkey':
	set_time_limit(30);
	$json['result'] = true;
	$json['stat']['top'] = TOP_WIFI_KEY;
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql('SELECT COUNT(DISTINCT `WiFiKey`) FROM BASE_TABLE'))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql('SELECT `WiFiKey`, COUNT(WiFiKey) FROM BASE_TABLE GROUP BY `WiFiKey` ORDER BY COUNT(WiFiKey) DESC LIMIT '.TOP_WIFI_KEY))
	{
		$json['stat']['data'] = array();
		while ($row = $res->fetch_row())
		{
			$data = array();
			$data[] = (int)$row[1];
			$data[] = $row[0];
			$json['stat']['data'][] = $data;
		}
		$res->close();
	}
	$db->close();
	break;

	// WPS пин коды точек доступа
	case 'stwps':
	set_time_limit(30);
	$json['result'] = true;
	$json['stat']['top'] = TOP_WPS_PIN;
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql('SELECT COUNT(DISTINCT `WPSPIN`) FROM BASE_TABLE WHERE `WPSPIN` != 1'))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql('SELECT `WPSPIN`, COUNT(WPSPIN) FROM BASE_TABLE WHERE `WPSPIN` != 1 GROUP BY `WPSPIN` ORDER BY COUNT(WPSPIN) DESC LIMIT '.TOP_WPS_PIN))
	{
		$json['stat']['data'] = array();
		while ($row = $res->fetch_row())
		{
			$data = array();
			$data[] = (int)$row[1];
			$data[] = str_pad($row[0], 8, '0', STR_PAD_LEFT);
			$json['stat']['data'][] = $data;
		}
		$res->close();
	}
	$db->close();
	break;

	// DNS серверы
	case 'stdns':
	set_time_limit(30);
	$json['result'] = true;
	$json['stat']['top'] = TOP_DNS;
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql("SELECT COUNT(DISTINCT DNS) FROM (
	SELECT DNS1 AS DNS FROM BASE_TABLE WHERE DNS1 != 0 
	UNION ALL 
	SELECT DNS2 AS DNS FROM BASE_TABLE WHERE DNS2 != 0 
	UNION ALL 
	SELECT DNS3 AS DNS FROM BASE_TABLE WHERE DNS3 != 0) DNSTable"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT DNS, COUNT(DNS) FROM (
	SELECT DNS1 AS DNS FROM BASE_TABLE WHERE DNS1 != 0 
	UNION ALL 
	SELECT DNS2 AS DNS FROM BASE_TABLE WHERE DNS2 != 0 
	UNION ALL 
	SELECT DNS3 AS DNS FROM BASE_TABLE WHERE DNS3 != 0) DNSTable 
	GROUP BY DNS ORDER BY COUNT(DNS) DESC LIMIT ".TOP_DNS))
	{
		$json['stat']['data'] = array();
		while ($row = $res->fetch_row())
		{
			$data = array();
			$data[] = (int)$row[1];
			$data[] = _long2ip($row[0]);
			$json['stat']['data'][] = $data;
		}
		$res->close();
	}
	$db->close();
	break;

	// Активные участники (Сидеры)
	case 'stsid':
	set_time_limit(30);
	$json['result'] = true;
	$json['stat']['top'] = TOP_SSID;

	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}

	if ($res = $db->query('SELECT COUNT(uid) FROM users'))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}

	if ($res = $db->query('SELECT nick, COUNT(id) FROM uploads LEFT JOIN users USING(uid) GROUP BY uploads.uid ORDER BY COUNT(id) DESC LIMIT '.TOP_SSID))
	{
		$json['stat']['data'] = array();
		while ($row = $res->fetch_row())
		{
			$data = array();
			$data[] = (int)$row[1];
			$data[] = $row[0];
			$json['stat']['data'][] = $data;
		}
		$res->close();
	}
	$db->close();
	break;

	// Получение API ключей по логину и паролю
	case 'apikeys':
	$json['result'] = false;
	$mode = explode(';', $_SERVER["CONTENT_TYPE"]);
	$mode = trim($mode[0]);
	if ($mode == 'application/x-www-form-urlencoded')
	{
		$data = $_POST;
	}
	elseif ($mode == 'application/json')
	{
		$data = json_decode(file_get_contents('php://input'), true);
	}
	$login = (isset($data) ? $data['login'] : null);
	$password = (isset($data) ? $data['password'] : null);
	$genread = (isset($data) ? (bool)$data['genread'] : false);
	$genwrite = (isset($data) ? (bool)$data['genwrite'] : false);
	if (!is_null($login) && !is_null($password))
	{
		filterLogin($login);
		if (!$UserManager->Auth($password, $login, true))
		{
			$json['error'] = 'loginfail';
			break;
		}
		$json['profile'] = array(
			'nick' => $UserManager->Nick,
			'regdate' => $UserManager->RegDate,
			'level' => (int)$UserManager->Level,
		);
		$data = $UserManager->getApiKeys();
		if (is_null($data['rapikey']) && $genread)
		{
			$data['rapikey'] = $UserManager->createApiKey(1);
		}
		if (is_null($data['wapikey']) && $genwrite)
		{
			$data['wapikey'] = $UserManager->createApiKey(2);
		}
		$json['data'] = array();
		if ($data['rapikey'])
			$json['data'][] = array('key' => $data['rapikey'], 'access' => 'read');
		if ($data['wapikey'])
			$json['data'][] = array('key' => $data['wapikey'], 'access' => 'write');
		$json['result'] = true;
	}
	else
	{
		$json['error'] = 'form';
	}
	break;

	// API поиск точек доступа
	case 'apiquery':
	$json['result'] = false;
	$mode = explode(';', $_SERVER["CONTENT_TYPE"]);
	$mode = trim($mode[0]);
	if ($mode == 'application/x-www-form-urlencoded')
	{
		$data = $_POST;
	}
	elseif ($mode == 'application/json')
	{
		$data = json_decode(file_get_contents('php://input'), true);
	}
	else
	{
		$data = $_REQUEST;
	}
	$key = (isset($data) ? $data['key'] : null);
	$bssid = (isset($data) ? $data['bssid'] : null);
	$essid = (isset($data) ? $data['essid'] : null);
	$sens = isset($data) && isset($data['sens']) && in_array($data['sens'], array('1', 'on', 'true', 1, true), true);
	if (is_string($bssid) && strlen($bssid))
		$bssid = array($bssid);
	if (is_string($essid) && strlen($essid))
		$essid = array($essid);
	if (is_null($key) || empty($key) ||
		!is_array($bssid) || !count($bssid))
	{
		$json['error'] = 'form';
		break;
	}
	$find_essid = is_array($essid);
	if ($find_essid && count($bssid) != count($essid))
	{
		$json['error'] = 'form';
		break;
	}
	if (count($bssid) > 100)
	{
		$json['error'] = 'limit';
		break;
	}
	if (!$UserManager->AuthByApiKey($key, true))
	{
		$json['error'] = 'loginfail';
		break;
	}
	if ($UserManager->ApiAccess != 'read')
	{
		$json['error'] = 'lowlevel';
		break;
	}
	if (!db_connect())
	{
		$json['error'] = 'database';
		break;
	}
	$json['data'] = array();
	foreach ($bssid as $i => $mac)
	{
		if (ismac($mac))
		{
			$mac = mac2dec($mac);
			$where = "BSSID = $mac";
			$ess = '';
			if ($find_essid && !empty($essid[$i]))
			{
				$ess = $db->real_escape_string($essid[$i]);
				if ($sens)
				{
					$where .= " AND BINARY ESSID = '$ess'";
				}
				else
				{
					$where .= " AND ESSID = '$ess'";
				}
			}
			$sql = "SELECT 
						time, BSSID, ESSID, Security, WiFiKey, WPSPIN, latitude, longitude 
					FROM 
						BASE_TABLE 
						INNER JOIN GEO_TABLE USING(BSSID) 
					WHERE 
						$where 
					ORDER BY 
						time DESC 
					LIMIT 10";
			$res = QuerySql($sql);
			if (!$res) continue;
			$data = array();
			while ($row = $res->fetch_assoc())
			{
				$entry = array(
					'time'  => $row['time'],
					'bssid' => dec2mac($row['BSSID']),
					'essid' => $row['ESSID'],
					'sec'   => sec2str($row['Security']),
					'key'   => $row['WiFiKey'],
					'wps'   => ($row['WPSPIN'] == 1 ? '' : str_pad($row['WPSPIN'], 8, '0', STR_PAD_LEFT)),
					'lat'   => (float)$row['latitude'],
					'lon'   => (float)$row['longitude'],
				);
				if ($entry['lat'] == 0 && $entry['lon'] == 0)
				{
					unset($entry['lat']);
					unset($entry['lon']);
				}
				$data[] = $entry;
			}
			if (count($data))
				$json['data'][dec2mac($mac).($ess == '' ? '' : '|'.$essid[$i])] = $data;
		}
		else
		{
			if (!$find_essid || empty($essid[$i])) continue;
			$ess = $db->real_escape_string($essid[$i]);
			if ($sens)
			{
				$where = "BINARY ESSID = '$ess'";
			}
			else
			{
				$where = "ESSID = '$ess'";
			}
			$sql = "SELECT 
						time, NoBSSID, BSSID, ESSID, Security, WiFiKey, WPSPIN, latitude, longitude 
					FROM 
						BASE_TABLE 
						INNER JOIN GEO_TABLE USING(BSSID) 
					WHERE 
						$where 
					ORDER BY 
						time DESC 
					LIMIT 10";
			$res = QuerySql($sql);
			if (!$res) continue;
			$data = array();
			while ($row = $res->fetch_assoc())
			{
				$entry = array(
					'time'  => $row['time'],
					'bssid' => ($row['NoBSSID'] ? '' : dec2mac($row['BSSID'])),
					'essid' => $row['ESSID'],
					'sec'   => sec2str($row['Security']),
					'key'   => $row['WiFiKey'],
					'wps'   => ($row['WPSPIN'] == 1 ? '' : str_pad($row['WPSPIN'], 8, '0', STR_PAD_LEFT)),
					'lat'   => (float)$row['latitude'],
					'lon'   => (float)$row['longitude'],
				);
				if ($entry['lat'] == 0 && $entry['lon'] == 0)
				{
					unset($entry['lat']);
					unset($entry['lon']);
				}
				$data[] = $entry;
			}
			if (count($data))
				$json['data']['*|'.$essid[$i]] = $data;
		}
	}
	$json['result'] = true;
	$db->close();
	break;

	// API определение WPS PIN по MAC
	case 'apiwps':
	$json['result'] = false;
	$mode = explode(';', $_SERVER["CONTENT_TYPE"]);
	$mode = trim($mode[0]);
	if ($mode == 'application/x-www-form-urlencoded')
	{
		$data = $_POST;
	}
	elseif ($mode == 'application/json')
	{
		$data = json_decode(file_get_contents('php://input'), true);
	}
	else
	{
		$data = $_REQUEST;
	}
	$key = (isset($data) ? $data['key'] : null);
	$bssid = (isset($data) ? $data['bssid'] : null);
	if (is_string($bssid) && strlen($bssid))
		$bssid = array($bssid);
	if (is_null($key) || empty($key) ||
		!is_array($bssid) || !count($bssid))
	{
		$json['error'] = 'form';
		break;
	}
	if (count($bssid) > 100)
	{
		$json['error'] = 'limit';
		break;
	}
	if (!$UserManager->AuthByApiKey($key, true))
	{
		$json['error'] = 'loginfail';
		break;
	}
	if ($UserManager->ApiAccess != 'read')
	{
		$json['error'] = 'lowlevel';
		break;
	}
	if (!db_connect())
	{
		$json['error'] = 'database';
		break;
	}
	require_once 'wpspin.php';
	$json['data'] = array();
	foreach ($bssid as $i => $mac)
	{
		$mac = preg_replace('/[^0-9A-Fa-f]/', '', $mac);
		if (strlen($mac) != 12)
			continue;
		$data = API_pin_search($mac);
		if (count($data['scores']))
			$json['data'][dec2mac(mac2dec($mac))] = $data;
	}
	$json['result'] = true;
	$db->close();
	break;

	// API определение диапазонов
	case 'apiranges':
	$json['result'] = false;
	$mode = explode(';', $_SERVER["CONTENT_TYPE"]);
	$mode = trim($mode[0]);
	if ($mode == 'application/x-www-form-urlencoded')
	{
		$data = $_POST;
	}
	elseif ($mode == 'application/json')
	{
		$data = json_decode(file_get_contents('php://input'), true);
	}
	else
	{
		$data = $_REQUEST;
	}
	$key = (isset($data) ? $data['key'] : null);
	$lat = (isset($data) ? $data['lat'] : null);
	$lon = (isset($data) ? $data['lon'] : null);
	$rad = (isset($data) ? $data['rad'] : null);
	if (is_null($key) || empty($key) ||
		is_null($lat) || empty($lat) ||
		is_null($lon) || empty($lon) ||
		is_null($rad) || empty($rad) )
	{
		$json['error'] = 'form';
		break;
	}
	$lat = (float)$lat;
	$lon = (float)$lon;
	$rad = (float)$rad;
	if ($lat < -90 || $lat > 90)
	{
		$json['error'] = 'form';
		break;
	}
	if ($lon < -180 || $lon > 180)
	{
		$json['error'] = 'form';
		break;
	}
	if ($rad < 0 || $rad > 25)
	{
		$json['error'] = 'form';
		break;
	}
	if (!$UserManager->AuthByApiKey($key, true))
	{
		$json['error'] = 'loginfail';
		break;
	}
	if ($UserManager->ApiAccess != 'read')
	{
		$json['error'] = 'lowlevel';
		break;
	}
	if (!db_connect())
	{
		$json['error'] = 'database';
		break;
	}
	require 'ipext.php';
	$json['result'] = true;
	$json['data'] = API_get_ranges($lat, $lon, $rad);
	$db->close();
	break;
}

if ($action != 'hash') session_write_close();
$time = microtime(true) - $time;
$json['time'] = $time;

Header('Content-Type: application/json');
echo json_encode($json);
?>