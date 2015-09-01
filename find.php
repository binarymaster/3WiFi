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

<form enctype="multipart/form-data" method="POST">
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
		$bssid = $db->real_escape_string($bssid);
		$essid = $db->real_escape_string($essid);
		$query = "SELECT * FROM `free` WHERE `BSSID` LIKE '$bssid' AND `ESSID` LIKE '$essid'";
		$nowrap = 'style="white-space:nowrap"';
		$overflow = 'style="max-width:200px;overflow-x:scroll;white-space:nowrap"';
		if ($res = $db->query($query)) {
			echo "<table class=st1>";
			printf("<tr><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th></tr>\n","Time", "Comment", "BSSID", "ESSID", "Security","Wi-Fi Key", "WPS PIN", "Latitude", "Longitude","Map");
			while ($row = $res->fetch_row()) {
				$xtime = substr($row[1], 0, 10);
				$xcomment = htmlspecialchars($row[2]);
				$xbssid = htmlspecialchars($row[9]);
				$xessid = htmlspecialchars($row[10]);
				$xsecurity = htmlspecialchars($row[11]);
				$xwifikey = htmlspecialchars($row[12]);
				$xwpspin = htmlspecialchars($row[13]);
				$xlatitude = $row[20];
				$xlongitude = $row[21];
				if (($xlatitude!="none")&&($xlatitude!="not found")&&($xlongitude!="none")&&($xlongitude!="not found")) {
					$xmap = '<a href="map3.php?lat='.$xlatitude.'&lon='.$xlongitude.'">map</a>';
				} else {
					$xmap = '';
				}
				//          |              Time         | Comment  |     BSSID         | ESSID    | Security
				printf("<tr><td $nowrap><tt>%s</tt></td><td>%s</td><td><tt>%s</tt></td><td>%s</td><td>%s</td>", $xtime, $xcomment, $xbssid, $xessid, $xsecurity);
				// Wi-Fi Key
				if (strlen($xwifikey) > 20)
				{
					printf("<td $overflow>%s</td>", $xwifikey);
				} else {
					printf("<td>%s</td>", $xwifikey);
				}
				//      |     WPS PIN       |       Latitude   |       Longitude  | Map Link
				printf("<td><tt>%s</tt></td><td $nowrap>%s</td><td $nowrap>%s</td><td>%s</td></tr>\n", $xwpspin, $xlatitude, $xlongitude, $xmap);
			}
			$res->close();
			echo "</table>";
		}
	} else {
		die("AUTH FAILED");
	}
}
?>
</body></html>