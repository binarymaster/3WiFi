<?php
$topPort = 10;
$topauth = 100;
$topname = 30;
$topbssid = 30;
$topessid = 30;
$topSecurity = 30;
$topWiFiKey = 30;
$topWPSPIN = 30;
$topWANGateway = 30;
$topDNS = 30;
header('Cache-Control: max-age=30, private, no-store');
?>
<html><head>
<title>3WiFi: Статистика</title>
<meta http-equiv=Content-Type content="text/html;charset=UTF-8">
<link rel=stylesheet href="css/style.css" type="text/css">
</head><body>
<h2 align="center">Статистика от <?php echo date('Y.m.d H:i:s'); ?> GMT.</h2>
<?php
require 'con_db.php'; /* Коннектор MySQL */

echo "<table class=st1>";
$total = 0;
$yvalue = 0;
$query="SELECT COUNT(DISTINCT `comment`),COUNT(*) FROM `free`";
if ($res = $db->query($query)) {
	$row = $res->fetch_row();
	$yvalue=$row[0];
	$total=$row[1];
	$res->close();
};
$query="SELECT COUNT(*) FROM `free` WHERE `BSSID` LIKE '__:__:__:__:__:__'";
$bssidok = 0;
if ($res = $db->query($query)) {
	$row = $res->fetch_row();
	$bssidok = $row[0];
	$res->close();
};
$query="SELECT COUNT(*) FROM `free` WHERE `BSSID` LIKE '__:__:__:__:__:__'";
$bssidok = 0;
if ($res = $db->query($query)) {
	$row = $res->fetch_row();
	$bssidok = $row[0];
	$res->close();
};
$query="SELECT COUNT(*) FROM `free` WHERE `latitude` > 0 AND `longitude` > 0";
$geook = 0;
if ($res = $db->query($query)) {
	$row = $res->fetch_row();
	$geook = $row[0];
	$res->close();
};
$query="SELECT COUNT(*) FROM `free` WHERE `latitude` = 'none' AND `longitude` = 'none'";
$inprogress = 0;
if ($res = $db->query($query)) {
	$row = $res->fetch_row();
	$inprogress = $row[0];
	$res->close();
};
echo "<tr><th>Кол-во</th><th>Сводная таблица</th></tr>\n";
printf("<tr><td>%s</td><td>Всего записей в базе</td></tr>\n", $total);
printf("<tr><td>%s</td><td>С корректным BSSID</td></tr>\n", $bssidok);
printf("<tr><td>%s</td><td>Найдено на карте</td></tr>\n", $geook);
printf("<tr><td>%s</td><td>В процессе обработки</td></tr>\n", $inprogress);

/* Таблица комментарии */
printf("<tr><th>Кол-во (%s)</th><th>Комментарии (%s)</th></tr>\n", $total, $yvalue);
$query="SELECT `comment`, COUNT(*) FROM free GROUP BY `comment` ORDER BY COUNT(*) DESC";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td>%s</td></tr>\n", $row[1], htmlspecialchars($row[0]));
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
printf("<tr><th>Кол-во (%s)</th><th>Названия устройств / прошивок (топ$topname)</th></tr>\n",$yvalue);
$query="SELECT `name`, COUNT(*) FROM free GROUP BY `name` ORDER BY COUNT(*) DESC LIMIT $topname";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td>%s</td></tr>\n", $row[1], htmlspecialchars($row[0]));
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
printf("<tr><th>Кол-во (%s)</th><th>Порты (топ$topPort)</th></tr>\n",$yvalue);
$query="SELECT `Port`, COUNT(*) FROM free GROUP BY `Port` ORDER BY COUNT(*) DESC LIMIT $topPort";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td>%s</td></tr>\n", $row[1], htmlspecialchars($row[0]));
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
printf("<tr><th>Кол-во (%s)</th><th>Данные авторизации (топ$topauth)</th></tr>\n",$yvalue);
$query="SELECT `Authorization`, COUNT(*) FROM free GROUP BY `Authorization` ORDER BY COUNT(*) DESC LIMIT $topauth";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		$xcount = $row[1];
		$xvalue = $row[0];
		if (strlen($xvalue) > 64)
		{
			printf("<tr><td>%s</td><td style=\"max-width:700px;overflow-x:scroll\">%s</td></tr>\n", $xcount, htmlspecialchars($xvalue));
		} else {
			printf("<tr><td>%s</td><td>%s</td></tr>\n", $xcount, htmlspecialchars($xvalue));
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
printf("<tr><th>Кол-во (%s)</th><th>BSSID / MAC-адрес (топ$topbssid)</th></tr>\n",$yvalue);
$query="SELECT `BSSID`, COUNT(*) FROM free GROUP BY `BSSID` ORDER BY COUNT(*) DESC LIMIT $topbssid";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td><tt>%s</tt></td></tr>\n", $row[1], htmlspecialchars($row[0]));
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
printf("<tr><th>Кол-во (%s)</th><th>ESSID / Имя сети (топ$topessid)</th></tr>\n",$yvalue);
$query="SELECT `ESSID`, COUNT(*) FROM free GROUP BY `ESSID` ORDER BY COUNT(*) DESC LIMIT $topessid";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td>%s</td></tr>\n", $row[1], htmlspecialchars($row[0]));
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
printf("<tr><th>Кол-во (%s)</th><th>Тип защиты (топ$topSecurity)</th></tr>\n",$yvalue);
$query="SELECT `Security`, COUNT(*) FROM free GROUP BY `Security` ORDER BY COUNT(*) DESC LIMIT $topSecurity";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td>%s</td></tr>\n", $row[1], htmlspecialchars($row[0]));
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
printf("<tr><th>Кол-во (%s)</th><th>Ключ сети (топ$topWiFiKey)</th></tr>\n",$yvalue);
$query="SELECT `WiFiKey`, COUNT(*) FROM free GROUP BY `WiFiKey` ORDER BY COUNT(*) DESC LIMIT $topWiFiKey";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td>%s</td></tr>\n", $row[1], htmlspecialchars($row[0]));
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
printf("<tr><th>Кол-во (%s)</th><th>Пин код WPS (топ$topWPSPIN)</th></tr>\n",$yvalue);
$query="SELECT `WPSPIN`, COUNT(*) FROM free GROUP BY `WPSPIN` ORDER BY COUNT(*) DESC LIMIT $topWPSPIN";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td><tt>%s</tt></td></tr>\n", $row[1], htmlspecialchars($row[0]));
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
printf("<tr><th>Кол-во (%s)</th><th>Шлюз WAN (топ$topWANGateway)</th></tr>\n",$yvalue);
$query="SELECT `WANGateway`, COUNT(*) FROM free GROUP BY `WANGateway` ORDER BY COUNT(*) DESC LIMIT $topWANGateway";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td>%s</td></tr>\n", $row[1], htmlspecialchars($row[0]));
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
printf("<tr><th>Кол-во (%s)</th><th>Серверы доменных имён (топ$topDNS)</th></tr>\n",$yvalue);
$query="SELECT `DNS`, COUNT(*) FROM free GROUP BY `DNS` ORDER BY COUNT(*) DESC LIMIT $topDNS";
if ($res = $db->query($query)) {
	while ($row = $res->fetch_row()) {
		printf("<tr><td>%s</td><td>%s</td></tr>\n", $row[1], htmlspecialchars($row[0]));
	};
	$res->close();
};
?></table>
</body></html>