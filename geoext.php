<?php
require_once 'utils.php';

function geoDbg($str)
{
	return; // debug output disabled
	$f = fopen('geodbg.log', 'ab');
	fwrite($f, '['.date('H:i:s')."] $str\r\n");
	fclose($f);
}
function geoDebug($prov, $bssid, $data)
{
	geoDbg("[+] $prov / $bssid\r\n$data");
}
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
function handleGeoErrors($prov, $bssid, $lat, $lon)
{
	if ($lat == 0 && $lon == 0)
	{
		geoDbg("[-] $prov / $bssid : Zero spot rejected\r\n");
		return false;
	}
	if ($lat == 12.3456 && $lon == -7.891)
	{
		geoDbg("[-] $prov / $bssid : 12.3456 rejected\r\n");
		return false;
	}
	if (($lat >= 56.864 && $lat <= 56.865) && ($lon >= 60.610 && $lon <= 60.612))
	{
		geoDbg("[-] $prov / $bssid : Ekaterinburg fake spot rejected\r\n");
		return false;
	}
	return true;
}
function GetGeolocationServices()
{
	return array(
		'Yandex',
		//'YandexLocator',
		'Microsoft',
		//'AlterGeo',
		//'Mylnikov',
		'MylnikovOpen',
		//'Multigeo',
	);
}
function GeoLocateAP($bssid, $svcs = null)
{
	geoDbg("start locate $bssid");
	$coords = '';
	if (!is_array($svcs))
		$svcs = GetGeolocationServices();
	foreach ($svcs as $svc)
	{
		$func = 'GetFrom' . $svc;
		$coords = $func($bssid);
		if ($coords != '')
			break;
	}
	return $coords;
}
function GetFromYandex($bssid)
{
	geoDbg("yandex: $bssid");
	$tries = 5;
	$bssid = str_replace(":","",$bssid);
	$bssid = str_replace("-","",$bssid);
	while (!($data = cURL_Get("http://mobile.maps.yandex.net/cellid_location/?clid=1866854&lac=-1&cellid=-1&operatorid=null&countrycode=null&signalstrength=-1&wifinetworks=$bssid:0&app")) && ($tries > 0))
	{
		$tries--;
		sleep(3);
	}

	$result = '';
	if (!$data) return $result;
	geoDebug('yandex', $bssid, $data);
	$latitude = getStringBetween($data, ' latitude="', '"');
	$longitude = getStringBetween($data, ' longitude="', '"');
	if ($latitude != '' && $longitude != '')
	{
		if (handleGeoErrors('yandex', $bssid, (float)$latitude, (float)$longitude))
		{
			$result = $latitude.';'.$longitude.';yandex';
		}
	}
	return $result;
}
function GetFromYandexLocator($bssid)
{
	geoDbg("yandex_locator: $bssid");
	$url = 'http://api.lbs.yandex.net/geolocation';
	$apiKey = YLOCATOR_APIKEY;
	if (empty($apiKey))
	{
		return '';
	}
	$xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>
		<ya_lbs_request xmlns="http://api.lbs.yandex.net/geolocation">
			<common>
				<version>1.0</version>
				<api_key>'.$apiKey.'</api_key>
			</common>
			<wifi_networks>
				<network>
					<mac>'.$bssid.'</mac>
				</network>
			</wifi_networks>
		</ya_lbs_request>';
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, "xml=" . urlencode($xmlRequest));
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$data = curl_exec($curl);

	if (curl_errno($curl))
	{
		return '';
	}
	curl_close($curl);

	$result = '';
	geoDebug('yandex_locator', $bssid, $data);
	$xml = simplexml_load_string($data);

	if ($xml->position->type == 'wifi')
	{
		$latitude = $xml->position->latitude;
		$longitude = $xml->position->longitude;
		if (handleGeoErrors('yandex_locator', $bssid, $latitude, $longitude))
		{
			$result = $latitude.';'.$longitude.';yandex_locator';
		}
	}
	return $result;
}
function GetFromAlterGeo($bssid)
{
	geoDbg("altergeo: $bssid");
	$tries = 3;
	$bssid = strtolower(str_replace(":","-",$bssid));
	while (!($data = cURL_Get("http://api.platform.altergeo.ru/loc/json?browser=firefox&sensor=false&wifi=mac:$bssid%7Css:0")) && ($tries > 0))
	{
		$tries--;
		sleep(5);
	}

	$result = '';
	if (!$data) return $result;
	geoDebug('altergeo', $bssid, $data);
	$json = json_decode($data);
	if (!$json) return $result;
	if ($json->status == 'OK')
	{
		if ($json->accuracy < 1000 && handleGeoErrors('altergeo', $bssid, $json->location->lat, $json->location->lng))
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
	geoDbg("microsoft: $bssid");
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
	geoDebug('microsoft', $bssid, $data);
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
		if (!handleGeoErrors('microsoft', $bssid, $geo->Latitude, $geo->Longitude))
		{
			$result = '';
		}
	}
	return $result;
}
function GetFromMylnikov($bssid)
{
	geoDbg("mylnikov: $bssid");
	$tries = 5;
	while (!($data = cURL_Get("https://api.mylnikov.org/wifi/main.py/get?bssid=$bssid", ''/* 127.0.0.1:3128 */)) && ($tries > 0))
	{
		$tries--;
		sleep(5);
	}

	geoDebug('mylnikov', $bssid, $data);
	$result = '';
	if (!$data) return $result;
	$json = json_decode($data);
	if (!$json) return $result;
	if ($json->result == 200 && handleGeoErrors('mylnikov', $bssid, $json->data->lat, $json->data->lon))
	{
		$latitude = $json->data->lat;
		$longitude = $json->data->lon;
		$result = $latitude.';'.$longitude.';mylnikov';
	}
	return $result;
}
function GetFromMylnikovOpen($bssid)
{
	geoDbg("mylnikov_open: $bssid");
	$tries = 3;
	while (!($data = cURL_Get("http://api.mylnikov.org/geolocation/wifi?v=1.1&data=open&bssid=$bssid")) && ($tries > 0))
	{
		$tries--;
		sleep(2);
	}

	$result = '';
	if (!$data) return $result;
	$json = json_decode($data);
	if (!$json) return $result;
	geoDebug('mylnikov_open', $bssid, $data);
	if ($json->result == 200 && handleGeoErrors('mylnikov_open', $bssid, $json->data->lat, $json->data->lon))
	{
		$latitude = $json->data->lat;
		$longitude = $json->data->lon;
		$result = $latitude.';'.$longitude.';mylnikov_open';
	}
	return $result;
}
function GetFromMultigeo($bssid)
{
	geoDbg("multigeo: $bssid");
	$tries = 3;
	while (!($data = cURL_Get("http://geomac.local/locate.php?mac=$bssid")) && ($tries > 0))
	{
		$tries--;
		sleep(2);
	}

	$result = '';
	if (strpos($data, 'Results for ') === false) return $result;
	geoDebug('multigeo', $bssid, $data);
	$svc = 'apple';
	$line = getStringBetween($data, 'Apple           | ', "\n");
	if (empty($line))
	{
		$svc = 'google';
		$line = getStringBetween($data, 'Google          | ', "\n");
	}
	if (empty($line))
		return $result;
	$line = explode(', ', $line);
	$latitude = $line[0];
	$longitude = $line[1];
	if (handleGeoErrors('multigeo', $bssid, (float)$latitude, (float)$longitude))
	{
		$result = $latitude.';'.$longitude.';'.$svc;
	}
	return $result;
}
function cURL_Get($url, $proxy = '', $proxytype = -1, $proxyauth = '')
{
	geoDbg("Fetching: $url");
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_ENCODING, 'utf-8');
	curl_setopt($ch, CURLOPT_TIMEOUT, 3);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_FAILONERROR, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, 'PHP/'.phpversion().' 3WiFi/2.0');
	if ($proxy != '')
	{
		curl_setopt($ch, CURLOPT_PROXYTYPE, $proxytype);
		curl_setopt($ch, CURLOPT_PROXY, $proxy);
		curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyauth);
	}
	curl_setopt($ch, CURLOPT_URL, $url);
	$data = curl_exec($ch);
	if (strpos($url, 'mobile.maps.yandex.net') !== false
		&& curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404)
	{
		// workaround for Yandex
		$data = '<error code="6">Not found</error>';
	}
	if ($data)
		geoDbg("Success: $url");
	else
		geoDbg("Error: $url\r\n" . curl_getinfo($ch, CURLINFO_HTTP_CODE).' / '.curl_errno($ch).' ('.curl_error($ch).')');
	curl_close($ch);
	return $data;
}
