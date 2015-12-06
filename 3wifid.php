<?php
require_once 'auth.php';
require_once 'utils.php';
require_once 'db.php';
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

if ($level != 2) die("Error: Not authorized.\n");

set_time_limit(0);

while (!db_connect())
{
	logt('Error: MySQL connection failed. Retrying in 5 seconds...');
	sleep(5);
}
logt('MySQL database connected.');
logt("Running `$argv[2]' task...");

switch ($argv[2])
{
	// Обработчик загрузок
	case 'uploads':
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
			$uid = $task['uid'];
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
						$res = addRow($data, $cmtid, $uid);
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
						$res = addRow($data, $cmtid, $uid);
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
	require_once 'geoext.php';
	require_once 'quadkey.php';

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
				$quadkey = 'NULL';
				$coords = GeoLocateAP(dec2mac($bssid));
				if ($coords != '')
				{
					$found++;
					$coords = explode(';', $coords);
					$latitude = (float)$coords[0];
					$longitude = (float)$coords[1];
					$quadkey = base_convert(latlon_to_quadkey($latitude, $longitude, MAX_ZOOM_LEVEL), 2, 10);
				}
				QuerySql("UPDATE GEO_TABLE SET `latitude`=$latitude,`longitude`=$longitude, `quadkey`=$quadkey WHERE `BSSID`=$bssid");
				if ((microtime(true) - $time > $hangcheck) && ($done < $total))
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
	require_once 'geoext.php';
	// TODO
	break;

	default:
	logt("Error: Unsupported action `$argv[2]'");
	break;
}
$db->close();
?>