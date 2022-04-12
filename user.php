<?php
include 'config.php';
require_once 'db.php';
require_once 'utils.php';
require_once 'user.class.php';

global $db;

$UserManager = new User();
$UserManager->load();

function getFloatCoord($coord)
{
	global $db;
	$name = randomStr(12, false);
	if (!QuerySql("CREATE TEMPORARY TABLE `$name` (coord FLOAT(12,8))")) return null;
	QuerySql("INSERT INTO `$name` (coord) VALUES ($coord)");
	$res = QuerySql("SELECT coord FROM `$name`");
	$row = $res->fetch_row();
	$res->close();
	QuerySql("DROP TABLE `$name`");
	return $row[0];
}

$json = array();
$json['result'] = false;
if (!db_connect())
{
	$json['error'] = 'database';
	header('Content-Type: application/json');
	echo json_encode($json);
	exit();
}

$action = isset($_GET['a']) ? $_GET['a'] : null;

if ($action == null)
{
	Header('HTTP/1.0 303 See Other');
	Header('Location: /');
	exit;
}

switch($action)
{
	// Вход пользователя
	case 'login':
	$json['result'] = false;
	if ($UserManager->isLogged())
	{
		$json['error'] = 'loggedin';
		break;
	}
	if (isset($_POST['login']) && isset($_POST['password']))
	{
		filterLogin($_POST['login']);
		if (!$UserManager->Auth($_POST['password'], $_POST['login']))
		{
			$json['error'] = 'loginfail';
			break;
		}
		if ($UserManager->Level == -2)
		{
			$json['error'] = 'lowlevel';
			$info = $UserManager->getUserInfo($UserManager->uID);
			if ($info['ban_reason'] != null)
			{
				$json['reason'] = $info['ban_reason'];
				$json['inherited'] = false;

				if ($json['reason'] == "inherit")
				{
					$info = $UserManager->getUserInfo($UserManager->puID);
					$json['reason'] = $info['ban_reason'];
					$json['inherited'] = true;
				}
			}
			$UserManager->out();
			break;
		}
		$json['result'] = true;
	}
	break;

	// Выход из учётной записи
	case 'logout':
	$json['result'] = false;
	if ($UserManager->isLogged())
	{
		if (!$UserManager->checkToken($_GET['token']))
		{
			$json['error'] = 'token';
			break;
		}
		$UserManager->out();
		$json['result'] = true;
	}
	else
	{
		$json['error'] = 'unauthorized';
	}
	break;

	// Проверка логина и ника на существование
	case 'testreg':
	$json['result'] = false;
	if ($UserManager->isLogged())
	{
		$json['error'] = 'loggedin';
		break;
	}
	if (isset($_POST['invite']) && (isset($_POST['login']) || isset($_POST['nick'])))
	{
		if (strlen($_POST['invite']) != INVITE_LEN || !$UserManager->isValidInvite($_POST['invite']))
		{
			$json['error'] = 'invite';
			break;
		}
		if (isset($_POST['login']))
		{
			filterLogin($_POST['login']);
			if (strlen($_POST['login']) < LOGIN_MIN || strlen($_POST['login']) > LOGIN_MAX)
			{
				$json['error'] = 'login';
				break;
			}
			if ($UserManager->isUserLogin($_POST['login']))
			{
				$json['error'] = 'login';
				break;
			}
		}
		if (isset($_POST['nick']))
		{
			filterNick($_POST['nick']);
			if (strlen($_POST['nick']) < NICK_MIN || strlen($_POST['nick']) > NICK_MAX)
			{
				$json['error'] = 'nick';
				break;
			}
			if ($UserManager->isUserNick($_POST['nick']))
			{
				$json['error'] = 'nick';
				break;
			}
		}
		$json['result'] = true;
	}
	else
		$json['error'] = 'form';
	break;

	// Получение токена для защиты от CSRF
	case 'token':
	$json['result'] = false;
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	$json['token'] = $UserManager->genToken();
	$json['result'] = $json['token'] != '';
	break;

	// Регистрация нового пользователя
	case 'reg':
	if ($UserManager->isLogged())
	{
		$json['error'] = 'loggedin';
		break;
	}
	$newLogin = isset($_POST['login']) ? $_POST['login'] : NULL;
	$newNick = isset($_POST['nick']) ? $_POST['nick'] : NULL;
	$newPassword = isset($_POST['password']) ? $_POST['password'] : NULL;
	$newInvite = isset($_POST['invite']) ? $_POST['invite'] : NULL;

	if(is_null($newLogin)
	|| is_null($newNick)
	|| is_null($newPassword)
	|| is_null($newInvite))
	{
		$json['error'] = 'form';
		break;
	}
	if (strlen($newInvite) != INVITE_LEN || !$UserManager->isValidInvite($newInvite))
	{
		$json['error'] = 'invite';
		break;
	}
	filterLogin($newLogin);
	if (strlen($newLogin) < LOGIN_MIN || strlen($newLogin) > LOGIN_MAX)
	{
		$json['error'] = 'form';
		break;
	}
	if ($UserManager->isUserLogin($newLogin))
	{
		$json['error'] = 'login';
		break;
	}
	filterNick($newNick);
	if (strlen($newNick) < NICK_MIN || strlen($newNick) > NICK_MAX)
	{
		$json['error'] = 'form';
		break;
	}
	if ($UserManager->isUserNick($newNick))
	{
		$json['error'] = 'nick';
		break;
	}
	if (strlen($newPassword) < PASS_MIN || strlen($newPassword) > PASS_MAX)
	{
		$json['error'] = 'password';
		break;
	}
	$json['result'] = $UserManager->Registration($newLogin, $newNick, $newPassword, $newInvite);
	break;

	// Смена никнейма пользователя
	case 'changenick':
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	if (!$UserManager->checkToken($_POST['token']))
	{
		$json['error'] = 'token';
		break;
	}
	$newNick = isset($_POST['nick']) ? $_POST['nick'] : null;
	if (is_null($newNick))
	{
		$json['error'] = 'form';
		break;
	}
	filterNick($_POST['nick']);
	if (strlen($_POST['nick']) < NICK_MIN || strlen($_POST['nick']) > NICK_MAX)
	{
		$json['error'] = 'form';
		break;
	}
	if ($_POST['nick'] == $UserManager->Nick)
	{
		$json['result'] = true;
		break;
	}
	if ($UserManager->isUserNick($_POST['nick']))
	{
		$json['error'] = 'nick';
		break;
	}
	$json['result'] = $UserManager->changeNick($_POST['nick']);
	if (!$json['result']) $json['error'] = 'database';
	break;

	// Смена пароля пользователя
	case 'changepass':
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	if (!$UserManager->checkToken($_POST['token']))
	{
		$json['error'] = 'token';
		break;
	}
	$newPass = isset($_POST['password']) ? $_POST['password'] : null;
	if(is_null($newPass))
	{
		$json['error'] = 'form';
		break;
	}
	if (strlen($newPass) < PASS_MIN || strlen($newPass) > PASS_MAX)
	{
		$json['error'] = 'password';
		break;
	}
	if ($UserManager->changePass($newPass))
	{
		$json['result'] = true;
	}
	else
	{
		$json['error'] = '';
	}
	break;

	// Просмотр загруженных точек
	case 'myuploads':
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$uid = $UserManager->uID;
	if (!$res = QuerySql(
		"SELECT id, time, cmtval, 
		IP, Port, Authorization, name, 
		RadioOff, Hidden, 
		NoBSSID, BSSID, ESSID, Security, 
		WiFiKey, WPSPIN, latitude, longitude, 
		fuid IS NOT NULL fav 
		FROM uploads 
		INNER JOIN BASE_TABLE USING(id) 
		LEFT JOIN comments USING (cmtid) 
		LEFT JOIN GEO_TABLE USING (BSSID) 
		LEFT JOIN (SELECT uid AS fuid,id FROM favorites WHERE uid=$uid) ufav USING(id) 
		WHERE uid=$uid ORDER BY time DESC LIMIT 200"))
	{
		$json['error'] = 'database';
		break;
	}
	$json['result'] = true;
	$json['data'] = array();
	while ($row = $res->fetch_assoc())
	{
		$ap = array();
		$ap['id'] = (int)$row['id'];
		$ap['time'] = $row['time'];
		$ap['comment'] = $row['cmtval'];
		$ap['ipport'] = _long2ip($row['IP']);
		if ($row['Port'] != '') $ap['ipport'] .= ':'.$row['Port'];
		$ap['auth'] = $row['Authorization'];
		$ap['name'] = $row['name'];
		$ap['nowifi'] = (bool)$row['RadioOff'];
		$ap['hidden'] = (bool)$row['Hidden'];
		$ap['bssid'] = bssid2str((int)$row['NoBSSID'], $row['BSSID']);
		$ap['essid'] = $row['ESSID'];
		$ap['sec'] = sec2str((int)$row['Security']);
		$ap['key'] = $row['WiFiKey'];
		$ap['wps'] = pin2str($row['WPSPIN']);
		$ap['lat'] = null;
		$ap['lon'] = null;
		if ($row['NoBSSID'] == 0 && $row['latitude'] != 0 && $row['longitude'] != 0)
		{
			$ap['lat'] = (float)$row['latitude'];
			$ap['lon'] = (float)$row['longitude'];
		}
		$ap['fav'] = (bool)$row['fav'];
		$json['data'][] = $ap;
		unset($ap);
	}
	$res->close();
	break;

	// Проверка доступности закачки
	case 'dlcheck':
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$uid = $UserManager->uID;
	if (!$res = QuerySql("SELECT COUNT(id) FROM uploads WHERE uid=$uid"))
	{
		$json['error'] = 'database';
		break;
	}
	$row = $res->fetch_row();
	$res->close();
	$json['result'] = true;
	$json['count'] = (int)$row[0];
	break;

	// Скачивание загруженных точек
	case 'download':
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$uid = $UserManager->uID;
	if (!$res = QuerySql("SELECT IP, Port, Authorization, name, RadioOff, Hidden, NoBSSID, BSSID, ESSID, Security, WiFiKey, WPSPIN, LANIP, LANMask, WANIP, WANMask, WANGateway, DNS1, DNS2, DNS3, latitude, longitude, data 
						FROM BASE_TABLE RIGHT JOIN uploads USING(id) LEFT JOIN extinfo USING (id) LEFT JOIN GEO_TABLE USING (BSSID) WHERE uid=$uid ORDER BY time"))
	{
		$json['error'] = 'database';
		break;
	}
	unset($json);
	Header('Content-Disposition: attachment; filename=myuploads.txt');
	Header('Content-Type: text/plain; charset=utf-8');
	while ($row = $res->fetch_row())
	{
		$row[0] = _long2ip($row[0]);
		if ($row[0] == '') $row[0] = '0.0.0.0';
		if ($row[1] == '') $row[1] = '0';
		$row[4] = ($row[4] == 1 ? '[X]' : '');
		$row[5] = ($row[5] == 1 ? '[X]' : '');
		$row[7] = bssid2str((int)$row[6], $row[7]);
		$row[9] = sec2str((int)$row[9]);
		$row[11] = pin2str($row[11]);
		$row[12] = ($row[12] != 0 ? _long2ip($row[12]) : ''); // LAN IP
		$row[13] = ($row[13] != 0 ? _long2ip($row[13]) : ''); // LAN Mask
		$row[14] = ($row[14] != 0 ? _long2ip($row[14]) : ''); // WAN IP
		$row[15] = ($row[15] != 0 ? _long2ip($row[15]) : ''); // WAN Mask
		$row[16] = ($row[16] != 0 ? _long2ip($row[16]) : ''); // WAN Gateway
		$row[17] = ($row[17] != 0 ? _long2ip($row[17]) : ''); // DNS 1
		$row[18] = ($row[18] != 0 ? _long2ip($row[18]) : ''); // DNS 2
		$row[19] = ($row[19] != 0 ? _long2ip($row[19]) : ''); // DNS 3
		$dns = trim($row[17].' '.$row[18].' '.$row[19]);
		if ($row[6] > 0)
		{	// not accessible
			$row[20] = '';
			$row[21] = '';
		}
		else if ($row[20] == 0 && $row[21] == 0)
		{
			$row[20] = 'not found';
			$row[21] = 'not found';
		}
		//      IP Addr       Port   Time/Stat  Auth         name       RadioOff      Hidden       BSSID        ESSID       Security     Wi-Fi Key      WPS PIN       LAN IP        LAN Mask      WAN IP        WAN Mask      WAN Gate      DNS       Latitude     Longitude      Comment
		$line = $row[0]."\t".$row[1]."\t0\t\t".$row[2]."\t".$row[3]."\t".$row[4]."\t".$row[5]."\t".$row[7]."\t".$row[8]."\t".$row[9]."\t".$row[10]."\t".$row[11]."\t".$row[12]."\t".$row[13]."\t".$row[14]."\t".$row[15]."\t".$row[16]."\t".$dns."\t".$row[20]."\t".$row[21]."\t".$row[22]."\t\n";
		echo $line;
	}
	$res->close();
	break;

	// Просмотр избранных точек
	case 'myfavs':
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$uid = $UserManager->uID;
	if (!$res = QuerySql(
		"SELECT id,time,cmtval,IP,
		RadioOff,Hidden,
		NoBSSID,BSSID,ESSID,
		Security,WiFiKey,WPSPIN,
		WANIP,latitude,longitude 
		FROM favorites 
		INNER JOIN BASE_TABLE USING(id) 
		LEFT JOIN comments USING(cmtid) 
		LEFT JOIN GEO_TABLE USING(BSSID) 
		WHERE uid=$uid"))
	{
		$json['error'] = 'database';
		break;
	}
	$json['result'] = true;
	$json['data'] = array();
	while ($row = $res->fetch_assoc())
	{
		$ap = array();
		$ap['id'] = (int)$row['id'];
		$ap['time'] = $row['time'];
		$ap['comment'] = $row['cmtval'];
		$ip = _long2ip($row['IP']);
		$wanip = _long2ip($row['WANIP']);
		$ap['range'] = ($ip != '' ? $ip : ($wanip != '' ? $wanip : ''));
		if (isLocalIP($ap['range'])
		&& $ap['range'] != $wanip
		&& isValidIP($wanip)
		&& !isLocalIP($wanip))
		{
			$ap['range'] = $wanip;
		}
		if (isValidIP($ap['range']))
		{
			$oct = explode('.', $ap['range']);
			array_pop($oct);
			array_pop($oct);
			$ap['range'] = implode('.', $oct).'.0.0/16';
		} else
			$ap['range'] = '';
		$ap['nowifi'] = (bool)$row['RadioOff'];
		$ap['hidden'] = (bool)$row['Hidden'];
		$ap['bssid'] = bssid2str((int)$row['NoBSSID'], $row['BSSID']);
		$ap['essid'] = $row['ESSID'];
		$ap['sec'] = sec2str((int)$row['Security']);
		$ap['key'] = $row['WiFiKey'];
		$ap['wps'] = pin2str($row['WPSPIN']);
		$ap['lat'] = null;
		$ap['lon'] = null;
		if ($row['NoBSSID'] == 0 && $row['latitude'] != 0 && $row['longitude'] != 0)
		{
			$ap['lat'] = (float)$row['latitude'];
			$ap['lon'] = (float)$row['longitude'];
		}
		$json['data'][] = $ap;
		unset($ap);
	}
	$res->close();
	break;

	// Добавление/удаление точки в избранное
	case 'fav':
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	if (!$UserManager->checkToken($_GET['token']))
	{
		$json['error'] = 'token';
		break;
	}
	$uid = $UserManager->uID;
	$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

	if (is_null($id))
	{
		$json['error'] = 'form';
		break;
	}
	if (!$res = QuerySql("SELECT id FROM favorites WHERE uid=$uid AND id=$id"))
	{
		$json['error'] = 'database';
		break;
	}
	$isfav = $res->num_rows > 0;
	$res->close();
	if ($isfav)
	{
		QuerySql("DELETE FROM favorites WHERE uid=$uid AND id=$id");
		$json['fav'] = false;
		$json['result'] = true;
		break;
	}
	$res = QuerySql("SELECT COUNT(id) FROM favorites WHERE uid=$uid");
	$row = $res->fetch_row();
	$res->close();
	if ($row[0] >= FAV_MAX)
	{
		$json['error'] = 'limit';
		break;
	}
	if (!QuerySql("INSERT INTO favorites (uid,id) VALUES ($uid,$id)"))
	{
		$json['error'] = 'unknown';
		break;
	}
	$json['fav'] = true;
	$json['result'] = true;
	break;

	// Просмотр избранных локаций
	case 'mylocs':
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$uid = $UserManager->uID;
	if (!$res = QuerySql("SELECT latitude,longitude,comment FROM locations WHERE uid=$uid"))
	{
		$json['error'] = 'database';
		break;
	}
	$json['result'] = true;
	$json['data'] = array();
	while ($row = $res->fetch_row())
	{
		$loc = array();
		$loc['lat'] = (float)$row[0];
		$loc['lon'] = (float)$row[1];
		$loc['cmt'] = $row[2];
		$json['data'][] = $loc;
		unset($loc);
	}
	$res->close();
	break;

	// Избранные локации на карте
	case 'mylocmap':
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$bbox = explode(',', $_GET['bbox']);
	$lat1 = (float)$bbox[0];
	$lon1 = (float)$bbox[1];
	$lat2 = (float)$bbox[2];
	$lon2 = (float)$bbox[3];
	$callback = $_GET['callback'];
	$mob = (isset($_GET['mobile']) ? (bool)$_GET['mobile'] : false);
	$uid = $UserManager->uID;
	if (!$res = QuerySql("SELECT latitude,longitude,comment FROM locations WHERE uid=$uid AND latitude BETWEEN $lat1 AND $lat2 AND longitude BETWEEN $lon1 AND $lon2"))
	{
		$json['error'] = 'database';
		break;
	}
	unset($json); // здесь используется JSON-P
	Header('Content-Type: application/javascript');
	$json['error'] = null;
	$json['data']['type'] = 'FeatureCollection';
	$json['data']['features'] = array();
	$loc['type'] = 'Feature';
	$loc['options']['iconColor'] = '#00D000';
	$loc['geometry']['type'] = 'Point';
	$propContent = ($mob ? 'balloonContent' : 'hintContent');
	while ($row = $res->fetch_row())
	{
		$loc['geometry']['coordinates'][0] = (float)$row[0];
		$loc['geometry']['coordinates'][1] = (float)$row[1];
		$loc['id'] = 'loc'.substr(md5($row[0].$row[1]), 0, 4);
		$loc['properties'][$propContent] = '<b>Локация:</b><br>'.htmlspecialchars($row[2]);
		$json['data']['features'][] = $loc;
	}
	$res->close();
	$db->close();
	echo 'typeof '.$callback.' === \'function\' && '.$callback.'('.json_encode($json).');';
	exit;
	break;

	// Добавление/изменение локации
	case 'addloc':
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	if (!$UserManager->checkToken($_GET['token']))
	{
		$json['error'] = 'token';
		break;
	}
	$uid = $UserManager->uID;
	$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
	$lon = isset($_GET['lon']) ? (float)$_GET['lon'] : null;
	$cmt = isset($_GET['cmt']) ? trim(preg_replace('/\s+/', ' ', $_GET['cmt'])) : null;
	if ($cmt == '') $cmt = null;

	if (is_null($lat) || is_null($lon) || is_null($cmt))
	{
		$json['error'] = 'form';
		break;
	}
	$lat = getFloatCoord($lat);
	$lon = getFloatCoord($lon);
	if (!$res = QuerySql("SELECT uid FROM locations WHERE uid=$uid AND latitude=$lat AND longitude=$lon"))
	{
		$json['error'] = 'database';
		break;
	}
	$cmt = $db->real_escape_string($cmt);
	$exist = $res->num_rows > 0;
	$res->close();
	$json['lat'] = (float)$lat;
	$json['lon'] = (float)$lon;
	$json['cmt'] = $cmt;
	if ($exist)
	{
		QuerySql("UPDATE locations SET comment='$cmt' WHERE uid=$uid AND latitude=$lat AND longitude=$lon");
		$json['new'] = false;
		$json['result'] = true;
		break;
	}
	$res = QuerySql("SELECT COUNT(uid) FROM locations WHERE uid=$uid");
	$row = $res->fetch_row();
	$res->close();
	if ($row[0] >= LOC_MAX)
	{
		$json['error'] = 'limit';
		break;
	}
	if (!QuerySql("INSERT INTO locations (uid,latitude,longitude,comment) VALUES ($uid,$lat,$lon,'$cmt')"))
	{
		$json['error'] = 'unknown';
		break;
	}
	$json['new'] = true;
	$json['result'] = true;
	break;

	// Удаление локации
	case 'delloc':
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	if (!$UserManager->checkToken($_GET['token']))
	{
		$json['error'] = 'token';
		break;
	}
	$uid = $UserManager->uID;
	$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
	$lon = isset($_GET['lon']) ? (float)$_GET['lon'] : null;

	if (is_null($lat) || is_null($lon))
	{
		$json['error'] = 'form';
		break;
	}
	$lat = getFloatCoord($lat);
	$lon = getFloatCoord($lon);
	if (!QuerySql("DELETE FROM locations WHERE uid=$uid AND latitude=$lat AND longitude=$lon"))
	{
		$json['error'] = 'database';
		break;
	}
	$json['result'] = true;
	break;

	// Управление приглашениями
	case 'myinvites':
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	$data = $UserManager->listInvites();
	if (!is_array($data))
	{
		$json['error'] = 'unknown';
		break;
	}
	$json['result'] = true;
	$json['data'] = $data;
	break;

	// Создание приглашения
	case 'createinv':
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	if (!$UserManager->checkToken($_GET['token']))
	{
		$json['error'] = 'token';
		break;
	}
	$level = isset($_GET['level']) ? (int)$_GET['level'] : 1;
	$json['result'] = $UserManager->createInvite($level);
	if (!$json['result'])
		$json['error'] = $UserManager->LastError;
	break;

	// Изменение приглашения
	case 'updateinv':
	if (!$UserManager->checkToken($_POST['token']))
	{
		$json['error'] = 'token';
		break;
	}
	$invite = isset($_POST['invite']) ? $_POST['invite'] : null;
	$level = isset($_POST['level']) ? (int)$_POST['level'] : null;

	if (is_null($invite) || is_null($level))
	{
		$json['error'] = 'form';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$json['result'] = $UserManager->updateInvite($invite, $level);
	if (!$json['result'])
		$json['error'] = $UserManager->LastError;
	break;

	// Удаление приглашения
	case 'deleteinv':
	if (!$UserManager->checkToken($_POST['token']))
	{
		$json['error'] = 'token';
		break;
	}
	$invite = isset($_POST['invite']) ? $_POST['invite'] : null;

	if (is_null($invite))
	{
		$json['error'] = 'form';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$json['result'] = $UserManager->deleteInvite($invite);
	if (!$json['result'])
		$json['error'] = $UserManager->LastError;
	break;

	// Создание API ключа
	case 'createapikey':
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	if (!$UserManager->checkToken($_POST['token']))
	{
		$json['error'] = 'token';
		break;
	}
	$type = isset($_POST['type']) ? (int)$_POST['type'] : null;
	$ApiKey = $UserManager->createApiKey($type);
	if($ApiKey === false)
	{
		$json['result'] = false;
		if (!$json['result'])
			$json['error'] = $UserManager->LastError;
		break;
	}
	$json['result'] = true;
	$json['data'] = array();
	switch($type)
	{
		case 1: $json['data']['r'] = $ApiKey;
		break;
		case 2: $json['data']['w'] = $ApiKey;
		break;
	}
	break;

	// Получение информации о пользователе
	case 'getuser':
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
	if (isset($_GET['invite']))
	{
		if (strlen($_GET['invite']) != INVITE_LEN)
		{
			$json['error'] = 'form';
			break;
		}
		$info = $UserManager->getInviteInfo($_GET['invite']);
		if (!$info)
		{
			$json['error'] = 'invite';
			break;
		}
		$uid = $info['uid'];
	}
	elseif (isset($_GET['uid']))
	{
		$uid = $_GET['uid'];
	}
	else
	{
		$json['error'] = 'form';
		break;
	}
	$info = $UserManager->getUserInfo($uid);
	$json['result'] = !is_null($info['login']);
	if ($json['result'])
	{
		$json['uid'] = (int)$info['uid'];
		$json['login'] = $info['login'];
		$json['nick'] = $info['nick'];
		$json['regdate'] = $info['regdate'];
		$json['level'] = (int)$info['level'];
		if (isset($info['ban_reason']))
			$json['ban_reason'] = $info['ban_reason'];
		$json['lastupdate'] = $info['lastupdate'];
		$inv = $UserManager->listInvites($json['uid']);
		if (is_array($inv))
		{
			$json['inv_invited'] = 0;
			$json['inv_created'] = count($inv);
			for ($i = 0; $i < count($inv); $i++)
			{
				if (!empty($inv[$i]['nick']))
					$json['inv_invited']++;
			}
		}
		$json['inv_left'] = (int)$info['invites'];
		$json['puid'] = (int)$info['puid'];
		$info = $UserManager->getUserInfo($info['puid']);
		$json['refuser'] = $info['nick'];
	}
	else
	{
		$json['error'] = 'notfound';
	}
	break;

	// Изменение информации о пользователе
	case 'setuser':
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
	if (!$UserManager->checkToken($_POST['token']))
	{
		$json['error'] = 'token';
		break;
	}
	if (!isset($_POST['uid']))
	{
		$json['error'] = 'form';
		break;
	}

	$uid = (int)$_POST['uid'];
	$level = isset($_POST['level']) ? (int)$_POST['level'] : null;
	$ban_reason = isset($_POST['ban_reason']) ? $_POST['ban_reason'] : null;

	if (!is_null($level))
	{
		$json['result'] = $UserManager->admSetLevel($uid, $level);
		if ($json['result'])
			$json['result'] = $UserManager->admBanReason($uid, $ban_reason);
		if (!$json['result'])
			$json['error'] = 'database';
		break;
	}

	$invites = isset($_POST['invites']) ? (int)$_POST['invites'] : null;

	if (!is_null($invites) && $invites >= 0)
	{
		$json['result'] = $UserManager->admSetInvites($uid, $invites);
		if (!$json['result'])
			$json['error'] = 'database';
		break;
	}

	$json['error'] = 'form';
	break;

	// Сброс пароля пользователя
	case 'resetpass':
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
	if (!$UserManager->isUserLogin($_GET['login']))
	{
		$json['error'] = 'notfound';
		break;
	}
	$json['pass'] = $UserManager->admResetPass($_GET['login']);
	if ($json['pass'] === false)
	{
		$json['error'] = 'database';
		unset($json['pass']);
	}
	$json['result'] = isset($json['pass']);
	break;

	// Статистика пользователя
	case 'stat':
	set_time_limit(30);
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$uid = $UserManager->uID;
	if (!$res = QuerySql("SELECT COUNT(id) FROM uploads WHERE uid=$uid"))
	{
		$json['error'] = 'database';
		break;
	}
	$row = $res->fetch_row();
	$res->close();
	$json['stat']['total'] = (int)$row[0];
	if (!$res = QuerySql("SELECT COUNT(id) FROM uploads JOIN BASE_TABLE USING(id) JOIN GEO_TABLE USING(BSSID) WHERE uid=$uid AND quadkey IS NOT NULL"))
	{
		$json['error'] = 'database';
		break;
	}
	$row = $res->fetch_row();
	$res->close();
	$json['stat']['onmap'] = (int)$row[0];
	if (!$res = QuerySql("SELECT COUNT(id) FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid AND NoBSSID=0"))
	{
		$json['error'] = 'database';
		break;
	}
	$row = $res->fetch_row();
	$res->close();
	$json['stat']['bssids'] = (int)$row[0];
	if (!$res = QuerySql("SELECT BSSID FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid GROUP BY BSSID"))
	{
		$json['error'] = 'database';
		break;
	}
	$json['stat']['uniqbss'] = $res->num_rows;
	$res->close();
	$json['result'] = true;
	break;

	// Статистика пользователя по комментариям
	case 'stcmt':
	set_time_limit(30);
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$uid = $UserManager->uID;
	if ($res = QuerySql("SELECT cmtid, COUNT(cmtid) FROM (SELECT cmtid FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) cmt GROUP BY cmtid ORDER BY COUNT(cmtid) DESC"))
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
	$json['result'] = true;
	break;

	// Статистика пользователя по устройствам
	case 'stdev':
	set_time_limit(30);
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$uid = $UserManager->uID;
	$json['stat']['top'] = TOP_NAME;
	if ($res = QuerySql("SELECT COUNT(DISTINCT name) FROM (SELECT name FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) dev WHERE name != ''"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT name, COUNT(name) FROM (SELECT name FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) dev WHERE name != '' GROUP BY name ORDER BY COUNT(name) DESC LIMIT ".TOP_NAME))
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
	$json['result'] = true;
	break;

	// Статистика пользователя по портам
	case 'stport':
	set_time_limit(30);
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$uid = $UserManager->uID;
	$json['stat']['top'] = TOP_PORT;
	if ($res = QuerySql("SELECT COUNT(DISTINCT Port) FROM (SELECT Port FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) prt WHERE NOT(Port IS NULL)"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT Port, COUNT(Port) FROM (SELECT Port FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) prt WHERE NOT(Port IS NULL) GROUP BY Port ORDER BY COUNT(Port) DESC LIMIT ".TOP_PORT))
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
	$json['result'] = true;
	break;

	// Статистика пользователя по авторизации
	case 'stauth':
	set_time_limit(30);
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$uid = $UserManager->uID;
	$json['stat']['top'] = TOP_AUTH;
	if ($res = QuerySql("SELECT COUNT(DISTINCT Authorization) FROM (SELECT Authorization FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) auth WHERE Authorization != ''"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT Authorization, COUNT(Authorization) FROM (SELECT Authorization FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) auth WHERE Authorization != '' GROUP BY Authorization ORDER BY COUNT(Authorization) DESC LIMIT ".TOP_AUTH))
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
	$json['result'] = true;
	break;

	// Статистика пользователя по BSSID
	case 'stbss':
	set_time_limit(30);
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$uid = $UserManager->uID;
	$json['stat']['top'] = TOP_BSSID;
	if ($res = QuerySql("SELECT COUNT(DISTINCT BSSID) FROM (SELECT NoBSSID,BSSID FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) bss WHERE NoBSSID = 0"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT BSSID, COUNT(BSSID) FROM (SELECT NoBSSID,BSSID FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) bss WHERE NoBSSID = 0 GROUP BY BSSID ORDER BY COUNT(BSSID) DESC LIMIT ".TOP_BSSID))
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
	$json['result'] = true;
	break;

	// Статистика пользователя по ESSID
	case 'stess':
	set_time_limit(30);
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$uid = $UserManager->uID;
	$json['stat']['top'] = TOP_ESSID;
	if ($res = QuerySql("SELECT COUNT(DISTINCT ESSID) FROM (SELECT ESSID FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid)"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT ESSID, COUNT(ESSID) FROM (SELECT ESSID FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) ess GROUP BY ESSID ORDER BY COUNT(ESSID) DESC LIMIT ".TOP_ESSID))
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
	$json['result'] = true;
	break;

	// Статистика пользователя по типам защиты
	case 'stsec':
	set_time_limit(30);
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$uid = $UserManager->uID;
	$json['stat']['top'] = TOP_SECURITY;
	if ($res = QuerySql("SELECT COUNT(DISTINCT Security) FROM (SELECT Security FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) sec"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT Security, COUNT(Security) FROM (SELECT Security FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) sec GROUP BY Security ORDER BY COUNT(Security) DESC LIMIT ".TOP_SECURITY))
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
	$json['result'] = true;
	break;

	// Статистика пользователя по ключам
	case 'stkey':
	set_time_limit(30);
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$uid = $UserManager->uID;
	$json['stat']['top'] = TOP_WIFI_KEY;
	if ($res = QuerySql("SELECT COUNT(DISTINCT WiFiKey) FROM (SELECT WiFiKey FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) wifi"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT WiFiKey, COUNT(WiFiKey) FROM (SELECT WiFiKey FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) wifi GROUP BY WiFiKey ORDER BY COUNT(WiFiKey) DESC LIMIT ".TOP_WIFI_KEY))
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
	$json['result'] = true;
	break;

	// Статистика пользователя по WPS пинам
	case 'stwps':
	set_time_limit(30);
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$uid = $UserManager->uID;
	$json['stat']['top'] = TOP_WPS_PIN;
	if ($res = QuerySql("SELECT COUNT(DISTINCT WPSPIN) FROM (SELECT WPSPIN FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) pin WHERE WPSPIN != 1"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT WPSPIN, COUNT(WPSPIN) FROM (SELECT WPSPIN FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) pin WHERE WPSPIN != 1 GROUP BY WPSPIN ORDER BY COUNT(WPSPIN) DESC LIMIT ".TOP_WPS_PIN))
	{
		$json['stat']['data'] = array();
		while ($row = $res->fetch_row())
		{
			$data = array();
			$data[] = (int)$row[1];
			$data[] = pin2str($row[0]);
			$json['stat']['data'][] = $data;
		}
		$res->close();
	}
	$json['result'] = true;
	break;

	// Статистика пользователя по DNS
	case 'stdns':
	set_time_limit(30);
	if (!$UserManager->isLogged())
	{
		$json['error'] = 'unauthorized';
		break;
	}
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	$uid = $UserManager->uID;
	$json['stat']['top'] = TOP_DNS;
	if ($res = QuerySql("SELECT COUNT(DISTINCT DNS) FROM (
	SELECT DNS1 AS DNS FROM (SELECT DNS1 FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) tdns1 WHERE DNS1 != 0 
	UNION ALL 
	SELECT DNS2 AS DNS FROM (SELECT DNS2 FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) tdns2 WHERE DNS2 != 0 
	UNION ALL 
	SELECT DNS3 AS DNS FROM (SELECT DNS3 FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) tdns3 WHERE DNS3 != 0) DNSTable"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT DNS, COUNT(DNS) FROM (
	SELECT DNS1 AS DNS FROM (SELECT DNS1 FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) tdns1 WHERE DNS1 != 0 
	UNION ALL 
	SELECT DNS2 AS DNS FROM (SELECT DNS2 FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) tdns2 WHERE DNS2 != 0 
	UNION ALL 
	SELECT DNS3 AS DNS FROM (SELECT DNS3 FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) tdns3 WHERE DNS3 != 0) DNSTable 
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
	$json['result'] = true;
	break;
}
$db->close();

if ($json != null)
{
	header('Content-Type: application/json');
	echo json_encode($json);
}
