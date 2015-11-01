<?php	
function CheckLocation($db, $aps, $tid = '')
{
	require 'geoext.php'; /* Модуль получения координат */

	$query_bssid  = "SELECT SQL_NO_CACHE `BSSID` FROM `free` WHERE `latitude`='none' AND `BSSID` LIKE '__:__:__:__:__:__'";
	$query_update = "UPDATE `free` SET `latitude`=?,`longitude`=? WHERE `BSSID`=?";
	$stmt_upd = $db->prepare($query_update);

	$not_found = "not found";
	$i = 0;

	// TODO: Лучше переделать в отдельные запросы по BSSID
	if ((count($aps) > 0) && ($res_bssid = $db->query($query_bssid)))
	{
		set_time_limit(0);
		ignore_user_abort(true);
		$hangcheck = 5;
		$time = microtime(true);
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
			if ($tid != '' && microtime(true) - $time > $hangcheck)
			{
				$db->query("UPDATE `tasks` SET `onmap`='$i' WHERE `tid`='$tid'");
				$time = microtime(true);
			}
		}
		$res_bssid->close();
	}
	$stmt_upd->close();
	return $i;
}
?>