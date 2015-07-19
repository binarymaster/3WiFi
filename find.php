<?php
if (isset($_POST['pass'])) {$pass = $_POST['pass'];} else {$pass='';};
if (isset($_POST['bssid'])&&($_POST['bssid']!='')) {$bssid = $_POST['bssid'];} else {$bssid='%';};
if (isset($_POST['essid'])&&($_POST['essid']!='')) {$essid = $_POST['essid'];} else {$essid='%';}; 
?>
<html><head>
<title>3WiFi: Поиск по базе</title>
<meta http-equiv=Content-Type content="text/html;charset=UTF-8">
<link rel=stylesheet href="css/style.css" type="text/css">
</head><body>

<form enctype="multipart/form-data" action="find.php" method="POST">

	<table>
	<tr><td>Пароль доступа:</td><td><input name="pass" type="password" value="<?php echo htmlspecialchars($pass);?>" /></td></tr>
	<tr><td>BSSID / MAC:</td><td><input name="bssid" type="text" value="<?php echo htmlspecialchars($bssid);?>" /></td><td>(% - заменяющий символ)</td></tr>
	<tr><td>ESSID / Имя:</td><td><input name="essid" type="text" value="<?php echo htmlspecialchars($essid);?>" /></td><td>(% - заменяющий символ)</td></tr>
	<tr><td><input type="submit" value="Найти" /></td><td></td></tr>
	</table>

</form>

<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pass']))
{
	$password = "antichat";

	require 'con_db.php'; /* Коннектор MySQL */

	if ($pass == $password) {
		$query="SELECT SQL_NO_CACHE * FROM `free` WHERE `BSSID` LIKE '$bssid' AND `ESSID` LIKE '$essid'";
		if ($res = $db->query($query)) {
			echo "<table class=st1>";
			printf("<tr><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th></tr>\n","Time", "Comment", "BSSID", "ESSID", "Security","Wi-Fi Key", "WPS PIN", "Latitude", "Longitude","Map");
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
				}
				$xtime = preg_replace('/\s+/', '<br>', $xtime);
				printf("<tr><td><tt>%s</tt></td><td>%s</td><td><tt>%s</tt></td><td>%s</td><td>%s</td><td>%s</td><td><tt>%s</tt></td><td>%s</td><td>%s</td><td>%s</td></tr>\n", $xtime, $xcomment, $xbssid, $xessid, $xsecurity, $xwifikey, $xwpspin, $xlatitude, $xlongitude, $xmap);
			}
			echo "</table>";
			$res->close();
		}
	} else {
		die("AUTH FAILED");
	}
}
?>
</body></html>