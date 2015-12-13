<?php
if (!isset($_GET['a']))
{
	Header('HTTP/1.0 303 See Other');
	Header('Location: /');
	exit;
}
require_once 'user.class.php';
require_once 'utils.php';
require_once 'db.php';
require_once 'quadkey.php';

$UserManager = new User();
$UserManager->loadSession();

$topPort = 30;
$topauth = 100;
$topname = 30;
$topbssid = 30;
$topessid = 30;
$topSecurity = 30;
$topWiFiKey = 30;
$topWPSPIN = 30;
$topDNS = 30;
$topSid = 10;

$action = $_GET['a'];

$time = microtime(true);
$json = array();
$json['result'] = false;

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
	list($tile_x1, $tile_y1, $tile_x2, $tile_y2) = explode(',', $_GET['tileNumber']);
	$zoom = $_GET['zoom'];
	$callback = $_GET['callback'];

	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}

	$res = get_clusters($db, $tile_x1, $tile_y1, $tile_x2, $tile_y2, $zoom);

	unset($json); // здесь используется JSON-P
	Header('Content-Type: application/json-p');
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

		$ap['properties']['hintContent'] = '';
		if (!empty($cluster['bssids']))
		{
			$hints = array();
			foreach ($cluster['bssids'] as $bssid)
			{
				if (!$get_info_stmt->execute()) continue;

				$res = $get_info_stmt->get_result();
				while ($row = $res->fetch_assoc())
				{
					$aphint = array();

					$xtime = $row['time'];
					$xbssid = htmlspecialchars(dec2mac($bssid));
					$xessid = htmlspecialchars($row['ESSID']);
					$xwifikey = htmlspecialchars($row['WiFiKey']);

					if ($UserManager->Level >= 0) $aphint[] = $xtime;
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
	echo "typeof $callback === 'function' && $callback(".json_encode($json).");";
	exit;
	break;

	// Поиск по базе
	case 'find':
	$json['result'] = true;
	$json['auth'] = $UserManager->Level >= 0;
	if (!$json['auth']) break;

	function GenerateFindQuery($cmtid, $BSSID, $ESSID, $Name, $Key, $Page, $Limit)
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

		if(($SplitCount < $UnkCount || $TestBSSID == '*' || $BSSID == '') && ($ESSID == '%' || $ESSID == ''))
		{
			$isLimitedRequest = true;
		}

		if($Page == 1) 
		{
			$_SESSION['Search']['FirstId'] = -1;
			$_SESSION['Search']['LastId'] = -1;
		}

		$sql = 'SELECT SQL_CALC_FOUND_ROWS 
				`id`,`time`,
				`cmtid`,`cmtval`,
				`IP`,`Port`,`Authorization`,`name`,
				`NoBSSID`,`BSSID`,`ESSID`,`Security`,
				`WiFiKey`,`WPSPIN`,`WANIP`,
				`latitude`,`longitude` 
				FROM `BASE_TABLE` 
				LEFT JOIN `comments` USING(cmtid) 
				LEFT JOIN `GEO_TABLE` USING(BSSID) 
				WHERE 1';
		if ($cmtid != -1)
		{
			$sql .= ' AND (`cmtid` '.($cmtid == 0 ? 'IS NULL)' : "= $cmtid)");
		}
		if ($BSSID != '')
		{
			if (StrInStr($BSSID, '*'))
			{
				$mmac = mac2dec(mac_mask($BSSID));
				$mask = mac2dec(mac_mask($BSSID, false));
				$sql .= " AND (`BSSID` & $mask = $mmac)";
			}
			else $sql .= ' AND `BSSID` = '.mac2dec($BSSID).'';
		}
		if ($ESSID != '')
		{
			if (StrInStr($ESSID, '%') || StrInStr($ESSID, '_')) $sql .= ' AND `ESSID` LIKE \''.$ESSID.'\'';
			else $sql .= ' AND `ESSID` = \''.$ESSID.'\'';
		}
		if ($Name != '')
		{
			if (StrInStr($Name, '%') || StrInStr($Name, '_')) $sql .= ' AND `name` LIKE \''.$Name.'\'';
			else $sql .= ' AND `name` = \''.$Name.'\'';
		}
		if ($Key != '')
		{
			if (StrInStr($Key, '%') || StrInStr($Key, '_')) $sql .= ' AND `WiFiKey` LIKE \''.$Key.'\'';
			else $sql .= ' AND `WiFiKey` = \''.$Key.'\'';
		}

		if($_SESSION['Search']['ArgsHash'] == md5($cmtid.$BSSID.$ESSID.$Name.$Key))
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

		$sql .= ' AND `id` '.$Sign.' '.$NextPageStartId.' LIMIT '.($DiffPage*100).', '.$Limit;

		$_SESSION['Search']['ArgsHash'] = md5($BSSID.$ESSID.$Name);
		$_SESSION['Search']['LastPage'] = $Page;

		return $sql;
	}
	$comment = '*';
	$cmtid = -1;
	$ipaddr = '';
	$auth = '%';
	$name = '%';
	$bssid = '';
	$essid = '%';
	$key = '%';
	$wps = '%';
	if (isset($_POST['bssid'])) $bssid = $_POST['bssid'];
	if (isset($_POST['essid'])) $essid = $_POST['essid'];
	$bssid = str_replace('-', ':', $bssid);
	if ($UserManager->Level > 1)
	{
		if (isset($_POST['comment'])) $comment = $_POST['comment'];
		if (isset($_POST['ipaddr'])) $ipaddr = $_POST['ipaddr'];
		if (isset($_POST['auth'])) $auth = $_POST['auth'];
		if (isset($_POST['name'])) $name = $_POST['name'];
		if (isset($_POST['key'])) $key = $_POST['key'];
		if (isset($_POST['wps'])) $wps = $_POST['wps'];
	}
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
			$res = $db->query("SELECT `cmtid` FROM comments WHERE `cmtval`='$comment'");
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
	$ipaddr = _ip2long($db->real_escape_string($ipaddr));
	$auth = $db->real_escape_string($auth);
	$name = $db->real_escape_string($name);
	$bssid = $db->real_escape_string($bssid);
	$essid = $db->real_escape_string($essid);
	$key = $db->real_escape_string($key);
	$wps = $db->real_escape_string($wps);

	$cur_page = 1;
	$per_page = 100;
	if (isset($_POST['page'])) $cur_page = (int)$_POST['page'];
	if ($cur_page < 1) $cur_page = 1;

	$sql = GenerateFindQuery($cmtid, $bssid, $essid, $name, $key, $cur_page, $per_page);
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
		while ($row = $res->fetch_row())
		{
			if ($FirstId == -1) $FirstId = (int)$row[0];
			$LastId = (int)$row[0];

			$entry = array();
			if ($UserManager->Level > 1) $entry['id'] = (int)$row[0];
			$entry['time'] = $row[1];
			$entry['comment'] = ($row[2] == null ? '' : $row[3]);
			$ip = _long2ip($row[4]);
			$wanip = _long2ip($row[14]);
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
				if ($entry['ipport'] != '' && $row[5] != null) $entry['ipport'] .= ':'.$row[5];
				$entry['auth'] = $row[6];
				$entry['name'] = $row[7];
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
			$entry['bssid'] = '';
			if ((int)$row[8] == 0) $entry['bssid'] = dec2mac($row[9]);
			$entry['essid'] = $row[10];
			$entry['sec'] = sec2str((int)$row[11]);
			$entry['key'] = $row[12];
			$entry['wps'] = ($row[13] == 1 ? '' : str_pad($row[13], 8, '0', STR_PAD_LEFT));
			$entry['lat'] = 'none';
			$entry['lon'] = 'none';
			if ((int)$row[8] == 0 && $row[15] !== null)
			{
				$entry['lat'] = (float)$row[15];
				$entry['lon'] = (float)$row[16];
				if ($entry['lat'] == 0 && $entry['lon'] == 0)
				{
					$entry['lat'] = 'not found';
					$entry['lon'] = 'not found';
				}
			}

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
		$json['error'] = 'Введите значение широты';
		break;
	}
	if ($lon == '')
	{
		$json['error'] = 'Введите значение долготы';
		break;
	}
	$lat = (float)$lat;
	$lon = (float)$lon;
	if ($lat < -90 || $lat > 90)
	{
		$json['error'] = 'Значение широты должно лежать в диапазоне [-90;90]';
		break;
	}
	if ($lon < -180 || $lon > 180)
	{
		$json['error'] = 'Значение долготы должно лежать в диапазоне [-180;180]';
		break;
	}

	$radius = '';
	if (isset($_POST['radius'])) $radius = $_POST['radius'];
	if ($radius == '')
	{
		$json['error'] = 'Введите значение радиуса поиска';
		break;
	}
	$radius = (float)$radius;
	if ($radius < 0 || $radius > 25)
	{
		$json['error'] = 'Значение радиуса поиска должно лежать в диапазоне (0;25]';
		break;
	}

	$lat_km = 111.143 - 0.562 * cos(2 * deg2rad($lat));
	$lon_km = 111.321 * cos(deg2rad($lon)) - 0.094 * cos(3 * deg2rad($lon));
	$lat1 = min(max($lat - $radius / $lat_km, -90), 90);
	$lat2 = min(max($lat + $radius / $lat_km, -90), 90);
	$lon1 = min(max($lon - $radius / $lon_km, -180), 180);
	$lon2 = min(max($lon + $radius / $lon_km, -180), 180);
	$tile_x1 = lon_to_tile_x($lon1, 7);
	$tile_y1 = lat_to_tile_y($lat2, 7);
	$tile_x2 = lon_to_tile_x($lon2, 7);
	$tile_y2 = lat_to_tile_y($lat1, 7);
	$quadkeys = get_quadkeys_for_tiles($tile_x1, $tile_y1, $tile_x2, $tile_y2, 7);
	$quadkeys = '(' . implode(',', array_map(function($x){return base_convert($x, 2, 10);}, $quadkeys)) . ')';
	$json['data'] = array();
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql(
		"SELECT DISTINCT IP FROM 
		(SELECT IP 
		FROM `BASE_TABLE`, `GEO_TABLE` 
		WHERE (`GEO_TABLE`.`quadkey` >> 32) IN $quadkeys AND
				`BASE_TABLE`.`BSSID` = `GEO_TABLE`.`BSSID` 
				AND (`GEO_TABLE`.`quadkey` IS NOT NULL) 
				AND (`GEO_TABLE`.`latitude` BETWEEN $lat1 AND $lat2 AND `GEO_TABLE`.`longitude` BETWEEN $lon1 AND $lon2) 
				AND (IP != 0 AND IP != -1) 
		UNION SELECT WANIP 
		FROM `BASE_TABLE`, `GEO_TABLE` 
		WHERE (`GEO_TABLE`.`quadkey` >> 32) IN $quadkeys AND
				`BASE_TABLE`.`BSSID` = `GEO_TABLE`.`BSSID` 
				AND (`GEO_TABLE`.`quadkey` IS NOT NULL) 
				AND (`GEO_TABLE`.`latitude` BETWEEN $lat1 AND $lat2 AND `GEO_TABLE`.`longitude` BETWEEN $lon1 AND $lon2) 
				AND (WANIP != 0 AND WANIP != -1)
		) IPTable ORDER BY CAST(IP AS UNSIGNED INTEGER)"));
	{
		require 'ipext.php';
		$last_upper = '0.0.0.0';
		while ($row = $res->fetch_row())
		{
			$row[0] = (int)$row[0];
			if (compare_ip(long2ip($row[0]), $last_upper) <= 0)
			{
				continue;
			}
			$ip_range = GetIPRange($db, $row[0]);
			if(is_null($ip_range))
			{
				continue;
			}
			$last_upper = $ip_range['endIP'];
			$json['data'][] = array(
				'range' => pretty_range($ip_range['startIP'], $ip_range['endIP']),
				'descr' => $ip_range['descr']);
		}
		$res->close();
		usort($json['data'], function($a, $b) { return strcmp($a['descr'], $b['descr']); });
		array_unique($json['data']);
	}
	$db->close();
	break;

	// Определение устройства по MAC
	case 'devicemac':
	$json['result'] = true;
	$json['auth'] = $UserManager->Level >= 0;
	if (!$json['auth']) break;

	$bssid = '';
	if (isset($_POST['bssid'])) $bssid = $_POST['bssid'];
	$oui = mac2dec($bssid);
	$oui = bcdiv($oui, bcpow('2', '24')); // XX:XX:XX:XX:XX:XX => XX:XX:XX:00:00:00
	$oui = bcmul($oui, bcpow('2', '24'));
	$mask = '281474959933440'; // FF:FF:FF:00:00:00
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	$oui = $db->real_escape_string($oui);
	if ($res = QuerySql("SELECT `BSSID`,`name` FROM `BASE_TABLE` WHERE (`NoBSSID` = 0 AND `BSSID` & $mask = $oui) AND `name` != ''"))
	{
		$devs = array();
		while ($row = $res->fetch_row())
		{
			$bss = dec2mac($row[0]);
			$name = $row[1];

			if (!isset($devs[$name]))
			{
				$devs[$name][12] = 0;
				$devs[$name][11] = 0;
				$devs[$name][10] = 0;
				$devs[$name][9] = 0;
				$devs[$name][8] = 0;
				$devs[$name][7] = 0;
				$devs[$name][6] = 0;
			}
			$match = 6;
			for ($i = 9; $i < strlen($bss); $i++)
			{
				if ($i == 11 || $i == 14) $i++; // ':'
				if ($bssid[$i] == $bss[$i])
				{
					$match++;
				} else break;
			}
			$devs[$name][$match] += 1;
		}
		$res->close();
	}
	$db->close();
	$scores = array();
	foreach($devs as $name => $match)
	{
		$val =
		$match[12] * 2048 + $match[11] * 1024 +
		$match[10] * 256 + $match[9] * 64 +
		$match[8] * 16 + $match[7] * 8 + $match[6] * 4;
		$scores[$name] = (($val * 512) ^ (1/2)) / ((($val * 512) + 1048576) ^ (1/2));
	}
	arsort($scores);
	$scores = array_slice($scores, 0, 8);

	$json['scores'] = array();
	foreach($scores as $name => $score)
	{
		$entry = array();
		$entry['name'] = $name;
		$entry['score'] = $score;
		$json['scores'][] = $entry;
	}
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
				$uid = $UserManager->uID;
				if (is_null($uid) || $UserManager->Level < 1) $uid = 'NULL';
				if ($db->query("INSERT INTO tasks (`tid`,`created`,`modified`,`ext`,`comment`,`checkexist`,`nowait`,`uid`) VALUES ('$tid',now(),now(),'$ext','$comment',$checkexist,$nowait,$uid)"))
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
					$db->query("UPDATE tasks SET `modified`=now(),`comment`='$comment',`checkexist`=$checkexist,`nowait`=$nowait WHERE `tid`='$tid')");
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
			$json['upload']['processing'] = $db->query("UPDATE tasks SET `tstate`=1 WHERE `tid`='$tid'");
		}
		$db->close();
	} else
		$error[] = 1; // Неверные заголовки или размер данных
	$json['upload']['error'] = $error;
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
	$json['result'] = true;
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql('SELECT `cmtid`, COUNT(*) FROM BASE_TABLE GROUP BY `cmtid` ORDER BY COUNT(*) DESC'))
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
	$json['result'] = true;
	$json['stat']['top'] = $topname;
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
	if ($res = QuerySql("SELECT `name`, COUNT(*) FROM BASE_TABLE WHERE `name` != '' GROUP BY `name` ORDER BY COUNT(*) DESC LIMIT $topname"))
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
	$json['result'] = true;
	$json['stat']['top'] = $topPort;
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
	if ($res = QuerySql("SELECT `Port`, COUNT(*) FROM BASE_TABLE WHERE NOT(`Port` IS NULL) GROUP BY `Port` ORDER BY COUNT(*) DESC LIMIT $topPort"))
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
	$json['result'] = true;
	$json['stat']['top'] = $topauth;
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
	if ($res = QuerySql("SELECT `Authorization`, COUNT(*) FROM BASE_TABLE WHERE `Authorization`!='' GROUP BY `Authorization` ORDER BY COUNT(*) DESC LIMIT $topauth"))
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
	$json['result'] = true;
	$json['stat']['top'] = $topbssid;
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
	if ($res = QuerySql("SELECT `BSSID`, COUNT(*) FROM BASE_TABLE WHERE `NoBSSID`=0 GROUP BY `BSSID` ORDER BY COUNT(*) DESC LIMIT $topbssid"))
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
	$json['result'] = true;
	$json['stat']['top'] = $topessid;
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql("SELECT COUNT(DISTINCT `ESSID`) FROM BASE_TABLE"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT `ESSID`, COUNT(*) FROM BASE_TABLE GROUP BY `ESSID` ORDER BY COUNT(*) DESC LIMIT $topessid"))
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
	$json['result'] = true;
	$json['stat']['top'] = $topSecurity;
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql("SELECT COUNT(DISTINCT `Security`) FROM BASE_TABLE"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT `Security`, COUNT(*) FROM BASE_TABLE GROUP BY `Security` ORDER BY COUNT(*) DESC LIMIT $topSecurity"))
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
	$json['result'] = true;
	$json['stat']['top'] = $topWiFiKey;
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql("SELECT COUNT(DISTINCT `WiFiKey`) FROM BASE_TABLE"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT `WiFiKey`, COUNT(*) FROM BASE_TABLE GROUP BY `WiFiKey` ORDER BY COUNT(*) DESC LIMIT $topWiFiKey"))
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
	$json['result'] = true;
	$json['stat']['top'] = $topWPSPIN;
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql("SELECT COUNT(DISTINCT `WPSPIN`) FROM BASE_TABLE WHERE `WPSPIN` != 1"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT `WPSPIN`, COUNT(*) FROM BASE_TABLE WHERE `WPSPIN` != 1 GROUP BY `WPSPIN` ORDER BY COUNT(*) DESC LIMIT $topWPSPIN"))
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
	$json['result'] = true;
	$json['stat']['top'] = $topDNS;
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
	GROUP BY DNS ORDER BY COUNT(DNS) DESC LIMIT $topDNS"))
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
	$json['result'] = true;
	$json['stat']['top'] = $topSid;

	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}

	if ($res = $db->query("SELECT COUNT(*) FROM users"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}

	// fixit!!!!!!!!
	if ($res = $db->query('SELECT nick, 0 FROM users WHERE 1'))
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
}

session_write_close();
$time = microtime(true) - $time;
$json['time'] = $time;

Header('Content-Type: application/json');
echo json_encode($json);
?>