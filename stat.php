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
$yvalue = 0;
$ycount = 0;
$query="SELECT COUNT(DISTINCT `comment`),COUNT(*) FROM `free`";
if ($res = $db->query($query)) {
	$row = $res->fetch_row();
	$yvalue=$row[0];
	$ycount=$row[1];
	$res->close();
};
echo "<table class=st1>";
printf("<tr><th>count (%s)</th><th>comment (%s)</th></tr>\n", $ycount, $yvalue);
$query="SELECT `comment`, COUNT(*) FROM free GROUP BY `comment` ORDER BY COUNT(*) DESC";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td>%s</td></tr>\n", $row[1], $row[0]);
	};
	$res->close();
};

/* Таблица устройства */
$yvalue = 0;
$query="SELECT COUNT(DISTINCT `name`) FROM `free`";
if ($res = $db->query($query)) {
	$row = $res->fetch_row();
	$yvalue=$row[0];
	$res->close();
};
printf("<tr><th>count (%s)</th><th>name (top$topname)</th></tr>\n",$yvalue);
$query="SELECT `name`, COUNT(*) FROM free GROUP BY `name` ORDER BY COUNT(*) DESC LIMIT $topname";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td>%s</td></tr>\n", $row[1], $row[0]);
	};
	$res->close();
};

/* Таблица порты */
$yvalue = 0;
$query="SELECT COUNT(DISTINCT `Port`) FROM `free`";
if ($res = $db->query($query)) {
	$row = $res->fetch_row();
	$yvalue=$row[0];
	$res->close();
};
printf("<tr><th>count (%s)</th><th>Port (top$topPort)</th></tr>\n",$yvalue);
$query="SELECT `Port`, COUNT(*) FROM free GROUP BY `Port` ORDER BY COUNT(*) DESC LIMIT $topPort";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td>%s</td></tr>\n", $row[1], $row[0]);
	};
	$res->close();
};

/* Таблица авторизация */
$yvalue = 0;
$query="SELECT COUNT(DISTINCT `Authorization`) FROM `free`";
if ($res = $db->query($query)) {
	$row = $res->fetch_row();
	$yvalue=$row[0];
	$res->close();
};
printf("<tr><th>count (%s)</th><th>Authorization (top$topauth)</th></tr>\n",$yvalue);
$query="SELECT `Authorization`, COUNT(*) FROM free GROUP BY `Authorization` ORDER BY COUNT(*) DESC LIMIT $topauth";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		$xcount = $row[1];
		$xvalue = $row[0];
		if (strlen($xvalue) > 64)
		{
			printf("<tr><td>%s</td><td style=\"max-width:700px;overflow-x:scroll\">%s</td></tr>\n", $xcount, $xvalue);
		} else {
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xcount, $xvalue);
		}
	};
	$res->close();
};

/* Таблица BSSID */
$yvalue = 0;
$query="SELECT COUNT(DISTINCT `BSSID`) FROM `free`";
if ($res = $db->query($query)) {
	$row = $res->fetch_row();
	$yvalue=$row[0];
	$res->close();
};
printf("<tr><th>count (%s)</th><th>BSSID (top$topbssid)</th></tr>\n",$yvalue);
$query="SELECT `BSSID`, COUNT(*) FROM free GROUP BY `BSSID` ORDER BY COUNT(*) DESC LIMIT $topbssid";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td><tt>%s</tt></td></tr>\n", $row[1], $row[0]);
	};
	$res->close();
};

/* Таблица ESSID */
$yvalue = 0;
$query="SELECT COUNT(DISTINCT `ESSID`) FROM `free`";
if ($res = $db->query($query)) {
	$row = $res->fetch_row();
	$yvalue=$row[0];
	$res->close();
};
printf("<tr><th>count (%s)</th><th>ESSID (top$topessid)</th></tr>\n",$yvalue);
$query="SELECT `ESSID`, COUNT(*) FROM free GROUP BY `ESSID` ORDER BY COUNT(*) DESC LIMIT $topessid";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td>%s</td></tr>\n", $row[1], $row[0]);
	};
	$res->close();
};

/* Таблица Security */
$yvalue = 0;
$query="SELECT COUNT(DISTINCT `Security`) FROM `free`";
if ($res = $db->query($query)) {
	$row = $res->fetch_row();
	$yvalue=$row[0];
	$res->close();
};
printf("<tr><th>count (%s)</th><th>Security (top$topSecurity)</th></tr>\n",$yvalue);
$query="SELECT `Security`, COUNT(*) FROM free GROUP BY `Security` ORDER BY COUNT(*) DESC LIMIT $topSecurity";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td>%s</td></tr>\n", $row[1], $row[0]);
	};
	$res->close();
};

/* Таблица WiFiKey */
$yvalue = 0;
$query="SELECT COUNT(DISTINCT `WiFiKey`) FROM `free`";
if ($res = $db->query($query)) {
	$row = $res->fetch_row();
	$yvalue=$row[0];
	$res->close();
};
printf("<tr><th>count (%s)</th><th>WiFiKey (top$topWiFiKey)</th></tr>\n",$yvalue);
$query="SELECT `WiFiKey`, COUNT(*) FROM free GROUP BY `WiFiKey` ORDER BY COUNT(*) DESC LIMIT $topWiFiKey";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td>%s</td></tr>\n", $row[1], $row[0]);
	};
	$res->close();
};

/* Таблица WPSPIN */
$yvalue = 0;
$query="SELECT COUNT(DISTINCT `WPSPIN`) FROM `free`";
if ($res = $db->query($query)) {
	$row = $res->fetch_row();
	$yvalue=$row[0];
	$res->close();
};
printf("<tr><th>count (%s)</th><th>WPSPIN (top$topWPSPIN)</th></tr>\n",$yvalue);
$query="SELECT `WPSPIN`, COUNT(*) FROM free GROUP BY `WPSPIN` ORDER BY COUNT(*) DESC LIMIT $topWPSPIN";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td><tt>%s</tt></td></tr>\n", $row[1], $row[0]);
	};
	$res->close();
};

/* Таблица WANGateway */
$yvalue = 0;
$query="SELECT COUNT(DISTINCT `WANGateway`) FROM `free`";
if ($res = $db->query($query)) {
	$row = $res->fetch_row();
	$yvalue=$row[0];
	$res->close();
};
printf("<tr><th>count (%s)</th><th>WANGateway (top$topWANGateway)</th></tr>\n",$yvalue);
$query="SELECT `WANGateway`, COUNT(*) FROM free GROUP BY `WANGateway` ORDER BY COUNT(*) DESC LIMIT $topWANGateway";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td>%s</td></tr>\n", $row[1], $row[0]);
	};
	$res->close();
};

/* Таблица DNS */
$yvalue = 0;
$query="SELECT COUNT(DISTINCT `DNS`) FROM `free`";
if ($res = $db->query($query)) {
	$row = $res->fetch_row();
	$yvalue=$row[0];
	$res->close();
};
printf("<tr><th>count (%s)</th><th>DNS (top$topDNS)</th></tr>\n",$yvalue);
$query="SELECT `DNS`, COUNT(*) FROM free GROUP BY `DNS` ORDER BY COUNT(*) DESC LIMIT $topDNS";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td>%s</td></tr>\n", $row[1], $row[0]);
	};
	$res->close();
};
?></table></body></html>