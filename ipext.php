<?php

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

function QueryRangeFromRIPE($IP)
{
	$data = cURL_Get("http://rest.db.ripe.net/search.json?type-filter=inetnum&flags=one-less&flags=no-irt&flags=no-referenced&query-string=$IP");
	$json = json_decode($data);
	if (is_null($json))
	{
		return;
	}
	$atribute = $json->objects->object[0]->attributes->attribute;
	$inetnum = array_filter($atribute, function($obj){return $obj->name == 'inetnum';});
	$inetnum = $inetnum[0]->value;
	$inetnum = explode(" - ", $inetnum);
	$descr = implode(
		" | ",
		array_map(
			function($obj){return $obj->value;},
			array_filter($atribute, function($obj){return $obj->name == 'descr';})
		)
	);
	return array('startIP' => $inetnum[0],
		'endIP' => $inetnum[1],
		'descr' => $descr);
}

function GetIPRange($IP)
{
	// If invalid
	$ip_long = ip2long($IP);
	if ($ip_long == False || $ip_long == -1)
	{
		return;
	}

	// If private IP
	$ip_arr = explode('.', $IP);
	if ($ip_arr[0] == 10 ||
		($ip_arr[0] == 100 && $ip_arr[1] >= 64 && $ip_arr[1] < 128) ||
		($ip_arr[0] == 172 && $ip_arr[1] >= 16 && $ip_arr[1] < 32) ||
		($ip_arr[0] == 192 && $ip_arr[1] == 168))
	{
		$two_oct = $ip_arr[0].'.'.$ip_arr[1];
		return array('startIP' => $two_oct.'.0.0',
			'endIP' => $two_oct.'.255.255',
			'descr' => 'Local IP range');
	}

	// If stored in local db
	$ip_long = sprintf('%u', $ip_long);
	require 'con_db.php';
	if ($res = $db->query(
		"SELECT * FROM ranges
		WHERE startIP <= $ip_long AND endIP >= $ip_long
		ORDER BY endIP-startIP
		LIMIT 1"))
	{
		if ($row = $res->fetch_row())
		{
			return array('startIP' => long2ip($row[1]),
				'endIP' => long2ip($row[2]),
				'descr' => $row[3]);
		}
		$res->close();
	}

	// Query RIPE db
	$ip_range = QueryRangeFromRIPE($IP);
	if(is_null($ip_range))
	{
		return;
	}
	$startIP = ip2long($ip_range["startIP"]);
	$endIP = ip2long($ip_range["endIP"]);
	if ($startIP == False || $startIP == -1 || $endIP == False || $endIP == -1)
	{
		return;
	}
	$startIP = sprintf('%u', $startIP);
	$endIP = sprintf('%u', $endIP);
	$descr = $db->real_escape_string($ip_range["descr"]);
	if (!$db->query("INSERT into ranges VALUES (NULL, '$startIP','$endIP','$descr')"))
	{
		return;
	}
	return array('startIP' => $ip_range["startIP"],
		'endIP' => $ip_range["endIP"],
		'descr' => $ip_range["descr"]);
}

function compare_ip($IP1, $IP2)
{
	$ip_arr1 = explode('.', $IP1);
	$ip_arr2 = explode('.', $IP2);
	for($i = 0; $i < 4; $i++)
	{
		if ($ip_arr1[$i] < $ip_arr2[$i]) {return -1;}
		else if ($ip_arr1[$i] > $ip_arr2[$i]) {return 1;}
	}
	return 0;
}

function pretty_range($IP1, $IP2)
{
	$ip_long1 = ip2long($IP1);
	$ip_long2 = ip2long($IP2);
	$diff = decbin($ip_long1 ^ $ip_long2);
	if (strpos($diff, '0')===False && ($ip_long1 & $ip_long2) == $ip_long1)
	{
		return $IP1.'/'.(32-strlen($diff));
	}
	return $IP1.'-'.$IP2;
}