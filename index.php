<?php
$incscript = file_get_contents('counter.txt');

if (!isset($page)) $page = (isset($_GET['page']) ? $_GET['page'] : '');
if ($page == '') $page = 'index';

if ($page == 'index' ||
	$page == 'left' ||
	$page == 'main' ||
	$page == 'map' ||
	$page == 'map2' ||
	$page == 'find' ||
	$page == 'find_ranges' ||
	$page == 'devicemac' ||
	$page == 'upload' ||
	$page == 'graph' ||
	$page == 'stat')
{
	$lat = 55.76;
	$lon = 37.64;
	$rad = 2;
	if (isset($_GET['lat'])) $lat = (float)$_GET['lat'];
	if (isset($_GET['lon'])) $lon = (float)$_GET['lon'];
	if (isset($_GET['rad'])) $rad = (int)$_GET['rad'];

	$content = file_get_contents($page.'.html');

	$content = str_replace('%var_lat%', $lat, $content);
	$content = str_replace('%var_lon%', $lon, $content);
	$content = str_replace('%var_rad%', $rad, $content);

	echo str_replace('</body>', $incscript.'</body>', $content);
	exit();
}
?>