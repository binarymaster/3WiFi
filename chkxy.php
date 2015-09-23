<?php	
function CheckLocation($aps)
{
	require 'con_db.php'; /* Коннектор MySQL */
	require 'geoext.php'; /* Модуль получения координат */

	$query_bssid  = "SELECT `BSSID` FROM `free` WHERE `BSSID` LIKE '__:__:__:__:__:__' AND `latitude` = 'none'";
	$query_update = "UPDATE `free` SET `latitude`=?,`longitude`=? WHERE `BSSID`=?";
	$stmt_upd = $db->prepare($query_update);

	$not_found = "not found";
	$i = 0;

	if ((count($aps) > 0) && ($res_bssid = $db->query($query_bssid)))
	{
		set_time_limit(0);
		while ($row = $res_bssid->fetch_row())
		{
			$bssid = $row[0];
			if (!in_array($bssid, $aps))
				continue;

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
	return $i;
}
?>