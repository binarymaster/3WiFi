<?php
function validPage($page)
{
	$result = '';
	if ($page == 'home' ||
		$page == 'rules' ||
		$page == 'faq' ||
		$page == 'apidoc' ||
		$page == 'map' ||
		$page == 'find' ||
		$page == 'ranges' ||
		$page == 'devmac' ||
		$page == 'wpspin' ||
		$page == 'wpspin_db' ||
		$page == 'upload' ||
		$page == 'graph' ||
		$page == 'stat' ||
		$page == 'user')
	{
		$result = $page;
	}
	return $result;
}
if (isset($_GET['redir']) && $_GET['redir'] != '')
{
	$page = validPage($_GET['redir']);
	if ($page != '')
	{
		Header('HTTP/1.0 303 See Other');
		Header('Location: ' . $page);
	}
	exit();
}

require_once 'user.class.php';
require_once 'utils.php';

session_start();

$UserManager = new User();
$UserManager->load();

$incscript = file_get_contents('counter.txt');

$page = validPage(isset($_GET['page']) ? $_GET['page'] : 'home');
if ($page == '') $page = '404';

$lat = 55.76;
$lon = 37.64;
$rad = 2;
if (isset($_GET['lat']))
{
	$_GET['lat'] = str_replace(',', '.', $_GET['lat']);
	$lat = (float)$_GET['lat'];
}
if (isset($_GET['lon']))
{
	$_GET['lon'] = str_replace(',', '.', $_GET['lon']);
	$lon = (float)$_GET['lon'];
}
if (isset($_GET['rad']))
{
	$_GET['rad'] = str_replace(',', '.', $_GET['rad']);
	$rad = (float)$_GET['rad'];
}

if (!file_exists($page.'.html')) $page = '404';
$hfile = file_get_contents($page.'.html');

$title = getStringBetween($hfile, '<title>', '</title>');
if ($title == '') $title = '3WiFi: Свободная база точек доступа';
$head = getStringBetween($hfile, '<head>', '</head>');
$content = getStringBetween($hfile, '<body>', '</body>');

$content = str_replace('%content%', $content, file_get_contents('index.html'));
$content = str_replace('%title%', $title, $content);
$content = str_replace('%head%', $head, $content);

$mb = 'menubtn';
$mbs = $mb.' mbsel';
$content = str_replace('%chk_docs%', ($page == 'home' || $page == 'faq' || $page == 'apidoc' || $page == 'rules' ? $mbs : $mb), $content);
$content = str_replace('%chk_map%', ($page == 'map' ? $mbs : $mb), $content);
$content = str_replace('%chk_find%', ($page == 'find' ? $mbs : $mb), $content);
$content = str_replace('%chk_tool%', ($page == 'ranges' || $page == 'devmac' || $page == 'wpspin' || $page == 'wpspin_db' ? $mbs : $mb), $content);
$content = str_replace('%chk_load%', ($page == 'upload' ? $mbs : $mb), $content);
$content = str_replace('%chk_st%', ($page == 'stat' || $page == 'graph' ? $mbs : $mb), $content);
$content = str_replace('%chk_user%', ($page == 'user' ? $mbs : $mb), $content);

$sm = 'submbtn';
$sms = $sm.' smsel';
$content = str_replace('%chk_home%', ($page == 'home' ? $sms : $sm), $content);
$content = str_replace('%chk_faq%', ($page == 'faq' ? $sms : $sm), $content);
$content = str_replace('%chk_apidoc%', ($page == 'apidoc' ? $sms : $sm), $content);
$content = str_replace('%chk_rul%', ($page == 'rules' ? $sms : $sm), $content);
$content = str_replace('%chk_rang%', ($page == 'ranges' ? $sms : $sm), $content);
$content = str_replace('%chk_dev%', ($page == 'devmac' ? $sms : $sm), $content);
$content = str_replace('%chk_wpsdb%', ($page == 'wpspin_db' ? $sms : $sm), $content);
$content = str_replace('%chk_wps%', ($page == 'wpspin' ? $sms : $sm), $content);
$content = str_replace('%chk_stat%', ($page == 'stat' ? $sms : $sm), $content);
$content = str_replace('%chk_grph%', ($page == 'graph' ? $sms : $sm), $content);

$profile = 'isUser: %isUser%, Nickname: "%nick%", Level: %user_access_level%, invites: %user_invites%';

$content = str_replace('%login_str%', ($UserManager->isLogged() ? 'Выход' : 'Вход'), $content);
$content = str_replace('%profile%', $profile, $content);
$content = str_replace('%isUser%', (int)$UserManager->isLogged(), $content);
$content = str_replace('%login%', htmlspecialchars($UserManager->Login), $content);
$content = str_replace('%nick%', htmlspecialchars($UserManager->Nick), $content);
$content = str_replace('%user_access_level%', $UserManager->Level, $content);
$content = str_replace('%user_invites%', $UserManager->invites, $content);
$content = str_replace('%rapikey%', $UserManager->ReadApiKey, $content);
$content = str_replace('%wapikey%', $UserManager->WriteApiKey, $content);
$content = str_replace('%regdate%', $UserManager->RegDate, $content);
$content = str_replace('%refuser%', $UserManager->InviterNickName, $content);
$content = str_replace('%var_lat%', $lat, $content);
$content = str_replace('%var_lon%', $lon, $content);
$content = str_replace('%var_rad%', $rad, $content);

echo str_replace('</body>', $incscript.'</body>', $content);
?>