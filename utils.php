<?php
// Debug
function Debug($Text)
{
	$LogFile = fopen('Debug.log', 'a');
	fwrite($LogFile, '['.date('H:i:s').']: '.$Text."\r\n");
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
	return $res;
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
?>