<?php
Header('Content-Type: text/plain');
echo "3WiFi Daemon Script\n\n";

if ($argv[0] != basename(__FILE__))
{
	die('This is CLI script. Use it with php-cli.');
}

if (count($argv) < 3)
{
	echo "USAGE:\n";
	echo "$argv[0] <password> <action>\n";
	exit();
}

require 'auth.php';
if ($level != 2) die("Error: Not authorized.\n");

require 'utils.php';
require 'db.php';

set_time_limit(0);

if (!db_connect())
	die("Error: MySQL connection failed.\n");

function logt($str)
{
	echo '['.date('H:i:s').'] '.$str."\n";
}

logt("Running `$argv[2]' task...");

switch ($argv[2])
{
	// Обработчик загрузок
	case 'uploads':
	function APinDB($NoBSSID, $bssid, $essid, $key)
	{
		global $db;
		$result = 0;
		$essid = $db->real_escape_string($essid);
		$key = $db->real_escape_string($key);
		// Проверяем, есть ли эта точка в базе (по BSSID/ESSID/WiFiKey)
		if ($res = QuerySql("SELECT `id` FROM BASE_TABLE WHERE `NoBSSID`=$NoBSSID AND `BSSID`=$bssid AND `ESSID`='$essid' AND `WiFiKey`='$key' LIMIT 1"))
		{
			$result = $res->num_rows;
			$res->close();
		}
		return $result > 0;
	}
	function addRow($row, $cmtid)
	{
		global $checkexist;
		global $db;
		global $aps;
		// Отбираем только валидные точки доступа
		$addr = $row[0];
		$port = $row[1];
		if ($addr == 'IP Address' && $port == 'Port')
		{
			return 1;
		}
		$bssid = $row[8];
		$essid = $row[9];
		$sec = $row[10];
		$key = $row[11];
		$wps = preg_replace('~\D+~', '', $row[12]); // Оставляем только цифры

		if ($bssid == '<no wireless>')
			return 2;

		if (ismac($bssid)) // Проверка MAC-адреса
		{
			$NoBSSID = 0;
			$bssid = mac2dec($bssid);
		} else {
			$NoBSSID = 1;
			if ($bssid == '<access denied>')
				$NoBSSID = 2;
			if ($bssid == '<not accessible>')
				$NoBSSID = 3;
			if ($bssid == '<not implemented>')
				$NoBSSID = 4;
			$bssid = 0;
		}
		if (($NoBSSID || $wps == '')
		&& ($essid == '' || $sec == '' || $sec == '-' || $key == '' || $key == '-'))
		{
			if ($NoBSSID == 0
			|| $essid != ''
			|| $sec != ''
			|| $key != ''
			|| $wps != '')
			{ return 3; } // Недостаточно полезных данных для добавления
			else { return 1; } // Вообще не содержит данных
		}
		if ($checkexist)
			if (APinDB($NoBSSID, $bssid, $essid, $key))
			{
				return 4; // Уже есть в базе, пропускаем
			}
		if ($NoBSSID == 0) // Корректный BSSID
		{
			$aps[] = $bssid; // Записываем в очередь ожидания
			$chkgeo = QuerySql("SELECT `BSSID` FROM GEO_TABLE WHERE `BSSID`=$bssid LIMIT 1");
			if ($chkgeo->num_rows == 0)
			{
				// Добавляем новый BSSID с координатами NULL
				QuerySql("INSERT INTO GEO_TABLE (`BSSID`) VALUES ($bssid)");
			}
			$chkgeo->close();
		}
		if ($cmtid == -1) $cmtid = 'NULL';
		$addr = _ip2long($addr); // IP Address
		if ($addr == 0 || $addr == -1) $addr = 'NULL';
		$port = (($port == '') ? 'NULL' : (int)$port); // Port
		$auth = ($row[4] == '' ? 'NULL' : '\''.$db->real_escape_string($row[4]).'\''); // Authorization
		$name = '\''.$db->real_escape_string($row[5]).'\''; // Device Name
		$radio = (($row[6] == '[X]') ? 1 : 0); // RadioOff
		$hide = (($row[7] == '[X]') ? 1 : 0); // Hidden
		$essid = '\''.$db->real_escape_string($essid).'\''; // ESSID
		$sec = str2sec($sec); // Security
		$key = '\''.$db->real_escape_string($key).'\''; // Wi-Fi Key
		$wps = (($wps == '') ? 'NULL' : (int)$wps); // WPS PIN
		$lanip = _ip2long($row[13]); // LAN IP
		if ($lanip == 0 || $lanip == -1) $lanip = 'NULL';
		$lanmsk = _ip2long($row[14]); // LAN Mask
		if ($lanmsk == 0) $lanmsk = 'NULL';
		$wanip = _ip2long($row[15]); // WAN IP
		if ($wanip == 0 || $wanip == -1) $wanip = 'NULL';
		$wanmsk = _ip2long($row[16]); // WAN Mask
		if ($wanmsk == 0) $wanmsk = 'NULL';
		$gate = _ip2long($row[17]); // WAN Gateway
		if ($gate == 0 || $gate == -1) $gate = 'NULL';
		$DNS = explode(' ', $row[18]); // DNS (up to 3 servers)
		for ($i = 0; $i < count($DNS); $i++)
		{
			$DNS[$i] = _ip2long($DNS[$i]);
			if ($DNS[$i] == 0 || $DNS[$i] == -1) $DNS[$i] = 'NULL';
		}
		for ($i = 0; $i <= 3; $i++)
			if (!isset($DNS[$i])) $DNS[$i] = 'NULL';

		QuerySql("INSERT INTO BASE_TABLE 
				(`cmtid`,`IP`,`Port`,`Authorization`,`name`,`RadioOff`,`Hidden`,`NoBSSID`,`BSSID`,`ESSID`,`Security`,`WiFiKey`,`WPSPIN`,`LANIP`,`LANMask`,`WANIP`,`WANMask`,`WANGateway`,`DNS1`,`DNS2`,`DNS3`) 
				VALUES ($cmtid, $addr, $port, $auth, $name, $radio, $hide, $NoBSSID, $bssid, $essid, $sec, $key, $wps, $lanip, $lanmsk, $wanip, $wanmsk, $gate, $DNS[0], $DNS[1], $DNS[2]) 
				ON DUPLICATE KEY UPDATE 
				`cmtid`=$cmtid,`IP`=$addr,`Port`=$port,`Authorization`=$auth,`name`=$name,`RadioOff`=$radio,`Hidden`=$hide,`NoBSSID`=$NoBSSID,`BSSID`=$bssid,`ESSID`=$essid,`Security`=$sec,`WiFiKey`=$key,`WPSPIN`=$wps,`LANIP`=$lanip,`LANMask`=$lanmsk,`WANIP`=$wanip,`WANMask`=$wanmsk,`WANGateway`=$gate,`DNS1`=$DNS[0],`DNS2`=$DNS[1],`DNS3`=$DNS[2];");
		return 0;
	}
	while (true)
	{
		logt('Fetching a task... (tstate = 1)');
		$res = QuerySql('SELECT `tid` FROM tasks WHERE `tstate`=1 ORDER BY `created` LIMIT 1');
		$tid = (($row = $res->fetch_row()) ? $row[0] : null);
		$res->close();
		$slp = 5;
		if ($tid != null)
		{
			$task = getTask($tid);
			logt("Processing task id = $tid");

			$checkexist = $task['checkexist'];
			$nowait = $task['nowait'];
			$warn = array();
			$ext = $task['ext'];
			$filename = 'uploads/'.$tid.$ext;
			$hangcheck = 5;
			if (($handle = fopen($filename, 'r')) !== false)
			{
				$cmtid = getCommentId($task['comment'], true);
				$i = 0;
				$cnt = 0;
				$aps = array();
				$time = microtime(true);
				switch ($ext)
				{
					case '.csv':
					while (($data = fgetcsv($handle, 1000, ';')) !== false)
					{
						$i++;
						if ($i == 1) continue; // Пропуск заголовка CSV
						$res = addRow($data, $cmtid);
						($res == 0 ? $cnt++ : $warn[$i - 1] = $res);
						if (microtime(true) - $time > $hangcheck)
						{
							logt("Status: $i processed, $cnt added (Working)");
							QuerySql("UPDATE tasks SET `lines`='$i',`accepted`='$cnt' WHERE `tid`='$tid'");
							$time = microtime(true);
						}
					}
					$i--;
					break;
					case '.txt':
					while (($str = fgets($handle)) !== false)
					{
						$data = explode("\t", $str);
						$i++;
						$res = addRow($data, $cmtid);
						($res == 0 ? $cnt++ : $warn[$i] = $res);
						if (microtime(true) - $time > $hangcheck)
						{
							logt("Status: $i processed, $cnt added (Working)");
							QuerySql("UPDATE tasks SET `lines`='$i',`accepted`='$cnt' WHERE `tid`='$tid'");
							$time = microtime(true);
						}
					}
					break;
				}
				fclose($handle);
			}
			logt("Status: $i processed, $cnt added (Done!)");
			$warns = array();
			foreach ($warn as $line => $wid)
				$warns[] = implode('|', array($line, $wid));
			$warns = implode(',', $warns);

			logt('Removing temporary file...');
			unlink($filename);
			if (!$nowait)
			{
				// Сохраняем очередь ожидания для следующего этапа
				logt('Writing .BSS list for 2nd stage...');
				$filename = 'uploads/'.$tid.'.bss';
				if (($handle = fopen($filename, 'a')) !== false)
				{
					for ($i = 0; $i < count($aps); $i++)
						fwrite($handle, $aps[$i]."\n");

					fclose($handle);
				}
				logt("Set tstate = 2 (geolocation)");
				QuerySql("UPDATE tasks SET `lines`=$i,`accepted`=$cnt,`warns`='$warns',`tstate`=2 WHERE `tid`='$tid'");
				logt("Task processing complete.");
			}
			else
			{
				QuerySql("DELETE FROM tasks WHERE `tid`='$tid'");
				logt("Task processing in `nowait' mode complete.");
			}
			$slp = 0;
		}
		else
			logt('No task was fetched.');
		if ($slp > 0) sleep($slp);
	}
	break;

	// Финализация заданий
	case 'finalize':
	function checkAPs($aps)
	{
		$result[-1] = 0;
		$result[0] = 0;
		$result[1] = 0;
		foreach ($aps as $bssid => $st)
			$result[$st]++;
		return $result;
	}
	while (true)
	{
		logt('Clean complete tasks... (tstate = 3)');
		QuerySql("DELETE FROM tasks WHERE `tstate`=3 AND `modified` < DATE_SUB(NOW(), INTERVAL 1 MINUTE)");

		logt('Fetching a task... (tstate = 2)');
		$res = QuerySql('SELECT `tid` FROM tasks WHERE `tstate`=2 ORDER BY `created` LIMIT 1');
		$tid = (($row = $res->fetch_row()) ? $row[0] : null);
		$res->close();
		$slp = 5;
		if ($tid != null)
		{
			$task = getTask($tid);
			logt("Finalizing task id = $tid");

			$aps = array();
			$filename = 'uploads/'.$tid.'.bss';
			// Получаем очередь BSSID в ожидании
			logt('Reading .BSS list...');
			if (($handle = fopen($filename, 'r')) !== false)
			{
				while (($str = fgets($handle)) !== false)
					$aps[$str] = 0;

				fclose($handle);
			}
			logt('Removing temporary file...');
			unlink($filename);

			$hangcheck = 2;
			$time = microtime(true);
			$start = $time;
			$found = 0;
			while (microtime(true) - $start < 600) // макс. 10 минут ожидания
			{
				$apschk = checkAPs($aps);
				$found = $apschk[1];
				if ($apschk[0] == 0) break; // задание обработано, выходим

				foreach ($aps as $bssid => $st)
				{
					if ($st == 0)
					{
						$res = QuerySql("SELECT `latitude`,`longitude` FROM GEO_TABLE WHERE `BSSID`=$bssid AND `latitude` IS NOT NULL LIMIT 1");
						if ($row = $res->fetch_row())
						{
							$row[0] = (float)$row[0];
							$row[1] = (float)$row[1];
							$aps[$bssid] = ($row[0] == 0 && $row[1] == 0 ? -1 : 1);
						}
						$res->close();
					}
				}
				if (microtime(true) - $time > $hangcheck)
				{
					logt('Status: '.$apschk[1].' found, '.$apschk[-1].' no, '.$apschk[0].' left (Working)');
					QuerySql("UPDATE tasks SET `onmap`=$found WHERE `tid`='$tid'");
					$time = microtime(true);
				}
				sleep(2);
			}
			logt('Status: '.$apschk[1].' found, '.$apschk[-1].' not found (Done!)');

			logt("Set tstate = 3 (complete)");
			QuerySql("UPDATE tasks SET `onmap`=$found,`tstate`=3 WHERE `tid`='$tid'");
			logt("Task processing complete.");
			$slp = 0;
		}
		else
			logt('No task was fetched.');			
		if ($slp > 0) sleep($slp);
	}
	break;

	// Получение координат для новых добавленных BSSID
	case 'geolocate':
	require 'geoext.php';

	while (true)
	{
		logt('Fetching incomplete BSSIDs...');
		$res = QuerySql('SELECT `BSSID` FROM GEO_TABLE WHERE `latitude` IS NULL');
		$total = $res->num_rows;
		if ($total == 0)
		{
			logt('No new BSSIDs was fetched.');
		}
		else
		{
			$done = 0;
			$found = 0;
			$hangcheck = 5;
			$time = microtime(true);
			while ($row = $res->fetch_row())
			{
				$done++;
				$bssid = $row[0];
				$latitude = 0;
				$longitude = 0;
				$coords = GeoLocateAP(dec2mac($bssid));
				if ($coords != '')
				{
					$found++;
					$coords = explode(';', $coords);
					$latitude = (float)$coords[0];
					$longitude = (float)$coords[1];
				}
				QuerySql("UPDATE GEO_TABLE SET `latitude`=$latitude,`longitude`=$longitude WHERE `BSSID`=$bssid");
				if (microtime(true) - $time > $hangcheck)
				{
					logt("Status: $done of $total, $found found on map (Working)");
					$time = microtime(true);
				}
			}
			logt("Status: $done of $total, $found found on map (Done!)");
		}
		$res->close();
		sleep(10);
	}
	break;

	// Обновление ранее не найденных точек
	case 'recheck':
	require 'geoext.php';
	// TODO
	break;

	default:
	logt("Error: Unsupported action `$argv[2]'");
	break;
}
$db->close();
?>