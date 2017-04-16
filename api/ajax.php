<?php
include '../config.php';
require_once '../user.class.php';
require_once '../utils.php';
require_once '../db.php';

$json = array();
$UserManager = new User();

switch ($_GET['Query'])
{
	case 'GetApiKeys':
	$json['Successes'] = false;
	$login = $_POST['Login'];
	$password = $_POST['Password'];
	if (!is_null($login) && !is_null($password))
	{
		filterLogin($login);
		if (!$UserManager->Auth($password, $login, true))
			break;
		$data = $UserManager->getApiKeys();
		if (is_null($data['rapikey']))
		{
			$data['rapikey'] = $UserManager->createApiKey(1);
		}
		$json['lastupdate'] = 0;
		$json['r'] = $data['rapikey'];
		$json['w'] = $data['wapikey'];
		$json['Successes'] = true;
	}
	break;

	case 'Find':
	$json['Successes'] = false;
	$ver = $_GET['Version'];
	$key = $_GET['Key'];
	$bssid = $_GET['BSSID'];
	if ($ver != '0.5')
	{
		$json['Error'] = array('Code' => 0, 'Desc' => 'Unsupported version');
		break;
	}
	if (!is_string($key) || empty($key) || !is_string($bssid) || empty($bssid) || !ismac($bssid))
	{
		$json['Error'] = array('Code' => 0, 'Desc' => 'Wrong input');
		break;
	}
	if (!$UserManager->AuthByApiKey($key, true))
	{
		$json['Error'] = array('Code' => -100, 'Desc' => 'Wrong API key');
		break;
	}
	if ($UserManager->ApiAccess != 'read')
	{
		$json['Error'] = array('Code' => 0, 'Desc' => 'API key have no "read" rights');
		break;
	}
	if (!db_connect())
	{
		$json['Error'] = array('Code' => 0, 'Desc' => 'Database unavailable');
		break;
	}
	$bssid = mac2dec($bssid);
	$sql = "SELECT 
				WiFiKey, WPSPIN 
			FROM 
				BASE_TABLE 
			WHERE 
				BSSID = $bssid 
			ORDER BY 
				time DESC 
			LIMIT 10";
	$res = QuerySql($sql);
	$json['Keys'] = array();
	$json['WPS'] = array();
	while ($row = $res->fetch_row())
	{
		$json['Keys'][] = $row[0];
		$json['WPS'][] = ($row[1] == 1 ? '' : str_pad($row[1], 8, '0', STR_PAD_LEFT));
	}
	$json['Successes'] = true;
	break;

	case 'AppVersion':
	$json['Successes'] = true;
	$json['ActualyVersion'] = '0.5';
	$json['WhatNews'] = 'This API is depreciated and will be removed.';
	break;

	case 'GetUserInfo':
	$json['Successes'] = false;
	$ver = $_GET['Version'];
	$key = $_GET['Key'];
	if ($ver != '0.5')
	{
		$json['Error'] = array('Code' => 0, 'Desc' => 'Unsupported version');
		break;
	}
	if (!is_string($key) || empty($key))
	{
		$json['Error'] = array('Code' => 0, 'Desc' => 'Wrong input');
		break;
	}
	if (!$UserManager->AuthByApiKey($key, true))
	{
		$json['Error'] = array('Code' => -100, 'Desc' => 'Wrong API key');
		break;
	}
	$json['Nickname'] = '3WiFi User';
	$json['RegDate'] = '';
	$json['Level'] = 1;
	$json['InvCount'] = 0;
	$json['Inviter'] = 'This API is depreciated and will be removed.';
	$json['LastUpdate'] = '';
	$json['Successes'] = true;
	break;
}

Header('Content-Type: application/json');
echo json_encode($json);
?>