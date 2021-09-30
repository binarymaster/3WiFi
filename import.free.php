<?php
/**

3WiFi Script for importing old `free` table into new base, use once

**/

set_time_limit(0);
//ignore_user_abort(true);
include 'config.php';
require 'utils.php';
require 'db.php';

db_connect();

$sql = 'SELECT * FROM free WHERE 1 ORDER BY `time` ASC';
$res = $db->query($sql);
if ($res->num_rows == 0) exit();

$sql_wifi = 'INSERT INTO '.BASE_TABLE.' (`time`,`cmtid`,`IP`,`Port`,`Authorization`,`name`,`RadioOff`,`Hidden`,`NoBSSID`,`BSSID`,`ESSID`,`Security`,`WiFiKey`,`WPSPIN`,`LANIP`,`LANMask`,`WANIP`,`WANMask`,`WANGateway`,`DNS1`,`DNS2`,`DNS3`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE `cmtid`=?,`IP`=?,`Port`=?,`Authorization`=?,`name`=?,`RadioOff`=?,`Hidden`=?,`NoBSSID`=?,`BSSID`=?,`ESSID`=?,`Security`=?,`WiFiKey`=?,`WPSPIN`=?,`LANIP`=?,`LANMask`=?,`WANIP`=?,`WANMask`=?,`WANGateway`=?,`DNS1`=?,`DNS2`=?,`DNS3`=?;';
$wifi = $db->prepare($sql_wifi);

$sql_geo = 'INSERT INTO '.GEO_TABLE.' (`BSSID`,`latitude`,`longitude`) VALUES (?,?,?) ON DUPLICATE KEY UPDATE `BSSID`=?,`latitude`=?,`longitude`=?;';
$geo = $db->prepare($sql_geo);

while($row = $res->fetch_assoc())
{
	$row['comment'] = trim(preg_replace('/\s+/', ' ', $row['comment']));
	if ($row['comment'] == 'none') $row['comment'] = '';
	$cmtid = getCommentId($row['comment'], true);
	$row['IP'] = _ip2long($row['IP']);
	if ($row['IP'] == 0 || $row['IP'] == -1) $row['IP'] = null;
	$row['Port'] = (($row['Port'] == '') ? null : (int)$row['Port']);
	if ($row['Authorization'] == '') $row['Authorization'] = null;
	$row['RadioOff'] = (($row['RadioOff'] == '[X]') ? 1 : 0);
	$row['Hidden'] = (($row['Hidden'] == '[X]') ? 1 : 0);

	if (ismac($row['BSSID']))
	{
		$NoBSSID = 0;
		$row['BSSID'] = mac2dec($row['BSSID']);
	} else {
		$NoBSSID = 1;
		if ($row['BSSID'] == '<access denied>')
			$NoBSSID = 2;
		if ($row['BSSID'] == '<not accessible>')
			$NoBSSID = 3;
		if ($row['BSSID'] == '<not implemented>')
			$NoBSSID = 4;
		$row['BSSID'] = 0;
	}
	$row['Security'] = str2sec($row['Security']);
	$row['WPSPIN'] = preg_replace('~\D+~', '', $row['WPSPIN']);
	$row['WPSPIN'] = (($row['WPSPIN'] == '') ? 1 : (int)$row['WPSPIN']);
	$row['LANIP'] = _ip2long($row['LANIP']);
	if ($row['LANIP'] == 0 || $row['LANIP'] == -1) $row['LANIP'] = null;
	$row['LANMask'] = _ip2long($row['LANMask']);
	if ($row['LANMask'] == 0) $row['LANMask'] = null;
	$row['WANIP'] = _ip2long($row['WANIP']);
	if ($row['WANIP'] == 0 || $row['WANIP'] == -1) $row['WANIP'] = null;
	$row['WANMask'] = _ip2long($row['WANMask']);
	if ($row['WANMask'] == 0) $row['WANMask'] = null;
	$row['WANGateway'] = _ip2long($row['WANGateway']);
	if ($row['WANGateway'] == 0 || $row['WANGateway'] == -1) $row['WANGateway'] = null;

	$DNS = explode(' ', $row['DNS']);
	for ($i = 0; $i < count($DNS); $i++)
	{
		$DNS[$i] = _ip2long($DNS[$i]);
		if ($DNS[$i] == 0 || $DNS[$i] == -1) $DNS[$i] = null;
	}

	$wifi->bind_param('siiissiiissisiiiiiiiiiiiissiiissisiiiiiiiii',
	// INSERT                                ^ 2nd part
	$row['time'],$cmtid,$row['IP'],$row['Port'],$row['Authorization'],$row['name'],$row['RadioOff'],$row['Hidden'],$NoBSSID,$row['BSSID'],$row['ESSID'],$row['Security'],$row['WiFiKey'],$row['WPSPIN'],$row['LANIP'],$row['LANMask'],$row['WANIP'],$row['WANMask'],$row['WANGateway'],$DNS[0],$DNS[1],$DNS[2],
	// UPDATE
	$cmtid,$row['IP'],$row['Port'],$row['Authorization'],$row['name'],$row['RadioOff'],$row['Hidden'],$NoBSSID,$row['BSSID'],$row['ESSID'],$row['Security'],$row['WiFiKey'],$row['WPSPIN'],$row['LANIP'],$row['LANMask'],$row['WANIP'],$row['WANMask'],$row['WANGateway'],$DNS[0],$DNS[1],$DNS[2]);
	$wifi->execute();

	if ($NoBSSID == 0 && $row['latitude'] != 'none')
	{
		if ($row['latitude'] == 'not found')
		{
			$row['latitude'] = 0;
			$row['longitude'] = 0;
		}
		$geo->bind_param('sddsdd',
		// INSERT
		$row['BSSID'],$row['latitude'],$row['longitude'],
		// UPDATE
		$row['BSSID'],$row['latitude'],$row['longitude']);
		$geo->execute();
	}
}
