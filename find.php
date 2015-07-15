<?php
$password="antichat";
echo '<html><head>
<title>3WiFi</title>

  <style type="text/css">
   TABLE {
    width: 800px; /* Ширина таблицы */
    background: #fffff0; /* Цвет фона нечетных строк */
    border: 1px solid #a52a2a; /* Рамка вокруг таблицы */
    border-collapse: collapse; /* Убираем двойные линии между ячейками */
   }
   TD, TH {
    padding: 3px; /* Поля вокруг содержимого ячейки */
   }
   TD {
    text-align: center; /* Выравнивание по центру */
    border-bottom: 1px solid #a52a2a; /* Линия внизу ячейки */
   }
   TH {
    background: #a52a2a; /* Цвет фона */
    color: white; /* Цвет текста */
   }
   TR.even {
    background: #fff8dc; /* Цвет фона четных строк */
   }
   .la {
    text-align: left; /* Выравнивание по левому краю */
   }
  </style>
  
</head><body>
';

	require 'con_db.php'; /* Коннектор MySQL */

	if (isset($_POST['pass'] )) {$pass  = $_POST['pass']; } else {$pass='';  };
	if (isset($_POST['bssid'])and($_POST['bssid']<>'')) {$bssid = $_POST['bssid'];} else {$bssid='%';};
	if (isset($_POST['essid'])and($_POST['essid']<>'')) {$essid = $_POST['essid'];} else {$essid='%';}; 
	
	require 'formfind.php';
	
	if ($pass==$password) {
		$query="SELECT SQL_NO_CACHE * FROM `free` WHERE `BSSID` LIKE '$bssid' AND `ESSID` LIKE '$essid'";
		if ($res = $db->query($query)) {
			echo "<table>";
			printf("<tr><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th></tr>\n","time", "comment", "BSSID", "ESSID", "Security","WiFi Key", "WPS PIN", "Latitude", "Longitude","map");
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
					$xmap='<a href="http://maps.yandex.ru/?text='.$xlatitude.'%20'.$xlongitude.'">map</a>';
				}else{
					$xmap='';
				};
				
				printf("<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n", $xtime, $xcomment, $xbssid, $xessid, $xsecurity, $xwifikey, $xwpspin, $xlatitude, $xlongitude, $xmap);
			};
			echo "</table>";
			$res->close();
		};

	} else {
		echo "AUTH FAILED";
		exit();
	};
?>