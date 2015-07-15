<?php
$topauth=100;
$topname=30;
$topbssid=30;

echo '<html><head>
<title>3WiFi</title>

  <style type="text/css">
   TABLE {
    background: #fffff0; /* Цвет фона нечетных строк */
    border: 1px solid #a52a2a; /* Рамка вокруг таблицы */
    border-collapse: collapse; /* Убираем двойные линии между ячейками */
   }
   TD, TH {
    padding: 3px; /* Поля вокруг содержимого ячейки */
   }
   TD {
    text-align: left; /* Выравнивание */
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
	
	/* Таблица комментарии */
	$query="SELECT COUNT(DISTINCT `comment`),COUNT(*) FROM `free`";
	if ($res = $db->query($query)) {
		$row = $res->fetch_row();
		$ycomment=$row[0];
		$ycount=$row[1];
		$res->close();
	};
	
	$query="SELECT `comment`, COUNT(*) FROM free GROUP BY `comment` ORDER BY COUNT(*) DESC";
	if ($res = $db->query($query)) {
		echo "<table>";
		printf("<tr><th>count(%s)</th><th>comment (%s)</th></tr>\n", $ycount, $ycomment);
		while ($row = $res->fetch_row()) {
			$xcomment=$row[0];
			$xcount=$row[1];
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xcount, $xcomment);
		};
		echo "</table>";
		$res->close();
	};

	
	/* Таблица устройства */
	
	$query="SELECT COUNT(DISTINCT `name`) FROM `free`";
	if ($res = $db->query($query)) {
		$row = $res->fetch_row();
		$yname=$row[0];
		$res->close();
	};
	
	$query="SELECT `name`, COUNT(*) FROM free GROUP BY `name` ORDER BY COUNT(*) DESC LIMIT $topname";
	if ($res = $db->query($query)) {
		echo "<table>";
		printf("<tr><th>count(%s)</th><th>name (top$topname)</th></tr>\n",$yname);
		while ($row = $res->fetch_row()) {
			$xname=$row[0];
			$xnamecount=$row[1];
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xnamecount, $xname);
		};
		echo "</table>";
		$res->close();
	};

	

	/* Таблица авторизация */
	
	$query="SELECT COUNT(DISTINCT `Authorization`) FROM `free`";
	if ($res = $db->query($query)) {
		$row = $res->fetch_row();
		$yauth=$row[0];
		$res->close();
	};
	
	$query="SELECT `Authorization`, COUNT(*) FROM free GROUP BY `Authorization` ORDER BY COUNT(*) DESC LIMIT $topauth";
	if ($res = $db->query($query)) {
		echo "<table>";
		printf("<tr><th>count(%s)</th><th>Authorization (top$topauth)</th></tr>\n",$yauth);
		while ($row = $res->fetch_row()) {
			$xauth=$row[0];
			$xauthcount=$row[1];
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xauthcount, $xauth);
		};
		echo "</table>";
		$res->close();
	};
	

	/* Таблица BSSID */
	
	$query="SELECT COUNT(DISTINCT `BSSID`) FROM `free`";
	if ($res = $db->query($query)) {
		$row = $res->fetch_row();
		$ybssid=$row[0];
		$res->close();
	};
	
	$query="SELECT `BSSID`, COUNT(*) FROM free GROUP BY `BSSID` ORDER BY COUNT(*) DESC LIMIT $topbssid";
	if ($res = $db->query($query)) {
		echo "<table>";
		printf("<tr><th>count(%s)</th><th>BSSID (top$topbssid)</th></tr>\n",$ybssid);
		while ($row = $res->fetch_row()) {
			$xbssid=$row[0];
			$xbssidcount=$row[1];
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xbssidcount, $xbssid);
		};
		echo "</table>";
		$res->close();
	};
	

	
?>