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
		Header("Content-Type: application/json-p");
		$json['error'] = null;
		$json['data']['type'] = 'FeatureCollection';
		$json['data']['features'] = array();
		$ap['type'] = 'Feature';
		while ($row = $res->fetch_row())
		{
			$xid = $row[0];
			$xtime = $row[1];
			$xcomment = htmlspecialchars($row[2]);
			$xbssid = htmlspecialchars($row[9]);
			$xessid = htmlspecialchars($row[10]);
			$xsecurity = htmlspecialchars($row[11]);
			$xwifikey = htmlspecialchars($row[12]);
			$xwpspin = htmlspecialchars($row[13]);
			$xlatitude = $row[20];
			$xlongitude = $row[21];

			$ap['id'] = $xid;
			$ap['geometry']['type'] = 'Point';
			$ap['geometry']['coordinates'][0] = (float)$xlatitude;
			$ap['geometry']['coordinates'][1] = (float)$xlongitude;
			$ap['properties']['hintContent'] = "$xtime<br>$xbssid<br>$xessid<br>$xwifikey";

			$json['data']['features'][] = $ap;
		}
		echo "typeof $callback === 'function' && $callback(".json_encode($json).");";
		$res->close();
	}
?>