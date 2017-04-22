<?php
require_once 'utils.php';

function my_gzdecode($data)
{
	$g = tempnam('', 'gztmp');
	@file_put_contents($g, $data);
	ob_start();
	readgzfile($g);
	$d = ob_get_clean();
	unlink($g);
	return $d;
}
function GeoLocateAP($bssid)
{
	$coords = GetFromYandex($bssid);
	if ($coords == '') $coords = GetFromMylnikov($bssid);
	if ($coords == '') $coords = GetFromAlterGeo($bssid);
	if ($coords == '') $coords = GetFromMicrosoft($bssid);
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
	if (!$data) return $result;
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
	if (!$data) return $result;
	$json = json_decode($data);
	if (!$json) return $result;
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
function GetFromMicrosoft($bssid)
{
	$headers = array(
		'Accept: */*',
		'Accept-Language: en-us',
		'Content-Type: text/xml',
		'Accept-Encoding: gzip, deflate',
		'User-Agent: PHP/'.phpversion().' 3WiFi/2.0',
		'Cache-Control: no-cache',
	);
	$tries = 2;
	$bssid = strtolower(str_replace("-",":",$bssid));

	$data = null;
	while (!$data && ($tries > 0))
	{
		$time = date('Y-m-d\TH:i:s.uP');
		$xmlRequest = '<GetLocationUsingFingerprint xmlns="http://inference.location.live.com"><RequestHeader><Timestamp>'.$time.'</Timestamp><ApplicationId>e1e71f6b-2149-45f3-a298-a20682ab5017</ApplicationId><TrackingId>21BF9AD6-CFD3-46B3-B041-EE90BD34FDBC</TrackingId><DeviceProfile ClientGuid="0fc571be-4624-4ce0-b04e-911bdeb1a222" Platform="Windows7" DeviceType="PC" OSVersion="7600.16695.amd64fre.win7_gdr.101026-1503" LFVersion="9.0.8080.16413" ExtendedDeviceInfo="" /><Authorization /></RequestHeader><BeaconFingerprint><Detections><Wifi7 BssId="'.$bssid.'" rssi="-1" /></Detections></BeaconFingerprint></GetLocationUsingFingerprint>';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://inference.location.live.net/inferenceservice/v21/Pox/GetLocationUsingFingerprint');
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$data = ( function_exists('gzdecode') ? gzdecode(curl_exec($ch)) : my_gzdecode(curl_exec($ch)) );
		curl_close($ch);

		$tries--;
		sleep(2);
	}

	$result = '';
	if (!$data) return $result;
	$xml = simplexml_load_string($data);
	if (!$xml) return $result;
	if ($xml->GetLocationUsingFingerprintResult->ResponseStatus == 'Success' &&
		$xml->GetLocationUsingFingerprintResult->LocationResult->ResolverStatus->attributes()->Status == 'Success' &&
		$xml->GetLocationUsingFingerprintResult->LocationResult->ResolverStatus->attributes()->Source == 'Internal' &&
		$xml->GetLocationUsingFingerprintResult->LocationResult->RadialUncertainty < 500
	)
	{
		$geo = $xml->GetLocationUsingFingerprintResult->LocationResult->ResolvedPosition->attributes();
		$result = $geo->Latitude.';'.$geo->Longitude.';microsoft';
		if ($geo->Latitude == 12.3456 && $geo->Longitude == -7.891)
		{
			$result = '';
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
	if (!$data) return $result;
	$json = json_decode($data);
	if (!$json) return $result;
	if ($json->result == 200)
	{
		$latitude = $json->data->lat;
		$longitude = $json->data->lon;
		$result = $latitude.';'.$longitude.';mylnikov';
	}
	return $result;
}
function cURL_Get($url, $proxy = '', $proxytype = -1, $proxyauth = '')
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
		curl_setopt($ch, CURLOPT_PROXYTYPE, $proxytype);
		curl_setopt($ch, CURLOPT_PROXY, $proxy);
		curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyauth);
	}
	curl_setopt($ch, CURLOPT_URL, $url);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}
?>