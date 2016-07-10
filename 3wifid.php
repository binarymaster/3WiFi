<?php
include 'config.php';
require_once 'utils.php';
require_once 'db.php';

Header('Content-Type: text/plain');
echo "3WiFi Daemon Script\n\n";

if ($argv[0] != basename(__FILE__))
{
	die('This is CLI script. Use it with php-cli.');
}
if (count($argv) < 2)
{
	echo "USAGE:\n";
	echo "$argv[0] <action>\n";
	exit();
}
set_time_limit(0);

while (!db_connect())
{
	logt('Error: MySQL connection failed. Retrying in 5 seconds...');
	sleep(5);
}
logt('MySQL database connected.');
logt("Running `$argv[1]' task...");

switch ($argv[1])
{
	// Обработчик загрузок
	case 'uploads':
	while (true)
	{
		logt('Cleaning up old tasks... (tstate = 0)');
		$db->query('UPDATE tasks SET tstate=1,checkexist=1,nowait=1 WHERE tstate=0 AND modified < NOW() - INTERVAL 12 HOUR');
		logt('Fetching a task... (tstate = 1)');
		$res = $db->query('SELECT `tid` FROM tasks WHERE `tstate`=1 ORDER BY `created` LIMIT 1');
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
			$cntp = 0;
			$cnta = 0;
			$aps = array();
			if (($handle = fopen($filename, 'r')) !== false)
			{
				$cmtid = getCommentId($task['comment'], true);
				$time = microtime(true);
				switch ($ext)
				{
					case '.csv':
					while (($data = fgetcsv($handle, 1000, ';')) !== false)
					{
						$cntp++;
						if ($cntp == 1) continue; // Пропуск заголовка CSV
						$res = db_add_ap($data, $cmtid, $uid);
						($res == 0 ? $cnta++ : $warn[$cntp - 1] = $res);
						if (microtime(true) - $time > $hangcheck)
						{
							logt("Status: $cntp processed, $cnta added (Working)");
							$db->query("UPDATE tasks SET `lines`=$cntp,`accepted`=$cnta WHERE `tid`='$tid'");
							$time = microtime(true);
						}
					}
					$cntp--;
					break;
					case '.txt':
					while (($str = fgets($handle)) !== false)
					{
						$data = explode("\t", $str);
						$cntp++;
						$res = db_add_ap($data, $cmtid, $uid);
						($res == 0 ? $cnta++ : $warn[$cntp] = $res);
						if (microtime(true) - $time > $hangcheck)
						{
							logt("Status: $cntp processed, $cnta added (Working)");
							$db->query("UPDATE tasks SET `lines`=$cntp,`accepted`=$cnta WHERE `tid`='$tid'");
							$time = microtime(true);
						}
					}
					break;
				}
				fclose($handle);
			}
			logt("Status: $cntp processed, $cnta added (Done!)");
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
				$db->query("UPDATE tasks SET `lines`=$cntp,`accepted`=$cnta,`warns`='$warns',`tstate`=2 WHERE `tid`='$tid'");
				logt("Task processing complete.");
			}
			else
			{
				$db->query("DELETE FROM tasks WHERE `tid`='$tid'");
				logt("Task processing in `nowait' mode complete.");
			}
			unset($aps);
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
		$db->query("DELETE FROM tasks WHERE `tstate`=3 AND `modified` < DATE_SUB(NOW(), INTERVAL 1 MINUTE)");

		logt('Fetching a task... (tstate = 2)');
		$res = $db->query('SELECT `tid` FROM tasks WHERE `tstate`=2 ORDER BY `created` LIMIT 1');
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
					$db->query("UPDATE tasks SET `onmap`=$found WHERE `tid`='$tid'");
					$time = microtime(true);
				}
				sleep(2);
			}
			logt('Removing temporary file...');
			unlink($filename);
			logt('Status: '.$apschk[1].' found, '.$apschk[-1].' not found (Done!)');

			logt("Set tstate = 3 (complete)");
			$db->query("UPDATE tasks SET `onmap`=$found,`tstate`=3 WHERE `tid`='$tid'");
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

	// Обслуживание таблиц в памяти
	case 'memory':
	while (true)
	{
		if(!TRY_USE_MEMORY_TABLES)
		{
			logt('Memory tables are not used');
			continue;
		}
		$DataBaseStatus = GetStatsValue(STATS_DATABASE_STATUS);
		if($DataBaseStatus == -1) // Service table not initialized
		{
			SetStatsValue(STATS_DATABASE_STATUS, DATABASE_PREPARE, true);
		}
		MemoryDataBaseInit();
		if($DataBaseStatus != DATABASE_ACTIVE)
		{
			SetStatsValue(STATS_DATABASE_STATUS, DATABASE_ACTIVE, true);
		}
		sleep(10);
	}
	break;

	default:
	logt("Error: Unsupported action `$argv[1]'");
	break;
}
$db->close();
?>