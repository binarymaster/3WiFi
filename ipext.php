<?php

// Regional Internet Registries
define('RIR_RIPE', 1);
define('RIR_APNIC', 2);
define('RIR_ARIN', 3);
define('RIR_AFRINIC', 4);
define('RIR_LACNIC', 5);

/**
 * Fetch data for ip object using RDAP.
 *
 * @param string $url Path to RDAP service.
 * @param string $ip IP address to search for.
 *
 * @return object|null Response encoded in json as PHP object.
 */
function fetch_rdap($url, $ip)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_ENCODING, 'utf-8');
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_USERAGENT, 'PHP/'.phpversion());
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	
	curl_setopt($ch, CURLOPT_URL, $url.'ip/'.$ip);
	curl_setopt($ch, CURLOPT_HTTPHEADER, 
		array("Accept: application/json, application/rdap+json"));
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	
	$res = curl_exec($ch);
	//$ret_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	//if ($ret_code != 200) {
	//	return;
	//}
	return json_decode($res);
}

/**
 * Fetch information from whois server.
 *
 * @param string $server Server address.
 * @param string $request Requset string.
 *
 * @return string|null Response of whois server.
 */
function fetch_whois($server, $request)
{
	$fp = fsockopen($server, 43, $errno, $errstr, 30);
	if (!$fp)
	{
		return;
	}
	else
	{
		$response = "";
		fputs($fp, "$request\r\n");
		while (!feof($fp))
		{
			$response .= fread($fp,128);
		}
		fclose ($fp);
	}
	return $response;
}

/**
 * Determine RIR for given IP.
 *
 * @param string $ip IP address.
 *
 * @return int|null.
 */
function get_rir($ip)
{
	// use ARIN server in hope that it will redirect to right one (if neccessary)
	$rdap = fetch_rdap('http://rdap.arin.net/registry/', $ip);
	if (is_null($rdap) || empty($rdap->port43))
	{
		return;
	}
	
	$whois = $rdap->port43;
	if (false !== stristr($whois, 'afrinic'))
	{
		return RIR_AFRINIC;
	}
	elseif (false !== stristr($whois, 'apnic'))
	{
		return RIR_APNIC;
	}
	elseif (false !== stristr($whois, 'arin'))
	{
		return RIR_ARIN;
	}
	elseif (false !== stristr($whois, 'lacnic'))
	{
		return RIR_LACNIC;
	}
	elseif (false !== stristr($whois, 'ripe'))
	{
		return RIR_RIPE;
	}
	return;
}

/**
 * Extract value of field with given name from the response of whois server.
 *
 * @param string $whois_text Whois server response.
 * @param string $field_name Field name to search for.
 *
 * @return string Value of the first field with given name.
 */
function get_whois_field($whois_text, $field_name)
{
	$field_pos = strpos($whois_text, "\n".$field_name.":");
	if ($field_pos === False)
	{
		return '';
	}
	$field_pos += strlen($field_name) + 2;
	$field_len = strpos($whois_text, "\n", $field_pos) - $field_pos;
	return trim(substr($whois_text, $field_pos, $field_len));
}

/**
 * Extract values of subsequent fields with given name from the response of whois server.
 *
 * @param string $whois_text Whois server response.
 * @param string $field_name Field name to search for.
 *
 * @return array Array of strings.
 */
function get_whois_field_arr($whois_text, $field_name)
{
	$field_pos = strpos($whois_text, "\n".$field_name.":");
	if ($field_pos === False)
	{
		return array('');
	}
	do
	{
		$value_pos = $field_pos + strlen($field_name) + 2;
		$value_len = strpos($whois_text, "\n", $value_pos) - $value_pos;
		$values[] = trim(substr($whois_text, $value_pos, $value_len));
		$field_pos = strpos($whois_text, "\n".$field_name.":", $value_pos);
	} while ($field_pos !== False and $field_pos == $value_pos + $value_len);
	return $values;
}

/**
 * Get boundaries of a range in CIDR notation.
 *
 * @param string $cidr IP range (x.x.x.x/z, x.x.x/z, ...).
 *
 * @return array Array ("startIP" => (int), "endIP" => (int)).
 */
function cidr_to_range($cidr)
{
	list($ip, $mask) = explode('/', $cidr);
	$i = substr_count($ip, '.');
	while ($i < 3)
	{
		$ip .= '.0';
		$i++;
	}
	$ip = ip2long($ip);
	return array('startIP' => $ip,
		// will not work on 32bit if $mask=0; but it doesn't matter here
		'endIP' => $ip | ((1<<(32-$mask)) - 1)); 
}

/**
 * Query RIR for a range to which belongs the given IP address.
 *
 * @param int $ip IP address as number.
 *
 * @return array|null Array ("startIP" => (int), "endIP" => (int), "netname" =>
 * (string), "descr" => (string), "country" => (string)) or null if nothing is 
 * found.
 */
function query_range_from_rir($ip)
{
	$rir = get_rir($ip);
	if (is_null($rir))
	{
		return;
	}
	
	$whois_servers = array(
		RIR_RIPE => 'whois.ripe.net',
		RIR_APNIC => 'whois.apnic.net',
		RIR_ARIN => 'whois.arin.net',
		RIR_AFRINIC => 'whois.afrinic.net',
		RIR_LACNIC => 'whois.lacnic.net');
	$whois_req = $ip;
	if ($rir == RIR_ARIN )
	{
		$whois_req = 'n + '.$ip;
	}
	$whois_res = fetch_whois($whois_servers[$rir], $whois_req);
	if ($whois_res == '')
	{
		return;
	}
	
	switch ($rir)
	{
		case RIR_RIPE:
			// the same as RIR_APNIC
		case RIR_AFRINIC:
			// the same as RIR_APNIC
		case RIR_APNIC:
			$inetnum = get_whois_field($whois_res, 'inetnum');
			if ($inetnum == '')
			{
				return;
			}
			$inetnum = preg_split('/\s*-\s*/', $inetnum);
			$netname = get_whois_field($whois_res, 'netname');
			$descr = implode(" | ", get_whois_field_arr($whois_res, 'descr'));
			if ($rir == RIR_RIPE)
			{
				$descr = iconv("ISO-8859-1", "UTF-8", $descr);
			}
			$country = strtoupper(get_whois_field($whois_res, 'country'));
			return array('startIP' => ip2long($inetnum[0]),
				'endIP' => ip2long($inetnum[1]),
				'netname' => $netname,
				'descr' => $descr,
				'country' => $country);
			break;
		case RIR_ARIN:
			// find the smallest range
			$start_pos = -1;
			$ip_count = array();
			while (($start_pos = strpos($whois_res, "\n# start", $start_pos + 1)) !== False)
			{
				$end_pos = strpos($whois_res, "\n# end", $start_pos);
				$inetnum = get_whois_field(substr($whois_res, $start_pos, $end_pos - $start_pos), 'NetRange');
				if ($inetnum != '')
				{
					$inetnum = preg_split('/\s*-\s*/', $inetnum);
					$ip_count[$start_pos] = ip2long($inetnum[1]) - ip2long($inetnum[0]);
				}
			}
			if (!empty($ip_count))
			{
				$start_pos = array_search(min($ip_count), $ip_count);
				$end_pos = strpos($whois_res, "\n# end", $start_pos);
				$whois_res = substr($whois_res, $start_pos, $end_pos - $start_pos);
			}
			
			$inetnum = get_whois_field($whois_res, 'NetRange');
			if ($inetnum == '')
			{
				return;
			}
			
			$inetnum = preg_split('/\s*-\s*/', $inetnum);
			$netname = get_whois_field($whois_res, 'NetName');
			$descr = get_whois_field($whois_res, 'OrgName');
			if ($descr == '')
			{
				$descr = get_whois_field($whois_res, 'CustName');
			}
			$country = strtoupper(get_whois_field($whois_res, 'Country'));
			return array('startIP' => ip2long($inetnum[0]),
				'endIP' => ip2long($inetnum[1]),
				'netname' => $netname,
				'descr' => $descr,
				'country' => $country);
			break;
		case RIR_LACNIC:
			$inetnum = get_whois_field($whois_res, 'inetnum');
			if ($inetnum == '')
			{
				return;
			}
			$inetnum = cidr_to_range($inetnum);
			$netname = get_whois_field($whois_res, 'netname');
			$descr = get_whois_field($whois_res, 'owner');
			$descr = iconv("ISO-8859-1", "UTF-8", $descr);
			$country = strtoupper(get_whois_field($whois_res, 'country'));
			return array('startIP' => $inetnum["startIP"],
				'endIP' => $inetnum["endIP"],
				'netname' => '',
				'descr' => $descr,
				'country' => $country);
			break;
	}
}

/**
 * Find a range to which belongs the given IP address.
 *
 * If IP doesn't belong to one of ranges stored in local database, it will try 
 * to fetch information from appropriate Regional Internet Registry (RIR).
 *
 * @param object $db Object which represents the connection to a MySQL Server.
 * @param int $ip IP address as number.
 *
 * @return array|null Array ("startIP" => (int), "endIP" => (int), "netname" =>
 * (string), "descr" => (string), "country" => (string)) or null if nothing is 
 * found.
 */
function get_ip_range($db, $ip)
{
	// If private IP
	if (($ip >= (int)0x0A000000 and $ip < (int)0x0B000000) or // 10.0.0.0-10.255.255.255
		($ip >= (int)0x64400000 and $ip < (int)0x64800000) or // 100.64.0.0-100.127.255.255
		($ip >= (int)0xAC100000 and $ip < (int)0xAC200000) or // 172.16.0.0-172.31.255.255
		($ip >= (int)0xC0A80000 and $ip < (int)0xC0A90000))   // 192.168.0.0-192.168.255.255
	{
		// exact range isn't known, just use /16 mask
		return array('startIP' => $ip & ~0xFFFF,
			'endIP' => $ip | 0xFFFF,
			'netname' => '',
			'descr' => 'Local IP range',
			'country' => '');
	}
	
	// If stored in local db
	$uIP = sprintf('%u', $ip);
	if ($res = $db->query(
			"SELECT * FROM ranges
			WHERE startIP <= $uIP AND endIP >= $uIP
			ORDER BY endIP-startIP
			LIMIT 1"))
	{
		if ($row = $res->fetch_row())
		{
			$res->close();
			// TODO: convert unsigned integer (represented by string) to int
			return array('startIP' => ip2long(long2ip($row[1])),
				'endIP' => ip2long(long2ip($row[2])),
				'netname' => $row[3],
				'descr' => $row[4],
				'country' => $row[5]);
		}
		$res->close();
	}
	
	// Query RIR
	$ip_range = query_range_from_rir(long2ip($ip));
	if(is_null($ip_range))
	{
		return;
	}
	$ip_range["netname"] = substr($ip_range["netname"], 0, 255);
	$ip_range["descr"] = substr($ip_range["descr"], 0, 255);
	$ip_range["descr"] = iconv("UTF-8", "UTF-8//IGNORE", $ip_range["descr"]);
	if ($ip_range["endIP"] - $ip_range["startIP"] >= 0x00FFFFFF)
	{
		return $ip_range; // don't store big ranges
	}
	$ip_range["country"] = substr($ip_range["country"], 0, 2);
	$startIP = sprintf('%u', $ip_range["startIP"]);
	$endIP = sprintf('%u', $ip_range["endIP"]);
	$netname = $db->real_escape_string($ip_range["netname"]);
	$descr = $db->real_escape_string($ip_range["descr"]);
	$country = $db->real_escape_string($ip_range["country"]);
	if (!$db->query(
			"INSERT into ranges
			VALUES (NULL, '$startIP','$endIP','$netname','$descr','$country')"))
	{
		return;
	}
	return $ip_range;
}

/**
 * Compare two IP addresses.
 * 
 * It's a workaround for 32-bit machines, where (since int type is signed) 
 * IPs bigger then '127.255.255.255' are negative numbers.
 * 
 * @param int $ip1 Numeric representation of the first IP.
 * @param int $ip2 Numeric representation of the second IP.
 * 
 * @return int Returns zero if addresses are equal, -1 if the first is smaller 
 * and 1 otherwise.
 */
function compare_ip($ip1, $ip2)
{
	if ($ip1 == $ip2)
	{
		return 0;
	}
	elseif ($ip1 < 0 and $ip2 >=0)
	{
		return 1;
	}
	elseif ($ip1 >= 0 and $ip2 < 0)
	{
		return -1;
	}
	elseif ($ip1 < $ip2)
	{
		return -1;
	}
	else
	{
		return 1;
	}
}

/**
 * Return string representation of IP range.
 * 
 * Convert IP range to CIDR specification (x.x.x.x/z) if it may be expressed as 
 * single entity. Otherwise format it to "x.x.x.x-y.y.y.y".
 *
 * @param int $ip1 Lower boundary of the range.
 * @param int $ip2 Upper boundary of the range.
 *
 * @return string
 */
function pretty_range($ip1, $ip2)
{
	$diff = decbin($ip1 ^ $ip2);
	if (strpos($diff, '0') === false and ($ip1 & $ip2) == $ip1)
	{
		return long2ip($ip1).'/'.(32 - strlen($diff));
	}
	return long2ip($ip1).'-'.long2ip($ip2);
}