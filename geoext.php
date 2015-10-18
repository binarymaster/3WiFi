<?php
function GeoLocateAP($bssid)
{
	$coords = GetFromYandex($bssid);
	if ($coords == '') $coords = GetFromAlterGeo($bssid);
	if ($coords == '') $coords = GetFromMylnikov($bssid);
	return $coords;
}
function GetFromYandex($bssid)
{
	$bssid = str_replace(":","",$bssid);
	$bssid = str_replace("-","",$bssid);
	$data = cURL_Get("http://mobile.maps.yandex.net/cellid_location/?clid=1866854&lac=-1&cellid=-1&operatorid=null&countrycode=null&signalstrength=-1&wifinetworks=$bssid:-65&app");

	$result = '';
	$latitude = getStringBetween($data, ' latitude="', '"');
	$longitude = getStringBetween($data, ' longitude="', '"');
	if ($latitude != '' && $longitude != '')
	{
		$result = $latitude.';'.$longitude;
	}
	return $result;
}
function GetFromAlterGeo($bssid)
{
	$bssid = strtolower(str_replace(":","-",$bssid));
	$data = cURL_Get("http://api.platform.altergeo.ru/loc/json?browser=firefox&sensor=false&wifi=mac:$bssid|ss:0");

	$result = '';
	$json = json_decode($data);
	if ($json->status == 'OK')
	{
		if ($json->accuracy < 50000)
		{
			$latitude = $json->location->lat;
			$longitude = $json->location->lng;
			$result = $latitude.';'.$longitude;
		}
	}
	return $result;
}
function GetFromMylnikov($bssid)
{
	$tries = 3;
	while (!($data = cURL_Get("http://api.mylnikov.org/wifi/main.py/get?bssid=$bssid")) && ($tries > 0))
	{
		$tries--;
		sleep(2);
	}

	$result = '';
	$json = json_decode($data);
	if ($json->result == 200)
	{
		$latitude = $json->data->lat;
		$longitude = $json->data->lon;
		$result = $latitude.';'.$longitude;
	}
	return $result;
}
function getStringBetween($string, $start, $end)
{
	$string = " ".$string;
	$ini = strpos($string, $start);
	if ($ini == 0) return "";
	$ini += strlen($start);
	$len = strpos($string, $end, $ini) - $ini;
	return substr($string, $ini, $len);
}
function cURL_Get($url)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_ENCODING, 'utf-8');
	curl_setopt($ch, CURLOPT_TIMEOUT, 3);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
	curl_setopt($ch, CURLOPT_USERAGENT, 'PHP/'.phpversion());
	curl_setopt($ch, CURLOPT_URL, $url);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}
?>