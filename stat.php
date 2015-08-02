<?php
$topPort=10;
$topauth=100;
$topname=30;
$topbssid=30;
$topessid=30;
$topSecurity=30;
$topWiFiKey=30;
$topWPSPIN=30;
$topWANGateway=30;
$topDNS=30;
?>
<html><head>
<title>3WiFi: Статистика</title>
<meta http-equiv=Content-Type content="text/html;charset=UTF-8">
<link rel=stylesheet href="css/style.css" type="text/css">
</head><body>
<?php
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
		echo "<table class=st1>";
		printf("<tr><th>count(%s)</th><th>comment (%s)</th></tr>\n", $ycount, $ycomment);
		while ($row = $res->fetch_row()) {
			$xcomment=$row[0];
			$xcount=$row[1];
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xcount, $xcomment);
		};
		echo "</table><br>";
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
		echo "<table class=st1>";
		printf("<tr><th>count(%s)</th><th>name (top$topname)</th></tr>\n",$yname);
		while ($row = $res->fetch_row()) {
			$xname=$row[0];
			$xnamecount=$row[1];
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xnamecount, $xname);
		};
		echo "</table><br>";
		$res->close();
	};

	/* Таблица порты */
	
	$query="SELECT COUNT(DISTINCT `Port`) FROM `free`";
	if ($res = $db->query($query)) {
		$row = $res->fetch_row();
		$yauth=$row[0];
		$res->close();
	};
	
	$query="SELECT `Port`, COUNT(*) FROM free GROUP BY `Port` ORDER BY COUNT(*) DESC LIMIT $topPort";
	if ($res = $db->query($query)) {
		echo "<table class=st1>";
		printf("<tr><th>count(%s)</th><th>Port (top$topPort)</th></tr>\n",$yauth);
		while ($row = $res->fetch_row()) {
			$xauth=$row[0];
			$xauthcount=$row[1];
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xauthcount, $xauth);
		};
		echo "</table><br>";
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
		echo "<table class=st1>";
		printf("<tr><th>count(%s)</th><th>Authorization (top$topauth)</th></tr>\n",$yauth);
		while ($row = $res->fetch_row()) {
			$xauth=$row[0];
			$xauthcount=$row[1];
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xauthcount, $xauth);
		};
		echo "</table><br>";
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
		echo "<table class=st1>";
		printf("<tr><th>count(%s)</th><th>BSSID (top$topbssid)</th></tr>\n",$ybssid);
		while ($row = $res->fetch_row()) {
			$xbssid=$row[0];
			$xbssidcount=$row[1];
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xbssidcount, $xbssid);
		};
		echo "</table><br>";
		$res->close();
	};
	
	/* Таблица ESSID */
	
	$query="SELECT COUNT(DISTINCT `ESSID`) FROM `free`";
	if ($res = $db->query($query)) {
		$row = $res->fetch_row();
		$yessid=$row[0];
		$res->close();
	};
	
	$query="SELECT `ESSID`, COUNT(*) FROM free GROUP BY `ESSID` ORDER BY COUNT(*) DESC LIMIT $topessid";
	if ($res = $db->query($query)) {
		echo "<table class=st1>";
		printf("<tr><th>count(%s)</th><th>ESSID (top$topessid)</th></tr>\n",$yessid);
		while ($row = $res->fetch_row()) {
			$xessid=$row[0];
			$xessidcount=$row[1];
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xessidcount, $xessid);
		};
		echo "</table><br>";
		$res->close();
	};

	/* Таблица Security */
	
	$query="SELECT COUNT(DISTINCT `Security`) FROM `free`";
	if ($res = $db->query($query)) {
		$row = $res->fetch_row();
		$ySecurity=$row[0];
		$res->close();
	};
	
	$query="SELECT `Security`, COUNT(*) FROM free GROUP BY `Security` ORDER BY COUNT(*) DESC LIMIT $topSecurity";
	if ($res = $db->query($query)) {
		echo "<table class=st1>";
		printf("<tr><th>count(%s)</th><th>Security (top$topSecurity)</th></tr>\n",$ySecurity);
		while ($row = $res->fetch_row()) {
			$xSecurity=$row[0];
			$xSecuritycount=$row[1];
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xSecuritycount, $xSecurity);
		};
		echo "</table><br>";
		$res->close();
	};

	/* Таблица WiFiKey */
	
	$query="SELECT COUNT(DISTINCT `WiFiKey`) FROM `free`";
	if ($res = $db->query($query)) {
		$row = $res->fetch_row();
		$yWiFiKey=$row[0];
		$res->close();
	};
	
	$query="SELECT `WiFiKey`, COUNT(*) FROM free GROUP BY `WiFiKey` ORDER BY COUNT(*) DESC LIMIT $topWiFiKey";
	if ($res = $db->query($query)) {
		echo "<table class=st1>";
		printf("<tr><th>count(%s)</th><th>WiFiKey (top$topWiFiKey)</th></tr>\n",$yWiFiKey);
		while ($row = $res->fetch_row()) {
			$xWiFiKey=$row[0];
			$xWiFiKeycount=$row[1];
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xWiFiKeycount, $xWiFiKey);
		};
		echo "</table><br>";
		$res->close();
	};

	
	/* Таблица WPSPIN */
	
	$query="SELECT COUNT(DISTINCT `WPSPIN`) FROM `free`";
	if ($res = $db->query($query)) {
		$row = $res->fetch_row();
		$yWPSPIN=$row[0];
		$res->close();
	};
	
	$query="SELECT `WPSPIN`, COUNT(*) FROM free GROUP BY `WPSPIN` ORDER BY COUNT(*) DESC LIMIT $topWPSPIN";
	if ($res = $db->query($query)) {
		echo "<table class=st1>";
		printf("<tr><th>count(%s)</th><th>WPSPIN (top$topWPSPIN)</th></tr>\n",$yWPSPIN);
		while ($row = $res->fetch_row()) {
			$xWPSPIN=$row[0];
			$xWPSPINcount=$row[1];
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xWPSPINcount, $xWPSPIN);
		};
		echo "</table><br>";
		$res->close();
	};

	
	/* Таблица WANGateway */
	
	$query="SELECT COUNT(DISTINCT `WANGateway`) FROM `free`";
	if ($res = $db->query($query)) {
		$row = $res->fetch_row();
		$yWANGateway=$row[0];
		$res->close();
	};
	
	$query="SELECT `WANGateway`, COUNT(*) FROM free GROUP BY `WANGateway` ORDER BY COUNT(*) DESC LIMIT $topWANGateway";
	if ($res = $db->query($query)) {
		echo "<table class=st1>";
		printf("<tr><th>count(%s)</th><th>WANGateway (top$topWANGateway)</th></tr>\n",$yWANGateway);
		while ($row = $res->fetch_row()) {
			$xWANGateway=$row[0];
			$xWANGatewaycount=$row[1];
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xWANGatewaycount, $xWANGateway);
		};
		echo "</table><br>";
		$res->close();
	};
	
	
	/* Таблица DNS */
	
	$query="SELECT COUNT(DISTINCT `DNS`) FROM `free`";
	if ($res = $db->query($query)) {
		$row = $res->fetch_row();
		$yDNS=$row[0];
		$res->close();
	};
	
	$query="SELECT `DNS`, COUNT(*) FROM free GROUP BY `DNS` ORDER BY COUNT(*) DESC LIMIT $topDNS";
	if ($res = $db->query($query)) {
		echo "<table class=st1>";
		printf("<tr><th>count(%s)</th><th>DNS (top$topDNS)</th></tr>\n",$yDNS);
		while ($row = $res->fetch_row()) {
			$xDNS=$row[0];
			$xDNScount=$row[1];
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xDNScount, $xDNS);
		};
		echo "</table>";
		$res->close();
	};
?></body></html>