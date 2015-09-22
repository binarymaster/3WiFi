<?php	
require 'con_db.php'; /* Коннектор MySQL */
require 'geoext.php'; /* Модуль получения координат */

$query_bssid  = "SELECT DISTINCT `BSSID` FROM `free` WHERE `BSSID` LIKE '__:__:__:__:__:__' AND `latitude` = 'not found' LIMIY";
$query_update = "UPDATE `free` SET `latitude`=?,`longitude`=? WHERE `BSSID`=?";
$stmt_upd = $db->prepare($query_update);

$not_found = "not found";
$i = 0;

if ($res_bssid = $db->query($query_bssid))
{
	while ($row = $res_bssid->fetch_row())
	{
		$bssid = $row[0];
		$coords = GeoLocateAP($bssid);

		if ($coords != '')
		{
			$coords = explode(';', $coords);
			$latitude = $coords[0];
			$longitude = $coords[1];
			if (strlen($latitude) > 11) $latitude = substr($latitude, 0, 11);
			if (strlen($longitude) > 11) $longitude = substr($longitude, 0, 11);

			$stmt_upd->bind_param("sss", $latitude, $longitude, $bssid);
			$stmt_upd->execute();

			$i++;
		} else {
			$stmt_upd->bind_param("sss", $not_found, $not_found, $bssid);
			$stmt_upd->execute();
		}
	}
	$res_bssid->close();
}
$stmt_upd->close();
echo "$i done<br>\n";
?>