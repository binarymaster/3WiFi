<?php
	require 'con_db.php'; /* Коннектор MySQL */
	$bbox =  explode(",", $_GET['bbox']);
	$callback = $_GET['callback'];

	$lat1 = (float)$bbox[0];
	$lon1 = (float)$bbox[1];
	$lat2 = (float)$bbox[2];
	$lon2 = (float)$bbox[3];
	
	$query = "SELECT * FROM `free` WHERE `latitude` BETWEEN $lat1 AND $lat2 AND `longitude` BETWEEN $lon1 AND $lon2 LIMIT 1000";

	if ($res = $db->query($query))
	{
		$data = array();
		while ($row = $res->fetch_row())
		{
			$xlatitude = $row[20];
			$xlongitude = $row[21];
			if (!isset($data[$xlatitude][$xlongitude])) $data[$xlatitude][$xlongitude] = array();
			$i = count($data[$xlatitude][$xlongitude]);
			$data[$xlatitude][$xlongitude][$i]['id'] = (int)$row[0];
			$data[$xlatitude][$xlongitude][$i]['time'] = $row[1];
			$data[$xlatitude][$xlongitude][$i]['bssid'] = $row[9];
			$data[$xlatitude][$xlongitude][$i]['essid'] = $row[10];
			$data[$xlatitude][$xlongitude][$i]['key'] = $row[12];
		}
		$res->close();

		Header("Content-Type: application/json-p");
		$json['error'] = null;
		$json['data']['type'] = 'FeatureCollection';
		$json['data']['features'] = array();
		$ap['type'] = 'Feature';
		foreach($data as $xlatitude => $xlongitude)
		foreach($xlongitude as $xlongitude => $apdata)
		{
			$ap['id'] = $apdata[0]['id'];
			$ap['geometry']['type'] = 'Point';
			$ap['geometry']['coordinates'][0] = (float)$xlatitude;
			$ap['geometry']['coordinates'][1] = (float)$xlongitude;

			$hint = array();
			for ($i = 0; $i < count($apdata); $i++)
			{
				$aphint = array();

				$xtime = $apdata[$i]['time'];
				$xbssid = htmlspecialchars($apdata[$i]['bssid']);
				$xessid = htmlspecialchars($apdata[$i]['essid']);
				$xwifikey = htmlspecialchars($apdata[$i]['key']);

				$aphint[] = $xtime;
				$aphint[] = $xbssid;
				$aphint[] = $xessid;
				$aphint[] = $xwifikey;
				$hint[] = implode('<br>', $aphint);
			}
			$ap['properties']['hintContent'] = implode('<hr>', $hint);

			$json['data']['features'][] = $ap;
		}
		echo "typeof $callback === 'function' && $callback(".json_encode($json).");";
	}
?>