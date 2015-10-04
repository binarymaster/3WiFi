<?php
if (!isset($_GET['a']))
{
	Header('HTTP/1.0 303 See Other');
	Header('Location: /');
	exit;
}

$topPort = 10;
$topauth = 100;
$topname = 30;
$topbssid = 30;
$topessid = 30;
$topSecurity = 30;
$topWiFiKey = 30;
$topWPSPIN = 30;
$topDNS = 30;

require 'con_db.php'; /* Коннектор MySQL */

$tstart = microtime(true);
$json = array();
$json['result'] = false;

switch ($_GET['a'])
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

				$aphint[] = $xtime;
				$aphint[] = $xbssid;
				$aphint[] = $xessid;
				$aphint[] = $xwifikey;
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
	$pass = '';
	if (isset($_POST['pass'])) $pass = $_POST['pass'];

	$json['result'] = true;
	$level = 0;
	if ($pass == 'antichat') $level = 1;
	if ($pass == 'secret_password') $level = 2;

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

	if ($res = $db->query("SELECT * FROM `free` WHERE `comment` LIKE '$comment' AND `IP` LIKE '$ipaddr' AND `Authorization` LIKE '$auth' AND `name` LIKE '$name' AND `BSSID` LIKE '$bssid' AND `ESSID` LIKE '$essid' AND `WiFiKey` LIKE '$key' AND `WPSPIN` LIKE '$wps' ORDER BY `time` DESC LIMIT 1000"))
	{
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
	$pass = '';
	if (isset($_POST['pass'])) $pass = $_POST['pass'];
	if ($pass != 'antichat')
	{
		$json['auth'] = false;
		break;
	}
	else $json['auth'] = true;
	
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
	$radius = (int)$radius;
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
		"SELECT SUBSTRING_INDEX(IP, '.', 2) AS ip_range 
		FROM `free` 
		WHERE (`latitude` != 0 AND `longitude` != 0)
				AND (`latitude` BETWEEN $lat1 AND $lat2 AND `longitude` BETWEEN $lon1 AND $lon2)
				AND IP !=''
		GROUP BY ip_range
		ORDER BY ip_range"))
	{
		while ($row = $res->fetch_row())
		{
			$json['data'][] = $row[0].'.0.0/16';
		}
		$res->close();
	}
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
$db->close();

$time = microtime(true) - $tstart;
$json['time'] = $time;

Header('Content-Type: application/json');
echo json_encode($json);
?>
