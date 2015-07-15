<?php
	require 'con_db.php'; /* Коннектор MySQL */
	$bbox =  explode(",", $_GET['bbox']);
	$callback = $_GET['callback'];

	$lat1 = $bbox[0];
	$lon1 = $bbox[1];
	$lat2 = $bbox[2];
	$lon2 = $bbox[3];
	
	$query="SELECT SQL_NO_CACHE * FROM `free` WHERE `latitude` BETWEEN $lat1 AND $lat2 AND `longitude` BETWEEN $lon1 AND $lon2 LIMIT 1000";

	if ($res = $db->query($query)) {

		$id=0;
		echo "$callback(";
		while ($row = $res->fetch_row()) {
			if ($id==0){echo '{"type": "FeatureCollection","features": [';}
			else{echo ',';};
			$xtime=$row[0];
			$xcomment=$row[1];
			$xbssid=$row[8];
			$xessid=$row[9];
			$xsecurity=$row[10];
			$xwifikey=$row[11];
			$xwpspin=$row[12];
			$xlatitude=$row[19];
			$xlongitude=$row[20];
			printf('{"type": "Feature","id": %s,"geometry": {"type": "Point","coordinates": [%s, %s]},"properties": {"hintContent": "%s"}}',$id,$xlatitude,$xlongitude,"$xbssid<br>$xessid<br>$xwifikey");
			$id++;
		};
		
		if ($id>0){echo ']}';};
		echo ')';
		$res->close();
	};


?>