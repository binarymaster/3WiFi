<?php
require_once 'db.php';
require_once 'utils.php';
require_once 'user.class.php';

global $db;

$UserManager = new User();
$UserManager->load();

$json = array();
$json['result'] = false;
if (!db_connect())
{
	$json['error'] = 'database';
	echo json_encode($json);
	exit();
}

header('Content-Type: application/json');

$action = isset($_GET['a']) ? $_GET['a'] : NULL;

if ($action == NULL)
{
	echo json_encode($json);
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
		if (strlen($_POST['invite']) != 32 || !$UserManager->isValidInvite($_POST['invite']))
		{
			$json['error'] = 'invite';
			break;
		}
		if (strlen($_POST['login']) < 5 || strlen($_POST['login']) > 30)
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
		if (strlen($_POST['invite']) != 32 || !$UserManager->isValidInvite($_POST['invite']))
		{
			$json['error'] = 'invite';
			break;
		}
		if (strlen($_POST['nick']) < 5 || strlen($_POST['nick']) > 30)
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
	if (strlen($newInvite) != 12 || !$UserManager->isValidInvite($newInvite))
	{
		$json['error'] = 'invite';
		break;
	}
	if (strlen($newLogin) < 5 || strlen($newLogin) > 30 || $UserManager->isUserLogin($newLogin))
	{
		$json['error'] = 'login';
		break;
	}
	if (strlen($newNick) < 5 || strlen($newNick) > 30 || $UserManager->isUserNick($newNick))
	{
		$json['error'] = 'nick';
		break;
	}
	if (strlen($newPassword) < 6 || strlen($newPassword) > 100)
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
	// $_POST['nick']
	break;

	// Смена пароля пользователя
	case 'changepass':
	if ($UserManager->Level < 1)
	{
		$json['error'] = 'lowlevel';
		break;
	}
	// $_POST['oldpass']
	// $_POST['password']
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
}
$db->close();

echo json_encode($json);
?>