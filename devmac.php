<?php
require_once 'utils.php';

/**
 * Получает список соседних устройств по MAC-адресу
 * @param string $bssid MAC-адрес устройства
 * @param bool $nocli Не учитывать Wi-Fi устройства в режиме клиента
 * @return array $json
 */
function API_device_mac($bssid, $nocli)
{
	$result = array();
	$res = QuerySql(
		"CREATE TEMPORARY TABLE tmp_dev_names 
		(
			`name` TINYTEXT NOT NULL, 
			`BSSID` BIGINT(15) UNSIGNED NOT NULL, 
			INDEX name (name(512)), 
			INDEX BSSID (BSSID), 
			UNIQUE INDEX uni (BSSID)
		)
	");
	$result['result'] = $res !== false;
	if (!$result['result'])
	{
		$result['error'] = 'database';
		return $result;
	}
	$mac = mac2dec($bssid);
	$x = 1;
	for ($i = 1; $i <= 24; $i++)
	{
		$m2 = $mac | $x;
		$m1 = $m2 - $x;
		$m2 = base_convert($m2, 10, 16);
		$m1 = base_convert($m1, 10, 16);
		if ($result['result'])
		{
			$sql = "INSERT IGNORE tmp_dev_names 
					SELECT name, BSSID 
					FROM `BASE_TABLE` 
					WHERE 
						BSSID BETWEEN 0x{$m1} AND 0x{$m2} 
						AND NoBSSID = 0 
						AND name != '' 
			";
			if ($nocli)
				$sql .= "AND name NOT LIKE 'FOSCAM%' 
						 AND name NOT LIKE 'IPCAM%' 
						 AND name NOT LIKE '%D-Link DCS%' 
						 AND name NOT LIKE '%IP Camera%' 
						 AND name NOT LIKE '%Network Camera%' 
				";
			$res = QuerySql($sql);
			$result['result'] = $res !== false;
		}
		if ($result['result'])
		{
			$res = QuerySql("SELECT COUNT(*) FROM tmp_dev_names");
			$result['result'] = $res !== false;
			if ($result['result'])
			{
				$row = $res->fetch_row();
				$res->close();
				if ((int)$row[0] >= 20000)
					break;
			}
		}
		if (!$result['result'])
			break;
		$x |= $x << 1;
	}
	if ($result['result'])
	{
		$res = QuerySql("CREATE TEMPORARY TABLE tmp_device_names LIKE tmp_dev_names");
		$result['result'] = $res !== false;
	}
	$mac = base_convert($mac, 10, 16);
	if ($result['result'])
	{
		$res = QuerySql(
			"INSERT tmp_device_names 
			SELECT name, BSSID 
			FROM tmp_dev_names 
			ORDER BY ABS(BSSID - 0x$mac)
		");
		$result['result'] = $res !== false;
	}
	if ($result['result'])
	{
		$res = QuerySql(
			"SELECT name, COUNT(name) cnt, ABS(BSSID - 0x$mac) diff 
			FROM tmp_device_names 
			GROUP BY name HAVING(cnt > 1) 
			ORDER BY ABS(BSSID - 0x$mac) 
			LIMIT 10
		");
		$result['result'] = $res !== false;
	}
	if ($result['result'])
	{
		$devs = array();
		while ($row = $res->fetch_assoc())
		{
			$devs[] = $row;
		}
		$res->close();
	}
	if (!$result['result'])
	{
		$result['error'] = 'database';
	}
	else
	{
		$result['scores'] = array();
		foreach($devs as $dev)
		{
			$entry = array();
			$entry['name'] = $dev['name'];
			$entry['score'] = 1 - pow((int)$dev['diff'] / 0xFFFFFF, 1 / 8);
			$entry['count'] = (int)$dev['cnt'];
			$result['scores'][] = $entry;
		}
	}
	QuerySql("DROP TABLE IF EXISTS tmp_dev_names, tmp_device_names");
	return $result;
}
