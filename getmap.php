<?php
	require 'con_db.php'; /* Коннектор MySQL */
	$bbox =  explode(",", $_GET['bbox']);
	$callback = $_GET['callback'];

	$lat1 = (float)$bbox[0];
	$lon1 = (float)$bbox[1];
	$lat2 = (float)$bbox[2];
	$lon2 = (float)$bbox[3];
	
	$query="SELECT SQL_NO_CACHE * FROM `free` WHERE `latitude` BETWEEN $lat1 AND $lat2 AND `longitude` BETWEEN $lon1 AND $lon2 LIMIT 1000";

	if ($res = $db->query($query))
	{
		Header("Content-Type: application/json-p");
		$id=0;
		$json['type'] = 'FeatureCollection';
		$json['features'] = array();
		$ap['type'] = 'Feature';
		while ($row = $res->fetch_row())
		{
			$xtime=$row[0];
			$xcomment=$row[1];
			$xbssid=$row[8];
			$xessid=$row[9];
			$xsecurity=$row[10];
			$xwifikey=$row[11];
			$xwpspin=$row[12];
			$xlatitude=$row[19];
			$xlongitude=$row[20];

			$ap['id'] = $id;
			$ap['geometry']['type'] = 'Point';
			$ap['geometry']['coordinates'][0] = (float)$xlatitude;
			$ap['geometry']['coordinates'][1] = (float)$xlongitude;
			$ap['properties']['hintContent'] = "$xbssid<br>$xessid<br>$xwifikey";

			$json['features'][] = $ap;
			$id++;
		}
		echo $callback.'('.json_encode($json).')';
		$res->close();
	}


?>