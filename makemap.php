<?php
	require 'con_db.php'; /* Коннектор MySQL */
	$pass='0282';
	
	

	// Записать строку текста
	

	// Закрыть текстовый файл
	
	
	
	
	if ($pass=="0282") {
		$query="SELECT * FROM `free` WHERE 1";
		$f = fopen("data.json", "w");
		fwrite($f,'{"type": "FeatureCollection","features": ['."\n");
		$i=0;
		if ($res = $db->query($query)) {
			while ($row = $res->fetch_row()) {
				$xtime=$row[0];
				$xcomment=$row[1];
				$xbssid=$row[8];
				$xessid=$row[9];
				$xsecurity=$row[10];
				$xwifikey=$row[11];
				$xwpspin=$row[12];
				$xlatitude=$row[19];
				$xlongitude=$row[20];
				if (($xlatitude!="none")and($xlatitude!="not found")and($xlongitude!="none")and($xlongitude!="not found")) {
					$i++;
					if ($i!=1) {fwrite($f,",\n");};
					fwrite($f, '{"type": "Feature", "id": '.$i.', "geometry": {"type": "Point", "coordinates": ['.$xlatitude.', '.$xlongitude.']}, "properties": {"balloonContent": "'.$xessid.'", "clusterCaption": "'.$xessid.'", "hintContent": "'.$xessid.'"}}'); 
				};
			};
			$res->close();
		};
		fwrite($f,"\n]}");
		fclose($f);
		echo 'Map rebuilded '.$i." total.<br>\n";
	} else {
		exit();
	};
	

?>