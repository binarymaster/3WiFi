<?php
include 'config.php';

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
		$page == 'upload' ||
		$page == 'graph' ||
		$page == 'stat' ||
		$page == 'user')
	{
		$result = $page;
	}
	return $result;
}
function detectBestLang($langs)
{
	$langs = explode(',', $langs);
	$parsed = array();
	foreach ($langs as $lang)
	{
		$v = explode(';q=', $lang);
		if (!isset($v[1])) $v[1] = 1;
		$parsed[$v[0]] = (float)$v[1];
	}
	$available = scandir('l10n/');
	arsort($parsed);
	foreach ($parsed as $lang => $q)
	{
		if ( in_array("$lang.php", $available, true) )
		{
			return $lang;
		}
	}
	return 'en';
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

$lat = DEFAULT_LAT;
$lon = DEFAULT_LON;
$rad = DEFAULT_RAD;

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

$langs = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
if (empty($langs)) $langs = 'en';

$lang = detectBestLang($langs);
include_once "l10n/$lang.php";

if (!file_exists($page.'.html')) $page = '404';
$hfile = file_get_contents($page.'.html');

$title = getStringBetween($hfile, '<title>', '</title>');
if ($title == '') $title = $l10n['title'];
$head = getStringBetween($hfile, '<head>', '</head>');
$content = getStringBetween($hfile, '<body>', '</body>');

$content = str_replace('%content%', $content, file_get_contents('index.html'));
$content = str_replace('%title%', $title, $content);
$content = str_replace('%head%', $head, $content);

$theme_base = 'themes';
$themes = scandir("$theme_base/");
$filter = array();
foreach ($themes as $theme_name)
{
	$theme_name = preg_replace("/[^a-zA-Z0-9\-_]+/", "", $theme_name);
	if ( file_exists("$theme_base/$theme_name/theme.php") )
		$filter[] = $theme_name;
}
$themes = $filter;

$theme = $_COOKIE['theme'];
$theme_data = array();
if ( isset($theme) && in_array($theme, $themes, true) )
{
	require_once "$theme_base/$theme/theme.php";
}
else
{
	// use default theme
	$theme_data['css'] = 'css/style.css?2017-03-05-r4';
	$theme_data['head'] = '';
	$theme_data['ajax'] = 'img/loadsm.gif';
}
$content = str_replace('%theme_css%', $theme_data['css'], $content);
$content = str_replace('%theme_head%', $theme_data['head'], $content);
$content = str_replace('%theme_ajax%', $theme_data['ajax'], $content);

$themes_str = '['.(count($themes) > 0 ? "'".implode("','", $themes)."'" : '').']';
$content = str_replace('%theme%', $theme, $content);
$content = str_replace('%themes%', $themes_str, $content);

$mb = 'menubtn';
$mbs = $mb.' mbsel';
$content = str_replace('%chk_docs%', ($page == 'home' || $page == 'faq' || $page == 'apidoc' || $page == 'rules' ? $mbs : $mb), $content);
$content = str_replace('%chk_map%', ($page == 'map' ? $mbs : $mb), $content);
$content = str_replace('%chk_find%', ($page == 'find' ? $mbs : $mb), $content);
$content = str_replace('%chk_tool%', ($page == 'ranges' || $page == 'devmac' || $page == 'wpspin' ? $mbs : $mb), $content);
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
$content = str_replace('%chk_wps%', ($page == 'wpspin' ? $sms : $sm), $content);
$content = str_replace('%chk_stat%', ($page == 'stat' ? $sms : $sm), $content);
$content = str_replace('%chk_grph%', ($page == 'graph' ? $sms : $sm), $content);

$broadcast = '';
if(TRY_USE_MEMORY_TABLES)
{
	$DataBaseStatus = GetStatsValue(STATS_DATABASE_STATUS);
	if($DataBaseStatus == DATABASE_PREPARE)
	{
		$broadcast .= '<p class=failure><b>'.$l10n['warning'].'</b> '.$l10n['db_prepare'].'</p>';
	}
}
$content = str_replace('%broadcast%', $broadcast, $content);

$profile = 'isUser: %isUser%, Nickname: "%nick%", Level: %user_access_level%, invites: %user_invites%';

$content = str_replace('%login_str%', ($UserManager->isLogged() ? $l10n['menu_logout'] : $l10n['menu_login']), $content);
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

foreach ($l10n as $key => $value)
{
	$content = str_replace("%l10n_$key%", $value, $content);
}

echo str_replace('</body>', $incscript.'</body>', $content);
?>