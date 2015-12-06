<?php
require_once 'auth.php';
$incscript = file_get_contents('counter.txt');

if (!isset($page)) $page = (isset($_GET['page']) ? $_GET['page'] : '');
if ($page == '') $page = 'index';

if ($page == 'index' ||
	$page == 'left' ||
	$page == 'main' ||
	$page == 'map' ||
	$page == 'login' ||
	$page == 'registration' ||
	$page == 'find' ||
	$page == 'find_ranges' ||
	$page == 'devicemac' ||
	$page == 'upload' ||
	$page == 'graph' ||
	$page == 'stat')
{
	global $level, $login, $nick;
	if ($login == '') {
		$action_login = 'login';
		$action_login_name = 'Войти';
		$action_reg = 'reg';
		$action_reg_name = 'Зарегистрироваться';
	}else {
		$action_login = 'logout';
		$action_login_name = 'Выйти';
		$action_reg = 'inv';
		$action_reg_name = 'Пригласить';
	}	
	
	$lat = 55.76;
	$lon = 37.64;
	$rad = 2;
	if (isset($_GET['lat'])) $lat = (float)$_GET['lat'];
	if (isset($_GET['lon'])) $lon = (float)$_GET['lon'];
	if (isset($_GET['rad'])) $rad = (float)$_GET['rad'];

	$invite = getParam('invite');
	
	$content = file_get_contents($page.'.html');

	$content = str_replace('%var_lat%', $lat, $content);
	$content = str_replace('%var_lon%', $lon, $content);
	$content = str_replace('%var_rad%', $rad, $content);
	$content = str_replace('%login%', $login, $content);
	$content = str_replace('%nick%', $nick, $content);
	$content = str_replace('%action%', $action_login, $content);
	$content = str_replace('%action_name%', $action_login_name, $content);
	$content = str_replace('%action_reg%', $action_reg, $content);
	$content = str_replace('%action_reg_name%', $action_reg_name, $content);
	$content = str_replace('%invite%', $invite, $content);

	echo str_replace('</body>', $incscript.'</body>', $content);
	exit();
}
?>