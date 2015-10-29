<?php
if (!isset($_GET['a']))
{
	Header('HTTP/1.0 303 See Other');
	Header('Location: /');
	exit;
}
$action = $_GET['a'];

$topPort = 10;
$topauth = 100;
$topname = 30;
$topbssid = 30;
$topessid = 30;
$topSecurity = 30;
$topWiFiKey = 30;
$topWPSPIN = 30;
$topDNS = 30;

$daemonize = false;
require 'con_db.php'; /* Коннектор MySQL */

$pass_level1 = 'antichat';
$pass_level2 = 'secret_password';

$pass = '';
if (isset($_POST['pass']))
{
	$pass = $_POST['pass'];
} else {
	if (isset($_GET['pass']))
		$pass = $_GET['pass'];
}

$level = 0;
if ($pass == $pass_level1) $level = 1;
if ($pass == $pass_level2) $level = 2;

$time = microtime(true);
$json = array();
$json['result'] = false;

function randhex($length)
{
	$alpha = '0123456789abcdef';
	$len = strlen($alpha);
	$str = '';
	for ($i = 0; $i < $length; $i++)
		$str .= $alpha[rand(0, $len - 1)];
	return $str;
}

function disable_gzip()
{
	@ini_set('zlib.output_compression', 'Off');
	@ini_set('output_buffering', 'Off');
	@ini_set('output_handler', '');
	@apache_setenv('no-gzip', 1);	
}

function getTask($tid)
{
	global $db;
	$result = false;
	if ($res = $db->query("SELECT * FROM `tasks` WHERE `tid`='$tid'"))
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
			$result['lines'] = (int)$row[7];
			$result['accepted'] = (int)$row[8];
			$result['onmap'] = (int)$row[9];
			$result['warns'] = $row[10];
		}
		$res->close();
	}
	return $result;
}

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

switch ($action)
{
	// Координаты точек на карте
	case 'map':
	$bbox =  explode(",", $_GET['bbox']);
	$callback = $_GET['callback'];

	$lat1 = (float)$bbox[0];
	$lon1 = (float)$bbox[1];
	$lat2 = (float)$bbox[2];
	$lon2 = (float)$bbox[3];

	if ($res = $db->query("SELECT * FROM `free` WHERE (`latitude` != 0 AND `longitude` != 0) AND (`latitude` BETWEEN $lat1 AND $lat2 AND `longitude` BETWEEN $lon1 AND $lon2) LIMIT 1000"))
	{
		unset($json); // здесь используется JSON-P
		$data = array();
		while ($row = $res->fetch_row())
		{
			$xlatitude = $row[20];
			$xlongitude = $row[21];
			if (!isset($data[$xlatitude][$xlongitude])) $data[$xlatitude][$xlongitude] = array();
			$i = count($data[$xlatitude][$xlongitude]);
			$data[$xlatitude][$xlongitude][$i]['id'] = (int)$row[0];
			$data[$xlatitude][$xlongitude][$i]['time'] = $row[1];
			$data[$xlatitude][$xlongitude][$i]['bssid'] = $row[9];
			$data[$xlatitude][$xlongitude][$i]['essid'] = $row[10];
			$data[$xlatitude][$xlongitude][$i]['key'] = $row[12];
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

	$comment = '%';
	$ipaddr = '%';
	$auth = '%';
	$name = '%';
	$bssid = '%';
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
	$comment = $db->real_escape_string($comment);
	$ipaddr = $db->real_escape_string($ipaddr);
	$auth = $db->real_escape_string($auth);
	$name = $db->real_escape_string($name);
	$bssid = $db->real_escape_string($bssid);
	$essid = $db->real_escape_string($essid);
	$key = $db->real_escape_string($key);
	$wps = $db->real_escape_string($wps);

	$cur_page = 1;
	$per_page = 50;
	if (isset($_POST['page'])) $cur_page = (int)$_POST['page'];
	if ($cur_page < 1) $cur_page = 1;
	$from = ($cur_page - 1) * $per_page;

	if ($res = $db->query("SELECT SQL_CALC_FOUND_ROWS * FROM `free` WHERE `comment` LIKE '$comment' AND `IP` LIKE '$ipaddr' AND `Authorization` LIKE '$auth' AND `name` LIKE '$name' AND `BSSID` LIKE '$bssid' AND `ESSID` LIKE '$essid' AND `WiFiKey` LIKE '$key' AND `WPSPIN` LIKE '$wps' ORDER BY `time` DESC LIMIT $from, $per_page"))
	{
		if ($res_rows = $db->query("SELECT FOUND_ROWS()"))
		{
			$rows = $res_rows->fetch_row();
			$rows = (int)$rows[0];
			$pages = ceil($rows / $per_page);
			$json['found'] = $rows;
			$json['page']['current'] = $cur_page;
			$json['page']['count'] = $pages;
		}
		$json['data'] = array();
		while ($row = $res->fetch_row())
		{
			$entry = array();
			if ($level > 1) $entry['id'] = (int)$row[0];
			$entry['time'] = $row[1];
			$entry['comment'] = $row[2];
			if ($level > 1)
			{
				$entry['ipport'] = '';
				if ($row[3] != '') $entry['ipport'] = $row[3].':'.$row[4];
				$entry['auth'] = $row[5];
				$entry['name'] = $row[6];
			} else {
				$entry['range'] = '';
				$oct = explode('.', $row[3]);
				if (count($oct) == 4)
				{
					array_pop($oct);
					array_pop($oct);
					$entry['range'] = implode('.', $oct).'.0.0/16';
				}
			}
			$entry['bssid'] = $row[9];
			$entry['essid'] = $row[10];
			$entry['sec'] = $row[11];
			$entry['key'] = $row[12];
			$entry['wps'] = $row[13];
			$entry['lat'] = $row[20];
			$entry['lon'] = $row[21];

			$json['data'][] = $entry;
			unset($entry);
		}
		$res->close();
	}
	break;

	// Поиск диапазонов IP
	case 'find_ranges':
	$json['result'] = true;
	$json['auth'] = $level > 0;
	if ($level == 0) break;

	$lat = ''; $lon = '';
	if (isset($_POST['latitude'])) $lat = $_POST['latitude'];
	if (isset($_POST['longitude'])) $lon = $_POST['longitude'];
	if ($lat == "")
	{
		$json['error'] = "Введите значение широты";
		break;
	}
	if ($lon == "")
	{
		$json['error'] = "Введите значение долготы";
		break;
	}
	$lat = (float)$lat;
	$lon = (float)$lon;
	if ($lat < -90 || $lat > 90)
	{
		$json['error'] = "Значение широты должно лежать в диапазоне [-90;90]";
		break;
	}
	if ($lon < -180 || $lon > 180)
	{
		$json['error'] = "Значение долготы должно лежать в диапазоне [-180;180]";
		break;
	}

	$radius = '';
	if (isset($_POST['radius'])) $radius = $_POST['radius'];
	if ($radius == "")
	{
		$json['error'] = "Введите значение радиуса поиска";
		break;
	}
	$radius = (float)$radius;
	if ($radius < 0 || $radius > 25)
	{
		$json['error'] = "Значение радиуса поиска должно лежать в диапазоне (0;25]";
		break;
	}

	$lat_km = 111.321 * cos(deg2rad($lat)) - 0.094 * cos(3 * deg2rad($lat));
	$lon_km = 111.143 - 0.562 * cos(2 * deg2rad($lat));
	$lat1 = min(max($lat - $radius / $lat_km, -90), 90);
	$lat2 = min(max($lat + $radius / $lat_km, -90), 90);
	$lon1 = min(max($lon - $radius / $lon_km, -180), 180);
	$lon2 = min(max($lon + $radius / $lon_km, -180), 180);
	$json['data'] = array();
	if ($res = $db->query(
		"SELECT IP 
		FROM `free` 
		WHERE (`latitude` != 0 AND `longitude` != 0)
				AND (`latitude` BETWEEN $lat1 AND $lat2 AND `longitude` BETWEEN $lon1 AND $lon2)
				AND IP !=''
		ORDER BY INET_ATON(IP)"))
	{
		require 'ipext.php';
		$last_upper = '0.0.0.0';
		while ($row = $res->fetch_row())
		{
			if (compare_ip($row[0], $last_upper) <= 0)
			{
				continue;
			}
			$ip_range = GetIPRange($row[0]);
			if(is_null($ip_range))
			{
				continue;
			}
			$last_upper = $ip_range["endIP"];
			$json['data'][] = array(
				"range" => pretty_range($ip_range["startIP"], $ip_range["endIP"]),
				"descr" => $ip_range["descr"]);
		}
		$res->close();
		usort($json['data'], function($a, $b){return strcmp($a['descr'], $b['descr']);});
	}
	break;

	// Определение устройства по MAC
	case 'devicemac':
	$json['result'] = true;
	$json['auth'] = $level > 0;
	if ($level == 0) break;

	$bssid = '';
	if (isset($_POST['bssid'])) $bssid = $_POST['bssid'];
	$bssid = strtoupper($bssid);
	$bssid = str_replace(':', '', $bssid);
	$bssid = str_replace('-', '', $bssid);
	$bssid = str_replace('.', '', $bssid);
	if (strlen($bssid) != 12) break;

	$bssid = substr_replace($bssid, ':', 10, 0);
	$bssid = substr_replace($bssid, ':', 8, 0);
	$bssid = substr_replace($bssid, ':', 6, 0);
	$bssid = substr_replace($bssid, ':', 4, 0);
	$bssid = substr_replace($bssid, ':', 2, 0);
	$oui = substr($bssid, 0, 9) . '__:__:__';

	$oui = $db->real_escape_string($oui);
	if ($res = $db->query("SELECT `BSSID`,`name` FROM `free` WHERE `BSSID` LIKE '$oui' AND `name`!=''"))
	{
		$devs = array();
		while ($row = $res->fetch_row())
		{
			$bss = strtoupper($row[0]);
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
		$tid = '';
		if (isset($_GET['tid'])) $tid = $_GET['tid'];
		$tid = $db->real_escape_string($tid);
		$comment = '';
		if (isset($_GET['comment'])) $comment = $_GET['comment'];
		if ($comment == '') $comment = 'none';
		$checkexist = isset($_GET['checkexist']) && ($_GET['checkexist'] == '1');
		$done = isset($_GET['done']) && ($_GET['done'] == '1');
		$nowait = isset($_GET['nowait']) && ($_GET['nowait'] == '1');
		if ($contentType == 'text/csv') $ext = '.csv';
		if ($contentType == 'text/plain') $ext = '.txt';

		if ($tid == '')
		{
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
				if ($db->query("INSERT INTO `tasks` (`tid`,`created`,`modified`,`ext`,`comment`,`checkexist`) VALUES ('$tid',now(),now(),'$ext','$comment','$checkexist')"))
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
					$db->query("UPDATE `tasks` SET `modified`=now(),`comment`='$comment',`checkexist`='$checkexist' WHERE `tid`='$tid')");
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
			$json['upload']['processing'] = $db->query("UPDATE `tasks` SET `tstate`='1' WHERE `tid`='$tid'");
			$daemonize = $json['upload']['processing'];
		}
	} else
		$error[] = 1; // Неверные заголовки или размер данных
	$json['upload']['error'] = $error;
	break;

	// Проверка состояния загрузки
	case 'upstat':
	$json['result'] = true;

	$tid = '';
	if (isset($_GET['tid'])) $tid = $_GET['tid'];
	$tid = $db->real_escape_string($tid);
	$task = getTask($tid);
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

	// Перепроверка необработанных результатов
	case 'check':
	$json['result'] = true;
	if ($res = $db->query("SELECT `BSSID` FROM `free` WHERE `BSSID` LIKE '__:__:__:__:__:__' AND `latitude` = 'none' AND `longitude` = 'none' LIMIT 100"))
	{
		$aps = array();
		while ($row = $res->fetch_row())
		{
			$aps[] = $row[0];
		}
		$res->close();
		$aps = array_unique($aps);
		require 'chkxy.php';
		$json['check']['done'] = count($aps);
		$json['check']['found'] = CheckLocation($aps);
	}
	break;

	// Общая статистика
	case 'stat':
	$json['result'] = true;
	$json['stat']['date'] = date('Y.m.d H:i:s');
	if ($res = $db->query("SELECT COUNT(*) FROM `free`"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = $db->query("SELECT COUNT(*) FROM `free` WHERE `BSSID` LIKE '__:__:__:__:__:__'"))
	{
		$row = $res->fetch_row();
		$json['stat']['bssids'] = (int)$row[0];
		$res->close();
	}
	if ($res = $db->query("SELECT COUNT(*) FROM `free` WHERE `latitude` != 0 AND `longitude` != 0"))
	{
		$row = $res->fetch_row();
		$json['stat']['onmap'] = (int)$row[0];
		$res->close();
	}
	if ($res = $db->query("SELECT COUNT(*) FROM `free` WHERE `BSSID` LIKE '__:__:__:__:__:__' AND `latitude` = 'none' AND `longitude` = 'none'"))
	{
		$row = $res->fetch_row();
		$json['stat']['processing'] = (int)$row[0];
		$res->close();
	}
	break;

	// Комментарии
	case 'stcmt':
	$json['result'] = true;
	if ($res = $db->query("SELECT `comment`, COUNT(*) FROM free GROUP BY `comment` ORDER BY COUNT(*) DESC"))
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
	break;

	// Названия устройств
	case 'stdev':
	$json['result'] = true;
	$json['stat']['top'] = $topname;
	if ($res = $db->query("SELECT COUNT(DISTINCT `name`) FROM `free` WHERE `name`!=''"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = $db->query("SELECT `name`, COUNT(*) FROM free WHERE `name`!='' GROUP BY `name` ORDER BY COUNT(*) DESC LIMIT $topname"))
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
	break;

	// Порты
	case 'stport':
	$json['result'] = true;
	$json['stat']['top'] = $topPort;
	if ($res = $db->query("SELECT COUNT(DISTINCT `Port`) FROM `free` WHERE `Port`!=''"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = $db->query("SELECT `Port`, COUNT(*) FROM free WHERE `Port`!='' GROUP BY `Port` ORDER BY COUNT(*) DESC LIMIT $topPort"))
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
	break;

	// Данные авторизации
	case 'stauth':
	$json['result'] = true;
	$json['stat']['top'] = $topauth;
	if ($res = $db->query("SELECT COUNT(DISTINCT `Authorization`) FROM `free` WHERE `Authorization`!=''"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = $db->query("SELECT `Authorization`, COUNT(*) FROM free WHERE `Authorization`!='' GROUP BY `Authorization` ORDER BY COUNT(*) DESC LIMIT $topauth"))
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
	break;

	// BSSID точек доступа
	case 'stbss':
	$json['result'] = true;
	$json['stat']['top'] = $topbssid;
	if ($res = $db->query("SELECT COUNT(DISTINCT `BSSID`) FROM `free`"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = $db->query("SELECT `BSSID`, COUNT(*) FROM free GROUP BY `BSSID` ORDER BY COUNT(*) DESC LIMIT $topbssid"))
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
	break;

	// ESSID точек доступа
	case 'stess':
	$json['result'] = true;
	$json['stat']['top'] = $topessid;
	if ($res = $db->query("SELECT COUNT(DISTINCT `ESSID`) FROM `free`"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = $db->query("SELECT `ESSID`, COUNT(*) FROM free GROUP BY `ESSID` ORDER BY COUNT(*) DESC LIMIT $topessid"))
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
	break;

	// Тип защиты точек доступа
	case 'stsec':
	$json['result'] = true;
	$json['stat']['top'] = $topSecurity;
	if ($res = $db->query("SELECT COUNT(DISTINCT `Security`) FROM `free`"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = $db->query("SELECT `Security`, COUNT(*) FROM free GROUP BY `Security` ORDER BY COUNT(*) DESC LIMIT $topSecurity"))
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
	break;

	// Ключи точек доступа
	case 'stkey':
	$json['result'] = true;
	$json['stat']['top'] = $topWiFiKey;
	if ($res = $db->query("SELECT COUNT(DISTINCT `WiFiKey`) FROM `free`"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = $db->query("SELECT `WiFiKey`, COUNT(*) FROM free GROUP BY `WiFiKey` ORDER BY COUNT(*) DESC LIMIT $topWiFiKey"))
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
	break;

	// WPS пин коды точек доступа
	case 'stwps':
	$json['result'] = true;
	$json['stat']['top'] = $topWPSPIN;
	if ($res = $db->query("SELECT COUNT(DISTINCT `WPSPIN`) FROM `free`"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = $db->query("SELECT `WPSPIN`, COUNT(*) FROM free GROUP BY `WPSPIN` ORDER BY COUNT(*) DESC LIMIT $topWPSPIN"))
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
	break;

	// DNS серверы
	case 'stdns':
	$json['result'] = true;
	$json['stat']['top'] = $topDNS;
	if ($res = $db->query("SELECT COUNT(DISTINCT `DNS`) FROM `free` WHERE `DNS`!=''"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = $db->query("SELECT `DNS`, COUNT(*) FROM free WHERE `DNS`!='' GROUP BY `DNS` ORDER BY COUNT(*) DESC LIMIT $topDNS"))
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
	break;
}

$time = microtime(true) - $time;
$json['time'] = $time;

Header('Content-Type: application/json');
if (!$daemonize)
{
	$db->close();
	echo json_encode($json);
	exit;
}

if ($daemonize)
{
	// Превращаемся в демона и обрабатываем данные в фоне
	set_time_limit(0);
	ignore_user_abort(true);
	disable_gzip();
	ob_start();
	echo json_encode($json);
	Header('Connection: close');
	Header("Content-Encoding: none");
	Header('Content-Length: ' . ob_get_length());
	ob_end_flush();
	ob_flush();
	flush();
	/* ------------------------------------------------- */
	switch ($action)
	{
		case 'upload':
		function APinDB($bssid, $essid, $key)
		{
			global $chkst;
			$chkst->bind_param("sss", $bssid, $essid, $key);
			$chkst->execute();
			$chkst->store_result();
			$result = $chkst->num_rows;
			$chkst->free_result();
			return $result > 0;
		}
		function addRow($row)
		{
			global $comment;
			global $checkexist;
			global $stmt;
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
			$sec = $row[10];
			$key = $row[11];
			$wps = $row[12];
			if ($bssid == '<no wireless>')
			{
				return 2;
			}
			if ((strpos($bssid, ':') === false || $wps == '')
			&& ($essid == '' || $sec == '' || $sec == '-' || $key == '' || $key == '-'))
			{
				if (strpos($bssid, ':') !== false
				|| $essid != ''
				|| $sec != ''
				|| $key != ''
				|| $wps != '')
				{ return 3; }
				else { return 1; }
			}
			if ($checkexist)
				if (APinDB($bssid, $essid, $key))
				{
					return 4;
				}

			$aps[] = $bssid;
			$stmt->bind_param("ssssssssssssssssssssssssssssssssssss", // format
					// INSERT
					//    comment   IP        Port      Auth      Name      RadioOff  Hidden    BSSID     ESSID     Security   Key        WPS PIN    LAN IP     LAN Mask   WAN IP     WAN Mask   WAN Gate   DNS Serv
						$comment, $row[0], $row[1], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $row[10], $row[11], $row[12], $row[13], $row[14], $row[15], $row[16], $row[17], $row[18],
					// UPDATE
						$comment, $row[0], $row[1], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $row[10], $row[11], $row[12], $row[13], $row[14], $row[15], $row[16], $row[17], $row[18]
			);
			$stmt->execute();
			return 0;
		}

		$tid = $db->real_escape_string($tid);
		$task = getTask($tid);
		if ($task !== false)
		{
			$checkexist = $task['checkexist'];
			if ($checkexist)
				$chkst = $db->prepare("SELECT * FROM `free` WHERE `BSSID`=? AND `ESSID`=? AND `WiFiKey`=? LIMIT 1");

			$warn = array();
			$ext = $task['ext'];
			$filename = 'uploads/'.$tid.$ext;
			if (($handle = fopen($filename, 'r')) !== false)
			{
				$comment = $task['comment'];

				$sql = 'INSERT INTO `free` (`comment`,`IP`,`Port`,`Authorization`,`name`,`RadioOff`,`Hidden`,`BSSID`,`ESSID`,`Security`,`WiFiKey`,`WPSPIN`,`LANIP`,`LANMask`,`WANIP`,`WANMask`,`WANGateway`,`DNS`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE `comment`=?,`IP`=?,`Port`=?,`Authorization`=?,`name`=?,`RadioOff`=?,`Hidden`=?,`BSSID`=?,`ESSID`=?,`Security`=?,`WiFiKey`=?,`WPSPIN`=?,`LANIP`=?,`LANMask`=?,`WANIP`=?,`WANMask`=?,`WANGateway`=?,`DNS`=?;';
				$stmt = $db->prepare($sql);

				$i = 0;
				$cnt = 0;
				$aps = array();
				$hangcheck = 5;
				$time = microtime(true);
				switch ($ext)
				{
					case '.csv':
					while (($data = fgetcsv($handle, 1000, ';')) !== false)
					{
						$i++;
						if ($i == 1) continue; // Пропуск заголовка CSV
						$res = addRow($data);
						($res == 0 ? $cnt++ : $warn[$i - 1] = $res);
						if (microtime(true) - $time > $hangcheck)
						{
							$db->query("UPDATE `tasks` SET `lines`='$i',`accepted`='$cnt' WHERE `tid`='$tid'");
							$time = microtime(true);
						}
					}
					$i--;
					break;
					case '.txt':
					while (($str = fgets($handle)) !== false)
					{
						$data = explode("\t", $str);
						$i++;
						$res = addRow($data);
						($res == 0 ? $cnt++ : $warn[$i] = $res);
						if (microtime(true) - $time > $hangcheck)
						{
							$db->query("UPDATE `tasks` SET `lines`='$i',`accepted`='$cnt' WHERE `tid`='$tid'");
							$time = microtime(true);
						}
					}
					break;
				}
				if ($checkexist) $chkst->close();
				fclose($handle);
				$stmt->close();
			}
			$warns = array();
			foreach ($warn as $line => $wid)
				$warns[] = implode('|', array($line, $wid));
			$warns = implode(',', $warns);

			$db->query("UPDATE `tasks` SET `lines`='$i',`accepted`='$cnt',`warns`='$warns',`tstate`='2' WHERE `tid`='$tid'");
			unlink($filename);

			require 'chkxy.php';
			$found = CheckLocation($aps, $tid);
			$db->query("UPDATE `tasks` SET `onmap`='$found',`tstate`='3' WHERE `tid`='$tid'");

			if (!$nowait) sleep(60);
			$db->query("DELETE FROM `tasks` WHERE `tid`='$tid'");
		}
		break;
	}
	$db->close();
}
?>