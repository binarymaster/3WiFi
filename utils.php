<?php
// Debug
function Debug($Text)
{
	$LogFile = fopen(DEBUG_FILENAME, 'a');
	fwrite($LogFile, '['.date('H:i:s').'] ('.$_SERVER['REMOTE_ADDR'].'): '.$Text."\r\n");
	fclose($LogFile);
}

function var_dump_ret($mixed = null)
{
	ob_start();
	var_dump($mixed);
	$content = ob_get_contents();
	ob_end_clean();
	return $content;
}

// Strings
function StrInStr($str, $sub)
{
	return !(strpos($str, $sub) === false);
}
function getStringBetween($string, $start, $end)
{
	$string = ' '.$string;
	$ini = strpos($string, $start);
	if ($ini == 0) return '';
	$ini += strlen($start);
	$len = strpos($string, $end, $ini) - $ini;
	return substr($string, $ini, $len);
}
function randomStr($size=32, $syms = true)
{
	$Str = '';

	$arr = array('a','b','c','d','e','f',
			 'g','h','i','j','k','l',
			 'm','n','o','p','r','s',
			 't','u','v','x','y','z',
			 'A','B','C','D','E','F',
			 'G','H','I','J','K','L',
			 'M','N','O','P','R','S',
			 'T','U','V','X','Y','Z',
			 '1','2','3','4','5','6',
			 '7','8','9','0');
	$symb_arr = Array( '!', '@',
			 '#', '$', '%', '^', '&',
			 '*', '(', ')', '-', '_',
			 '+', '=', '|', '.', ',');

	if ($syms) $arr = array_merge($arr, $symb_arr);

	for($i = 0; $i < $size; $i++)
	{
	  $Str .= $arr[rand(0, sizeof($arr) - 1)];
	}
	return $Str;
}
function pin2str($pin)
{
	return ($pin == 1 ? '' : str_pad($pin, 8, '0', STR_PAD_LEFT));
}
function parseDelimStr($str)
{
	$delim = ', ';
	if (strpos($str, '; ') !== false)
		$delim = '; ';
	$ex = explode($delim, $str);
	$ret = array();
	foreach ($ex as $part)
	{
		$n = explode(': ', $part);
		foreach ($n as $s)
			$ret[] = $s;
	}
	return $ret;
}
function getDelimStr($name, $arr)
{
	for ($i = 0; $i < (count($arr) >> 1); $i++)
	{
		if ($arr[$i << 1] == $name)
		{
			return $arr[($i << 1) + 1];
		}
	}
	return false;
}

// IP block
function _long2ip($arg)
{
	$res = long2ip($arg);
	if($res === null) return '';
	return $res;
}
function _ip2long($arg)
{
	$res = ip2long($arg);
	if($res === false) return 'NULL';
	if ($res > 0x7FFFFFFF) // prevent MySQL INT field clipping
		$res -= 0x100000000;
	return $res;
}
function _l2ul($arg)
{
	if ( sprintf('%u', -1) == '18446744073709551615' )
	{
		$res = sprintf('%u', $arg & 0xFFFFFFFF);
	}
	else
	{
		$res = sprintf('%u', $arg);
	}
	return $res;
}
function _ip2ulong($arg)
{
	$ip = ip2long($arg);
	if($ip === false)
		return false;
	return _l2ul($ip);
}
function isValidIP($addr)
{
	$ip_arr = explode('.', $addr);
	return (count($ip_arr) == 4 && $ip_arr[0] > 0 && $ip_arr[0] < 255);
}
function isLocalIP($addr)
{
	$ip_arr = explode('.', $addr);
	return ($ip_arr[0] == 10 ||
			($ip_arr[0] == 100 && $ip_arr[1] >= 64 && $ip_arr[1] < 128) ||
			($ip_arr[0] == 172 && $ip_arr[1] >= 16 && $ip_arr[1] < 32) ||
			($ip_arr[0] == 192 && $ip_arr[1] == 168));
}

// MAC block
function dec2hex($number)
{
	$hexvalues = array('0','1','2','3','4','5','6','7',
						'8','9','A','B','C','D','E','F');
	$hexval = '';
	 while($number != '0')
	 {
		$hexval = $hexvalues[bcmod($number,'16')].$hexval;
		$number = bcdiv($number,'16',0);
	}
	return $hexval;
}
function hex2dec($number)
{
	$decvalues = array('0' => '0', '1' => '1', '2' => '2',
						'3' => '3', '4' => '4', '5' => '5',
						'6' => '6', '7' => '7', '8' => '8',
						'9' => '9', 'A' => '10', 'B' => '11',
						'C' => '12', 'D' => '13', 'E' => '14',
						'F' => '15');
	$decval = '0';
	$number = strrev($number);
	for($i = 0; $i < strlen($number); $i++)
	{
		$decval = bcadd(bcmul(bcpow('16',$i,0),$decvalues[$number{$i}]), $decval);
	}
	return $decval;
}
function dec2mac($mac)
{
	$mac = dec2hex($mac);
	$mac = str_pad($mac, 12, '0', STR_PAD_LEFT);
	$mac = substr_replace($mac, ':', 10, 0);
	$mac = substr_replace($mac, ':', 8, 0);
	$mac = substr_replace($mac, ':', 6, 0);
	$mac = substr_replace($mac, ':', 4, 0);
	$mac = substr_replace($mac, ':', 2, 0);
	return $mac;
}
function mac2dec($mac)
{
	$mac = str_replace(':', '', $mac);
	$mac = str_replace('-', '', $mac);
	$mac = str_replace('.', '', $mac);
	return hex2dec(strtoupper($mac));
}
function ismac($mac)
{
	$mac = str_replace(':', '', $mac);
	$mac = str_replace('-', '', $mac);
	$mac = str_replace('.', '', $mac);
	if (strlen($mac) != 12) return false;
	return ctype_xdigit($mac);
}
function mac_mask($macfield, $unmask = true)
{
	$macfield = str_replace(':', '', $macfield);
	$macfield = str_replace('-', '', $macfield);
	$macfield = str_replace('.', '', $macfield);
	$wc = array();
	$i = 0;
	while ($i < strlen($macfield))
	{
		if ($macfield[$i] == '*')
		{
			$wc[] = $i;
			$macfield = substr_replace($macfield, '', $i, 1);
		} else {
			if (!$unmask) $macfield[$i] = 'F';
			$i++;
		}
	}
	if (count($wc) == 0) return $macfield;
	$needfill = 12 - strlen($macfield);
	while ($needfill)
	{
		for ($i = 0; $i < count($wc); $i++)
		{
			$macfield = substr_replace($macfield, '0', $wc[$i], 0);
			for ($j = $i + 1; $j < count($wc); $j++)
				$wc[$j]++;
			$needfill--;
			if (!$needfill) break;
		}
	}
	return $macfield;
}

// Security block
define('SEC_AUTH_OPEN', 0x00);
define('SEC_AUTH_PSK', 0x01);
define('SEC_AUTH_EAP', 0x02);
define('SEC_AUTH_WAPI', 0x03);

define('SEC_WEP_NO', 0x00);
define('SEC_WEP_YES', 0x01);

define('SEC_SHARED_NO', 0x00);
define('SEC_SHARED_YES', 0x01);

define('SEC_8021X_NO', 0x00);
define('SEC_8021X_YES', 0x01);

define('SEC_WPA_NO', 0x00);
define('SEC_WPA_1', 0x01);
define('SEC_WPA_2', 0x02);
define('SEC_WPA_BOTH', 0x03);

define('SEC_DEFINED_NO', 0x00);
define('SEC_DEFINED_YES', 0x01);


function SECURITY_PACK($sec_auth, $sec_wep, $sec_shared, $sec_8021x, $sec_wpa, $sec_def)
{
	$sec = 0;
	$sec |= min(max($sec_auth, 0), 3);
	$sec |= (min(max($sec_wep, 0), 1) << 2);
	$sec |= (min(max($sec_shared, 0), 1) << 3);
	$sec |= (min(max($sec_8021x, 0), 1) << 4);
	$sec |= (min(max($sec_wpa, 0), 3) << 5);
	$sec |= (min(max($sec_def, 0), 1) << 7);
	return $sec;
}

function SECURITY_UNPACK($sec)
{
	$sec_auth = $sec & 3;
	$sec_wep = ($sec >> 2) & 1;
	$sec_shared = ($sec >> 3) & 1;
	$sec_8021x = ($sec >> 4) & 1;
	$sec_wpa = ($sec >> 5) & 3;
	$sec_def = ($sec >> 7) & 1;

	return array('Auth'=> $sec_auth, 'WEP'=> $sec_wep, 'Shared'=> $sec_shared, '8021X'=> $sec_8021x, 'WPA'=> $sec_wpa, 'Def'=> $sec_def);
}

function str2sec($str)
{
	$str = trim(strtoupper($str));

	// init vals
	$sec_auth = SEC_AUTH_OPEN;
	$sec_wep = SEC_WEP_NO;
	$sec_shared = SEC_SHARED_NO;
	$sec_8021x = SEC_8021X_NO;
	$sec_wpa = SEC_WPA_NO;
	$sec_def = SEC_DEFINED_NO;

	if($str == 'PSK') $sec_auth = SEC_AUTH_PSK;
	if($str == 'EAP') $sec_auth = SEC_AUTH_EAP;

	if($str == 'NONE')
	{
		$sec_auth = SEC_AUTH_OPEN;
		$sec_def = SEC_DEFINED_YES;
	}

	if(preg_match('/.*802\.1X.*/', $str))
	{
		$sec_8021x = SEC_8021X_YES;
		$sec_def = SEC_DEFINED_YES;
	}
	if(preg_match('/.*WEP.*/', $str))
	{
		$sec_wep = SEC_WEP_YES;
		$sec_def = SEC_DEFINED_YES;
	}
	if(preg_match('/.*SHARED.*/', $str))
	{
		$sec_wep = SEC_WEP_YES;
		$sec_shared = SEC_SHARED_YES;
		$sec_def = SEC_DEFINED_YES;
	}
	if(preg_match('/.*WPA.*/', $str))
	{
		$sec_auth = SEC_AUTH_PSK;
		$sec_def = SEC_DEFINED_YES;
	}
	if(preg_match('/.*ENTERPRISE.*/', $str))
	{
		$sec_auth = SEC_AUTH_EAP;
		$sec_def = SEC_DEFINED_YES;
	}

	if(preg_match('/WPA( |$)/', $str)) $sec_wpa = SEC_WPA_1;
	if(preg_match('/WPA2( |$)/', $str)) $sec_wpa = SEC_WPA_2;
	if(preg_match('/WPA\/WPA2( |$)/', $str)) $sec_wpa = SEC_WPA_BOTH;

	if(preg_match('/.*WAPI.*/', $str))
	{
		$sec_auth = SEC_AUTH_WAPI;
		$sec_def = SEC_DEFINED_YES;
	}
	if($str == 'WAPI') $sec_wpa = SEC_WPA_1;
	if($str == 'WAPI-PSK') $sec_wpa = SEC_WPA_2;
	if($str == 'WAPI/WAPI-PSK') $sec_wpa = SEC_WPA_BOTH;

	return SECURITY_PACK($sec_auth, $sec_wep, $sec_shared, $sec_8021x, $sec_wpa, $sec_def);
}

function sec2str($sec)
{
	$sec = SECURITY_UNPACK((int)$sec);
	$str = '';

	if ($sec['Def'] == SEC_DEFINED_NO)
	{
		switch($sec['Auth'])
		{
			case SEC_AUTH_OPEN:
				$str = 'Unknown';
				break;
			case SEC_AUTH_PSK:
				$str = 'PSK';
				break;
			case SEC_AUTH_EAP:
				$str = 'EAP';
				break;
			case SEC_AUTH_WAPI:
				$str = 'WAPI';
				break;
		}
	} else {
		switch($sec['Auth'])
		{
			case SEC_AUTH_OPEN:
				if ($sec['WEP'] == SEC_WEP_NO && $sec['8021X'] == SEC_8021X_NO)
					$str = 'None';
				if ($sec['WEP'] == SEC_WEP_YES && $sec['8021X'] == SEC_8021X_NO)
					$str = 'WEP';
				if ($sec['WEP'] == SEC_WEP_NO && $sec['8021X'] == SEC_8021X_YES)
					$str = '802.1X';
				if ($sec['WEP'] == SEC_WEP_YES && $sec['8021X'] == SEC_8021X_YES)
					$str = '802.1X/WEP';
				if ($sec['Shared'] == SEC_SHARED_YES)
					$str .= ' Shared';
				break;
			case SEC_AUTH_PSK:
			case SEC_AUTH_EAP:
				if ($sec['WPA'] == SEC_WPA_1) $str = 'WPA';
				if ($sec['WPA'] == SEC_WPA_2) $str = 'WPA2';
				if ($sec['WPA'] == SEC_WPA_BOTH) $str = 'WPA/WPA2';
				if ($sec['Auth'] == SEC_AUTH_EAP) $str .= ' Enterprise';
				break;
			case SEC_AUTH_WAPI:
				$str = 'WAPI';
				if ($sec['WPA'] == SEC_WPA_2) $str .= '-PSK';
				if ($sec['WPA'] == SEC_WPA_BOTH) $str .= '/WAPI-PSK';
				break;
		}
	}
	return $str;
}

function logt($str)
{
	global $silent;
	if ($silent) return;
	echo '['.date('H:i:s').'] '.$str."\n";
}

function ValidHeaderCSV($row)
{
	if (($row[0] !== 'IP Address')
	|| ($row[1] !== 'Port')
	|| ($row[4] !== 'Authorization')
	|| ($row[5] !== 'Server name / Realm name / Device type')
	|| ($row[6] !== 'Radio Off')
	|| ($row[7] !== 'Hidden')
	|| ($row[8] !== 'BSSID')
	|| ($row[9] !== 'ESSID')
	|| ($row[10] !== 'Security')
	|| ($row[11] !== 'Key')
	|| ($row[12] !== 'WPS PIN')
	|| ($row[13] !== 'LAN IP Address')
	|| ($row[14] !== 'LAN Subnet Mask')
	|| ($row[15] !== 'WAN IP Address')
	|| ($row[16] !== 'WAN Subnet Mask')
	|| ($row[17] !== 'WAN Gateway')
	|| ($row[18] !== 'Domain Name Servers'))
	{
		return false;
	}
	return true;
}

function ValidHeaderTXT($row)
{
	$row = explode("\t", $row);
	return (count($row) == 23);
}

function filterLogin(&$login)
{
	$login = preg_replace('~[\\\\/|!?#$%\^&*()=:;"<>\[\]\{\},\~\+\'`]+~', '', $login);
	$login = trim(preg_replace('/\s+/', ' ', $login));
}

function filterNick(&$nick)
{
	$nick = preg_replace('~[\\\\/|!?#$%\^&*()=:;"<>\[\]\{\},\~\+]+~', '', $nick);
	$nick = trim(preg_replace('/\s+/', ' ', $nick));
}

function loadStatsCache($name)
{
	if (!CACHE_STATS) return false;
	$res = file_get_contents("uploads/cache_$name.txt");
	if (!$res || strlen($res) == 0) return false;
	return unserialize($res);
}

function useLocationAllowed($query)
{
	global $UserManager, $db;

	$uselocation = array();
	parse_str($query, $uselocation);
	if ($UserManager->Level < 1)
		return false;
	if (!isset($uselocation['lat']) || empty($uselocation['lat']))
		return false;
	if (!isset($uselocation['lon']) || empty($uselocation['lon']))
		return false;
	if (!isset($uselocation['rad']) || empty($uselocation['rad']))
		return false;
	$lat = (float)$uselocation['lat'];
	$lon = (float)$uselocation['lon'];
	$rad = (float)$uselocation['rad'];
	if ($lat < -90 || $lat > 90) return false;
	if ($lon < -180 || $lon > 180) return false;
	if ($rad < 0 || $rad > 25) return false;

	require_once 'quadkey.php';
	return query_radius_ids($db, $lat, $lon, $rad);
}

// Web interface

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

function loadLanguage()
{
	$langs = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
	if (empty($langs)) $langs = 'en';

	$lang = detectBestLang($langs);
	return "l10n/$lang.php";
}
?>