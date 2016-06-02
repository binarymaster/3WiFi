<?php
require_once 'utils.php';

function GeoLocateAP($bssid)
{
	$coords = GetFromYandex($bssid);
	if ($coords == '') $coords = GetFromAlterGeo($bssid);
	if ($coords == '') $coords = GetFromMylnikov($bssid);
	return $coords;
}
function GetFromYandex($bssid)
{
	$tries = 3;
	$bssid = str_replace(":","",$bssid);
	$bssid = str_replace("-","",$bssid);
	while (!($data = cURL_Get("https://mobile.maps.yandex.net/cellid_location/?clid=1866854&lac=-1&cellid=-1&operatorid=null&countrycode=null&signalstrength=-1&wifinetworks=$bssid:0&app")) && ($tries > 0))
	{
		$tries--;
		sleep(2);
	}

	$result = '';
	$latitude = getStringBetween($data, ' latitude="', '"');
	$longitude = getStringBetween($data, ' longitude="', '"');
	if ($latitude != '' && $longitude != '')
	{
		$result = $latitude.';'.$longitude.';yandex';
	}
	return $result;
}
function GetFromAlterGeo($bssid)
{
	$tries = 3;
	$bssid = strtolower(str_replace(":","-",$bssid));
	while (!($data = cURL_Get("http://api.platform.altergeo.ru/loc/json?browser=firefox&sensor=false&wifi=mac:$bssid|ss:0")) && ($tries > 0))
	{
		$tries--;
		sleep(2);
	}

	$result = '';
	$json = json_decode($data);
	if ($json->status == 'OK')
	{
		if ($json->accuracy < 50000)
		{
			$latitude = $json->location->lat;
			$longitude = $json->location->lng;
			$result = $latitude.';'.$longitude.';altergeo';
		}
	}
	return $result;
}
function GetFromMylnikov($bssid)
{
	$tries = 3;
	$proto = 'https:';
	while (!($data = cURL_Get("$proto//api.mylnikov.org/wifi/main.py/get?bssid=$bssid", ''/* 127.0.0.1:3128 */)) && ($tries > 0))
	{
		$tries--;
		$proto = ($tries % 2 == 0 ? 'http:' : 'https:');
		sleep(2);
	}

	$result = '';
	$json = json_decode($data);
	if ($json->result == 200)
	{
		$latitude = $json->data->lat;
		$longitude = $json->data->lon;
		$result = $latitude.';'.$longitude.';mylnikov';
	}
	return $result;
}
function cURL_Get($url, $proxy = '')
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_ENCODING, 'utf-8');
	curl_setopt($ch, CURLOPT_TIMEOUT, 3);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
	curl_setopt($ch, CURLOPT_USERAGENT, 'PHP/'.phpversion().' 3WiFi/2.0');
	if ($proxy != '')
	{
		curl_setopt($ch, CURLOPT_PROXY, $proxy);
		//curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyauth);
	}
	curl_setopt($ch, CURLOPT_URL, $url);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}
?>