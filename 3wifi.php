<?php
if (!isset($_GET['a']))
{
	Header('HTTP/1.0 303 See Other');
	Header('Location: /');
	exit;
}
require 'auth.php';
require 'utils.php';
require 'db.php';
session_start();

$topPort = 10;
$topauth = 100;
$topname = 30;
$topbssid = 30;
$topessid = 30;
$topSecurity = 30;
$topWiFiKey = 30;
$topWPSPIN = 30;
$topDNS = 30;

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

	// Координаты точек на карте
	case 'map':
	$bbox = explode(",", $_GET['bbox']);
	$callback = $_GET['callback'];

	$lat1 = (float)$bbox[0];
	$lon1 = (float)$bbox[1];
	$lat2 = (float)$bbox[2];
	$lon2 = (float)$bbox[3];

	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql("SELECT `id`,`time`,`GEO_TABLE`.`BSSID`,`ESSID`,`WiFiKey`,`latitude`,`longitude` FROM `GEO_TABLE`, `BASE_TABLE` WHERE 
						(`latitude` != 0 AND `longitude` != 0) 
						AND (`latitude` BETWEEN $lat1 AND $lat2 AND `longitude` BETWEEN $lon1 AND $lon2) 
						AND `BASE_TABLE`.`BSSID` = `GEO_TABLE`.`BSSID` LIMIT 2500"))
	{
		unset($json); // здесь используется JSON-P
		$data = array();
		while ($row = $res->fetch_row())
		{
			$xlatitude = $row[5];
			$xlongitude = $row[6];
			if (!isset($data[$xlatitude][$xlongitude])) $data[$xlatitude][$xlongitude] = array();
			$i = count($data[$xlatitude][$xlongitude]);
			$data[$xlatitude][$xlongitude][$i]['id'] = (int)$row[0];
			$data[$xlatitude][$xlongitude][$i]['time'] = $row[1];
			$data[$xlatitude][$xlongitude][$i]['bssid'] = dec2mac($row[2]);
			$data[$xlatitude][$xlongitude][$i]['essid'] = $row[3];
			$data[$xlatitude][$xlongitude][$i]['key'] = $row[4];
		}
		$res->close();
		$db->close();

		Header("Content-Type: application/json-p");
		$json['error'] = null;
		$json['data']['type'] = 'FeatureCollection';
		$json['data']['features'] = array();
		$ap['type'] = 'Feature';
		foreach($data as $xlatitude => $xlongitude)
		foreach($xlongitude as $xlongitude => $apdata)
		{
			$ap['id'] = $apdata[0]['id'];
			$ap['geometry']['type'] = 'Point';
			$ap['geometry']['coordinates'][0] = (float)$xlatitude;
			$ap['geometry']['coordinates'][1] = (float)$xlongitude;

			$hint = array();
			for ($i = 0; $i < count($apdata); $i++)
			{
				$aphint = array();

				$xtime = $apdata[$i]['time'];
				$xbssid = htmlspecialchars($apdata[$i]['bssid']);
				$xessid = htmlspecialchars($apdata[$i]['essid']);
				$xwifikey = htmlspecialchars($apdata[$i]['key']);

				if ($level > 0) $aphint[] = $xtime;
				$aphint[] = $xbssid;
				$aphint[] = $xessid;
				if ($level > 0) $aphint[] = $xwifikey;
				$hint[] = implode('<br>', $aphint);
			}
			$ap['properties']['hintContent'] = implode('<hr>', $hint);

			$json['data']['features'][] = $ap;
		}
		echo "typeof $callback === 'function' && $callback(".json_encode($json).");";
		exit;
	}
	break;

	// Поиск по базе
	case 'find':
	$json['result'] = true;
	$json['auth'] = $level > 0;
	if ($level == 0) break;

	function GenerateFindQuery($comment, $BSSID, $ESSID, $Name, $Page, $Limit)
	{
		if(!isset($_SESSION['Search'])) $_SESSION['Search'] = array();
		if(!isset($_SESSION['Search']['ArgsHash'])) $_SESSION['Search']['ArgsHash'] = '';
		if(!isset($_SESSION['Search']['LastRowsNum'])) $_SESSION['Search']['LastRowsNum'] = -1;
		if(!isset($_SESSION['Search']['LastId'])) $_SESSION['Search']['FirstId'] = -1;
		if(!isset($_SESSION['Search']['LastId'])) $_SESSION['Search']['LastId'] = -1;
		if(!isset($_SESSION['Search']['LastPage'])) $_SESSION['Search']['LastPage'] = 1;

		$DiffPage = 0;
		$NextPageStartId = 0;

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
				`WiFiKey`,`WPSPIN`,
				`latitude`,`longitude` 
				FROM `BASE_TABLE` 
				LEFT JOIN `comments` USING(cmtid) 
				LEFT JOIN `GEO_TABLE` USING(BSSID) 
				WHERE 1';
		if ($comment != '*')
		{
			$sql .= ' AND (`cmtid` '.($comment == '' ? 'IS NULL)' : "= $comment)");
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
			if(StrInStr($ESSID, '%') || StrInStr($ESSID, '_')) $sql .= ' AND `ESSID` LIKE \''.$ESSID.'\'';
			else $sql .= ' AND `ESSID` = \''.$ESSID.'\'';
		}
		if($Name != '')
		{
			$sql .= ' AND `name` LIKE \''.$Name.'\'';
		}

		if($_SESSION['Search']['ArgsHash'] == md5($BSSID.$ESSID.$Name))
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

		$DiffPage = ((int)$Page-$_SESSION['Search']['LastPage']);
		$DiffPage *= 100;

		if($_SESSION['Search']['LastId'] == -1 || $_SESSION['Search']['FirstId'] == -1) 
		{
			$NextPageStartId = 4294967295;
		}
		else
		{
			$NextPageStartId = ((int)$_SESSION['Search']['LastId'])-$DiffPage;
		}
		$sql .= ' AND `id` < '.$NextPageStartId.' LIMIT '.$Limit;

		$_SESSION['Search']['LastPage'] = $Page;
		$_SESSION['Search']['ArgsHash'] = md5($BSSID.$ESSID.$Name);
		$_SESSION['Search']['LastId'] = -1;

		return $sql;
	}
	$comment = '*';
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
	if ($level > 1)
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

	if ($comment != '' && $comment != '*')
	{
		$comment = $db->real_escape_string($comment);
		$res = QuerySql("SELECT `cmtid` FROM comments WHERE `cmtval`='$comment'");
		if ($res->num_rows > 0)
		{
			$row = $res->fetch_row();
			$cmtid = $row[0];
		}
		else
		{
			$res->close();
			break;
		}
		$res->close();
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

	$sql = GenerateFindQuery($comment, $bssid, $essid, $name, $cur_page, $per_page);
	if ($res = QuerySql($sql))
	{
		if($_SESSION['Search']['LastRowsNum'] == -1)
		{
			$res_rows = QuerySql("SELECT FOUND_ROWS()");
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
		while ($row = $res->fetch_row())
		{
			$entry = array();
			if ($level > 1) $entry['id'] = (int)$row[0];
			$entry['time'] = $row[1];
			$entry['comment'] = ($row[2] == null ? '' : $row[3]);
			if ($level > 1)
			{
				$entry['ipport'] = '';
				if ($row[4] != '') $entry['ipport'] = _long2ip($row[4]).':'.$row[5];
				$entry['auth'] = $row[6];
				$entry['name'] = $row[7];
			} else {
				$entry['range'] = '';
				$oct = explode('.', _long2ip($row[4]));
				if ((int)$row[4] != 0)
				{
					array_pop($oct);
					array_pop($oct);
					$entry['range'] = implode('.', $oct).'.0.0/16';
				}
			}
			$entry['bssid'] = '';
			if ((int)$row[8] == 0) $entry['bssid'] = dec2mac($row[9]);
			$entry['essid'] = $row[10];
			$entry['sec'] = sec2str((int)$row[11]);
			$entry['key'] = $row[12];
			$entry['wps'] = ($row[13] == null ? '' : str_pad($row[13], 8, '0', STR_PAD_LEFT));
			$entry['lat'] = 'none';
			$entry['lon'] = 'none';
			if ((int)$row[8] == 0)
			{
				$entry['lat'] = (float)$row[14];
				$entry['lon'] = (float)$row[15];
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
		if(sizeof($json['data'] > 0))
		{
			$_SESSION['Search']['FirstId'] = $json['data'][0];
			$_SESSION['Search']['LastId'] = $json['data'][sizeof($json['data'])-1];
		}
	}
	$db->close();
	break;

	// Поиск диапазонов IP
	case 'find_ranges':
	$json['result'] = true;
	$json['auth'] = $level > 0;
	if ($level == 0) break;

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
	if ($radius == "")
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

	$lat_km = 111.321 * cos(deg2rad($lat)) - 0.094 * cos(3 * deg2rad($lat));
	$lon_km = 111.143 - 0.562 * cos(2 * deg2rad($lat));
	$lat1 = min(max($lat - $radius / $lat_km, -90), 90);
	$lat2 = min(max($lat + $radius / $lat_km, -90), 90);
	$lon1 = min(max($lon - $radius / $lon_km, -180), 180);
	$lon2 = min(max($lon + $radius / $lon_km, -180), 180);
	$json['data'] = array();
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql(
		"SELECT DISTINCT IP 
		FROM `BASE_TABLE`, `GEO_TABLE` 
		WHERE `BASE_TABLE`.`BSSID` = `GEO_TABLE`.`BSSID` 
				AND (`GEO_TABLE`.`latitude` != 0 AND `GEO_TABLE`.`longitude` != 0 
				AND `GEO_TABLE`.`latitude` IS NOT NULL AND `GEO_TABLE`.`longitude` IS NOT NULL) 
				AND (`GEO_TABLE`.`latitude` BETWEEN $lat1 AND $lat2 AND `GEO_TABLE`.`longitude` BETWEEN $lon1 AND $lon2) 
				AND IP != 0 ORDER BY CAST(IP AS UNSIGNED INTEGER)"));
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
	$json['auth'] = $level > 0;
	if ($level == 0) break;

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

			function ValidHeaderCSV($row)
			{
				if (($row[0] !== 'IP Address')
				|| ($row[1] !== 'Port')
				|| ($row[4] !== 'Authorization')
				|| ($row[5] !== 'Server name / Realm name / Device type')
				|| ($row[6] !== 'Radio Off')
				|| ($row[7] !== 'Hidden')
				|| ($row[8] !== 'BSSID')
				|| ($row[9] !== 'ESSID')
				|| ($row[10] !== 'Security')
				|| ($row[11] !== 'Key')
				|| ($row[12] !== 'WPS PIN')
				|| ($row[13] !== 'LAN IP Address')
				|| ($row[14] !== 'LAN Subnet Mask')
				|| ($row[15] !== 'WAN IP Address')
				|| ($row[16] !== 'WAN Subnet Mask')
				|| ($row[17] !== 'WAN Gateway')
				|| ($row[18] !== 'Domain Name Servers'))
				{
					return false;
				}
				return true;
			}
			function ValidHeaderTXT($row)
			{
				$row = explode("\t", $row);
				return (count($row) == 23);
			}

			if (($handle = fopen($filename, 'r')) !== false)
			{
				switch ($ext)
				{
					case '.csv':
					if (($row = fgetcsv($handle, 1000, ";")) !== false)
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
				if (QuerySql("INSERT INTO tasks (`tid`,`created`,`modified`,`ext`,`comment`,`checkexist`,`nowait`) VALUES ('$tid',now(),now(),'$ext','$comment',$checkexist,$nowait)"))
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
					QuerySql("UPDATE tasks SET `modified`=now(),`comment`='$comment',`checkexist`=$checkexist,`nowait`=$nowait WHERE `tid`='$tid')");
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
			$json['upload']['processing'] = QuerySql("UPDATE tasks SET `tstate`=1 WHERE `tid`='$tid'");
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

	// Общая статистика
	case 'stat':
	$json['result'] = true;
	$json['stat']['date'] = date('Y.m.d H:i:s');
	if (!db_connect())
	{
		$json['result'] = false;
		$json['error'] = 'database';
		break;
	}
	if ($res = QuerySql("SELECT COUNT(*) FROM BASE_TABLE"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT COUNT(*) FROM BASE_TABLE WHERE `NoBSSID` = 0"))
	{
		$row = $res->fetch_row();
		$json['stat']['bssids'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT COUNT(*) FROM GEO_TABLE WHERE (`latitude` IS NOT NULL AND `longitude` IS NOT NULL) 
																AND (`latitude` != 0 AND `longitude` != 0)"))
	{
		$row = $res->fetch_row();
		$json['stat']['onmap'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT COUNT(*) FROM GEO_TABLE WHERE `latitude` IS NULL"))
	{
		$row = $res->fetch_row();
		$json['stat']['processing'] = (int)$row[0];
		$res->close();
	}
	$db->close();
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
	if ($res = QuerySql("SELECT `cmtid`, COUNT(*) FROM BASE_TABLE GROUP BY `cmtid` ORDER BY COUNT(*) DESC"))
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
	if ($res = QuerySql("SELECT COUNT(DISTINCT `WPSPIN`) FROM BASE_TABLE WHERE NOT(`WPSPIN` IS NULL)"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT `WPSPIN`, COUNT(*) FROM BASE_TABLE WHERE NOT(`WPSPIN` IS NULL) GROUP BY `WPSPIN` ORDER BY COUNT(*) DESC LIMIT $topWPSPIN"))
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
	// Исправить на проверку всех полей DNS#
	if ($res = QuerySql("SELECT COUNT(DISTINCT `DNS1`) FROM BASE_TABLE WHERE `DNS1`!=0"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT `DNS1`, COUNT(*) FROM BASE_TABLE WHERE `DNS1`!=0 GROUP BY `DNS1` ORDER BY COUNT(*) DESC LIMIT $topDNS"))
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
}

session_write_close();
$time = microtime(true) - $time;
$json['time'] = $time;

Header('Content-Type: application/json');
echo json_encode($json);
?>