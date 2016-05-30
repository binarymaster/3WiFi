<?php
require_once 'db.php';
require_once 'utils.php';
require_once 'user.class.php';

global $db;

$UserManager = new User();
$UserManager->load();

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

define('LOGIN_MIN', 5);
define('LOGIN_MAX', 30);
define('NICK_MIN', 5);
define('NICK_MAX', 30);
define('PASS_MIN', 6);
define('PASS_MAX', 100);
define('INVITE_LEN', 12);

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
	if ($UserManager->isLogged())
	{
		$json['error'] = 'loggedin';
		break;
	}
	if (isset($_POST['login']) && isset($_POST['password']))
	{
		filterLogin($_POST['login']);
		$json['result'] = $UserManager->Auth($_POST['password'], $_POST['login']);
		if (!$json['result']) $json['error'] = 'loginfail';
	}
	break;

	// Выход из учётной записи
	case 'logout':
	if ($UserManager->isLogged())
	{
		$UserManager->out();
		$json['result'] = true;
	}
	else
	{
		$json['error'] = 'unauthorized';
	}
	break;

	// Проверка логина на существование
	case 'checklogin':
	if ($UserManager->isLogged())
	{
		$json['error'] = 'loggedin';
		break;
	}
	if (isset($_POST['invite']) && isset($_POST['login']))
	{
		if (strlen($_POST['invite']) != INVITE_LEN || !$UserManager->isValidInvite($_POST['invite']))
		{
			$json['error'] = 'invite';
			break;
		}
		filterLogin($_POST['login']);
		if (strlen($_POST['login']) < LOGIN_MIN || strlen($_POST['login']) > LOGIN_MAX)
		{
			$json['result'] = 'form';
			break;
		}
		$json['result'] = !$UserManager->isUserLogin($_POST['login']);
	}
	else
		$json['error'] = 'form';
	break;

	// Проверка ника на существование
	case 'checknick':
	if ($UserManager->isLogged())
	{
		$json['error'] = 'loggedin';
		break;
	}
	if (isset($_POST['invite']) && isset($_POST['nick']))
	{
		if (strlen($_POST['invite']) != INVITE_LEN || !$UserManager->isValidInvite($_POST['invite']))
		{
			$json['error'] = 'invite';
			break;
		}
		filterNick($_POST['nick']);
		if (strlen($_POST['nick']) < NICK_MIN || strlen($_POST['nick']) > NICK_MAX)
		{
			$json['result'] = 'form';
			break;
		}
		$json['result'] = !$UserManager->isUserNick($_POST['nick']);
	}
	else
		$json['error'] = 'form';
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
	if (strlen($newLogin) < LOGIN_MIN || strlen($newLogin) > LOGIN_MAX || $UserManager->isUserLogin($newLogin))
	{
		$json['error'] = 'login';
		break;
	}
	filterNick($newNick);
	if (strlen($newNick) < NICK_MIN || strlen($newNick) > NICK_MAX || $UserManager->isUserNick($newNick))
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
	if (!$res = QuerySql("SELECT id, time, cmtval, IP, Port, Authorization, name, NoBSSID, BSSID, ESSID, Security, WiFiKey, WPSPIN, latitude, longitude 
						FROM BASE_TABLE RIGHT JOIN uploads USING(id) LEFT JOIN comments USING (cmtid) LEFT JOIN GEO_TABLE USING (BSSID) WHERE uid=$uid ORDER BY time DESC LIMIT 200"))
	{
		$json['error'] = 'database';
		break;
	}
	$json['result'] = true;
	$json['data'] = array();
	while ($row = $res->fetch_row())
	{
		$ap = array();
		$ap['id'] = (int)$row[0];
		$ap['time'] = $row[1];
		$ap['comment'] = $row[2];
		$ap['ipport'] = _long2ip($row[3]);
		if ($row[4] != '') $ap['ipport'] .= ':'.$row[4];
		$ap['auth'] = $row[5];
		$ap['name'] = $row[6];
		$ap['bssid'] = ($row[7] == 0 ? dec2mac($row[8]) : '');
		$ap['essid'] = $row[9];
		$ap['sec'] = sec2str((int)$row[10]);
		$ap['key'] = $row[11];
		$ap['wps'] = ($row[12] == 1 ? '' : str_pad($row[12], 8, '0', STR_PAD_LEFT));
		$ap['lat'] = null;
		$ap['lon'] = null;
		if ($row[6] == 0 && $row[13] != 0 && $row[14] != 0)
		{
			$ap['lat'] = (float)$row[13];
			$ap['lon'] = (float)$row[14];
		}
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
	if (!$res = QuerySql("SELECT IP, Port, Authorization, name, RadioOff, Hidden, NoBSSID, BSSID, ESSID, Security, WiFiKey, WPSPIN, LANIP, LANMask, WANIP, WANMask, WANGateway, DNS1, DNS2, DNS3, latitude, longitude, cmtval 
						FROM BASE_TABLE RIGHT JOIN uploads USING(id) LEFT JOIN comments USING (cmtid) LEFT JOIN GEO_TABLE USING (BSSID) WHERE uid=$uid ORDER BY time"))
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
		$row[7] = ($row[6] == 0 ? dec2mac($row[7]) : '');
		$row[9] = sec2str((int)$row[9]);
		$row[11] = ($row[11] == 1 ? '' : str_pad($row[11], 8, '0', STR_PAD_LEFT));
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
	$level = isset($_GET['level']) ? (int)$_GET['level'] : 1;
	$json['result'] = $UserManager->createInvite($level);
	break;

	// Изменение приглашения
	case 'updateinv':
	$invite = isset($_POST['invite']) ? $_POST['invite'] : null;
	$level = isset($_POST['level']) ? (int)$_POST['level'] : null;

	if ($invite == null || $level == null)
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
	break;

	// Удаление приглашения
	case 'deleteinv':
	$invite = isset($_POST['invite']) ? $_POST['invite'] : null;

	if ($invite == null)
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
	break;

	// Создание API ключа
	case 'createapikey':
	$type = isset($_POST['type']) ? (int)$_POST['type'] : null;
	$ApiKey = $UserManager->createApiKey($type);
	if($ApiKey === false)
	{
		$json['result'] = false;
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
	if (!$UserManager->isUserLogin($_GET['login']))
	{
		$json['error'] = 'login';
		break;
	}
	$json['pass'] = $UserManager->resetPass($_GET['login']);
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
	$json['stat']['top'] = $topname;
	if ($res = QuerySql("SELECT COUNT(DISTINCT name) FROM (SELECT name FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) dev WHERE name != ''"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT name, COUNT(name) FROM (SELECT name FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) dev WHERE name != '' GROUP BY name ORDER BY COUNT(name) DESC LIMIT $topname"))
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
	$json['stat']['top'] = $topPort;
	if ($res = QuerySql("SELECT COUNT(DISTINCT Port) FROM (SELECT Port FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) prt WHERE NOT(Port IS NULL)"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT Port, COUNT(Port) FROM (SELECT Port FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) prt WHERE NOT(Port IS NULL) GROUP BY Port ORDER BY COUNT(Port) DESC LIMIT $topPort"))
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
	$json['stat']['top'] = $topauth;
	if ($res = QuerySql("SELECT COUNT(DISTINCT Authorization) FROM (SELECT Authorization FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) auth WHERE Authorization != ''"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT Authorization, COUNT(Authorization) FROM (SELECT Authorization FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) auth WHERE Authorization != '' GROUP BY Authorization ORDER BY COUNT(Authorization) DESC LIMIT $topauth"))
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
	$json['stat']['top'] = $topbssid;
	if ($res = QuerySql("SELECT COUNT(DISTINCT BSSID) FROM (SELECT NoBSSID,BSSID FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) bss WHERE NoBSSID = 0"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT BSSID, COUNT(BSSID) FROM (SELECT NoBSSID,BSSID FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) bss WHERE NoBSSID = 0 GROUP BY BSSID ORDER BY COUNT(BSSID) DESC LIMIT $topbssid"))
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
	$json['stat']['top'] = $topessid;
	if ($res = QuerySql("SELECT COUNT(DISTINCT ESSID) FROM (SELECT ESSID FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) ess"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT ESSID, COUNT(ESSID) FROM (SELECT ESSID FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) ess GROUP BY ESSID ORDER BY COUNT(ESSID) DESC LIMIT $topessid"))
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
	$json['stat']['top'] = $topSecurity;
	if ($res = QuerySql("SELECT COUNT(DISTINCT Security) FROM (SELECT Security FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) sec"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT Security, COUNT(Security) FROM (SELECT Security FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) sec GROUP BY Security ORDER BY COUNT(Security) DESC LIMIT $topSecurity"))
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
	$json['stat']['top'] = $topWiFiKey;
	if ($res = QuerySql("SELECT COUNT(DISTINCT WiFiKey) FROM (SELECT WiFiKey FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) wifi"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT WiFiKey, COUNT(WiFiKey) FROM (SELECT WiFiKey FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) wifi GROUP BY WiFiKey ORDER BY COUNT(WiFiKey) DESC LIMIT $topWiFiKey"))
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
	$json['stat']['top'] = $topWPSPIN;
	if ($res = QuerySql("SELECT COUNT(DISTINCT WPSPIN) FROM (SELECT WPSPIN FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) pin WHERE WPSPIN != 1"))
	{
		$row = $res->fetch_row();
		$json['stat']['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql("SELECT WPSPIN, COUNT(WPSPIN) FROM (SELECT WPSPIN FROM uploads JOIN BASE_TABLE USING(id) WHERE uid=$uid) pin WHERE WPSPIN != 1 GROUP BY WPSPIN ORDER BY COUNT(WPSPIN) DESC LIMIT $topWPSPIN"))
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
	$json['stat']['top'] = $topDNS;
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
	$json['result'] = true;
	break;
}
$db->close();

if ($json != null)
{
	header('Content-Type: application/json');
	echo json_encode($json);
}
?>