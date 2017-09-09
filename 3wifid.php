<?php
include 'config.php';
require_once 'utils.php';
require_once 'db.php';

$silent = $argv[1] == 'geolocate' && !empty($argv[2]);

if (!$silent)
{
	Header('Content-Type: text/plain');
	echo "3WiFi Daemon Script\n\n";
}

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
				{
					$str = trim(str_replace("\n", '', $str));
					if (!strlen($str)) continue;
					$aps[$str] = 0;
				}

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

	// Обновление ранее не найденных точек
	case 'recheck':
	$geoquery = 'SELECT `BSSID` FROM GEO_TABLE WHERE `latitude` = 0 AND `longitude` = 0  ORDER BY RAND()';

	// Получение координат для новых добавленных BSSID
	case 'geolocate':
	if (!isset($geoquery))
		$geoquery = 'SELECT `BSSID` FROM GEO_TABLE WHERE `latitude` IS NULL ORDER BY RAND() LIMIT ' . GEO_PORTION;
	require_once 'geoext.php';
	require_once 'quadkey.php';
	if (!empty($argv[2]))
	{
		// geolocation service set, so this is worker
		while($s = fgets(STDIN))
		{
			$s = rtrim($s);
			if ($s == 'quit')
				break;
			if ($s == '')
				continue;
			$coords = GeoLocateAP(dec2mac($s), array($argv[2]));
			echo "$s=$coords\n";
			usleep(10000);
		}
		break;
	}
	$svcs = GetGeolocationServices();
	$desc = array(
		0 => array("pipe", "r"),
		1 => array("pipe", "w"),
	);

	while (true)
	{
		logt('Fetching ' . GEO_PORTION . ' incomplete BSSIDs...');
		$aps = array();
		$res = QuerySql($geoquery);
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
				$aps[] = $row[0];
			}

			$stage = array();
			for ($i = 0; $i < count($aps); $i++)
			{
				$stage[$aps[$i]] = -1;
			}
			// start workers
			$workers = array();
			for ($i = 0; $i < count($svcs); $i++)
			{
				$proc = proc_open('php 3wifid.php geolocate ' . $svcs[$i], $desc, $pipes);
				stream_set_blocking($pipes[1], 0);
				$workers[] = array('proc' => $proc, 'pipes' => $pipes);
			}

			$sequential = false; // send queries to services sequentially, or in parallel
			$finished = false;
			while (!$finished)
			{
				for ($i = 0; $i < count($aps); $i++)
				{
					// send BSSIDs
					if ($stage[$aps[$i]] == -1)
					{
						$s = $aps[$i] . "\n";
						if ($sequential)
						{
							// to first worker
							fwrite($workers[0]['pipes'][0], $s);
						}
						else
						{
							// to all workers
							for ($j = 0; $j < count($workers); $j++)
								fwrite($workers[$j]['pipes'][0], $s);
						}
						$stage[$aps[$i]]++;
					}
				}
				// check workers output
				for ($i = 0; $i < count($workers); $i++)
				{
					if (is_resource($workers[$i]['proc']))
					{
						// worker is alive
						if ($s = fgets($workers[$i]['pipes'][1]))
						{
							// parse response
							$s = rtrim($s);
							if (strpos($s, '=') === false)
								continue;
							$s = explode('=', $s);
							if ($stage[$s[0]] >= count($workers))
								continue;
							if ($s[1] != '')
							{
								// BSSID found
								$stage[$s[0]] = count($workers);

								$done++;
								$found++;
								$bssid = $s[0];
								$coords = explode(';', $s[1]);
								$latitude = (float)$coords[0];
								$longitude = (float)$coords[1];
								$quadkey = base_convert(latlon_to_quadkey($latitude, $longitude, MAX_ZOOM_LEVEL), 2, 10);
								QuerySql("UPDATE GEO_TABLE SET `latitude`=$latitude,`longitude`=$longitude, `quadkey`=$quadkey WHERE `BSSID`=$bssid");
							}
							else
							{
								// BSSID not found
								if ($sequential && $i + 1 < count($workers))
									fwrite($workers[$i + 1]['pipes'][0], $s[0] . "\n");
								$stage[$s[0]]++;
								if ($stage[$s[0]] == count($workers))
								{
									$done++;
									$bssid = $s[0];
									$latitude = 0;
									$longitude = 0;
									$quadkey = 'NULL';
									QuerySql("UPDATE GEO_TABLE SET `latitude`=$latitude,`longitude`=$longitude, `quadkey`=$quadkey WHERE `BSSID`=$bssid");
								}
							}
						}
					}
				}
				usleep(10000);
				$finished = true;
				for ($i = 0; $i < count($aps); $i++)
				{
					if ($stage[$aps[$i]] < count($workers))
					{
						$finished = false;
						break;
					}
				}
				if (!$finished && (microtime(true) - $time > $hangcheck) && ($done < $total))
				{
					logt("Status: $done of $total, $found found on map (Working)");
					$time = microtime(true);
				}
			}
			logt("Status: $done of $total, $found found on map (Done!)");
			// stop workers
			for ($i = 0; $i < count($workers); $i++)
			{
				fwrite($workers[$i]['pipes'][0], "quit\n");
				fclose($workers[$i]['pipes'][0]);
				fclose($workers[$i]['pipes'][1]);
				proc_close($workers[$i]['proc']);
			}
		}
		$res->close();
		sleep(1);
	}
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
