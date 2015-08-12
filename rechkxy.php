<?php	
require 'con_db.php'; /* Коннектор MySQL */

$query_bssid  = "SELECT DISTINCT `BSSID` FROM `free` WHERE `BSSID` LIKE '__:__:__:__:__:__' AND `latitude` = 'not found' LIMIY";

$query_update = "UPDATE `free` SET `latitude`=?,`longitude`=? WHERE `BSSID`=?";
$stmt_upd = $db->prepare($query_update);

$t1 = "http://mobile.maps.yandex.net/cellid_location/?clid=1866854&lac=-1&cellid=-1&operatorid=null&countrycode=null&signalstrength=-1&wifinetworks=";
$t2 = ":-65&app";
$not_found = "not found";
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_ENCODING, 'utf-8');
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
$i = 0;

if ($res_bssid = $db->query($query_bssid))
{
	while ($row = $res_bssid->fetch_row())
	{
		$bssid = $row[0];
		$bssid_cut = str_replace(":","",$bssid);
		$t = $t1.$bssid_cut.$t2;
		curl_setopt($ch, CURLOPT_URL, $t);
		$data = curl_exec($ch);

		$pos1 = strpos($data, "latitude=");
		if (!($pos1 === false))
		{
			$pos2 = strpos($data, "longitude=");
			$pos3 = strpos($data, "nlatitude=");

			$latitude =substr($data,$pos1+10, $pos2-$pos1-12);
			$longitude=substr($data,$pos2+11, $pos3-$pos2-13);

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
curl_close($ch);
echo "$i done<br>\n";
?>