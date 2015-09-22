<html><head>
<title>3WiFi: Добавление точек в базу</title>
<meta http-equiv=Content-Type content="text/html;charset=UTF-8">
</head><body>

<form enctype="multipart/form-data" action="upload.php" method="POST">
    <!-- Поле MAX_FILE_SIZE должно быть указано до поля загрузки файла -->
    <input type="hidden" name="MAX_FILE_SIZE" value="15000000" />
    <!-- Название элемента input определяет имя в массиве $_FILES -->
	<table>
	<tr><td>Отчёт Router Scan:</td><td><input name="userfile" type="file" accept=".csv,.txt" /></td><td>(в формате <b>CSV</b> или <b>TXT</b>)</td></tr>
	<tr><td>Ваш комментарий:</td><td><input name="comment" type="text" <?php if (isset($_POST['comment'])) echo 'value="'.htmlspecialchars($_POST['comment']).'" '; ?>/></td></tr>
	<tr><td>Дополнительно:</td><td><input type="checkbox" name="checkexist" value="1" checked>Не обновлять существующие в базе точки</td></tr>
	<tr><td><input type="submit" value="Отправить файл" /></td><td></td></tr>
	</table>
</form>

<?php
function getExtension($filename)
{
	return substr(strrchr($filename, '.'), 1);
}
function APinDB($bssid, $essid, $key)
{
	global $chkst;
	$chkst->bind_param("sss", $bssid, $essid, $key);
	$chkst->execute();
	$chkst->store_result();
	$result = $chkst->num_rows;
	$chkst->free_result();
	return $result > 0;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && count($_FILES) > 0)
{
	$uploaddir = 'uploads/';
	$filename = basename($_FILES['userfile']['name']);
	$uploadfile = $uploaddir . $filename;

	if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
		echo "Файл <b>".htmlspecialchars($filename)."</b> загружен на сервер.<br>\n";
		require 'con_db.php'; /* Коннектор MySQL */

		$checkexist = isset($_POST['checkexist']) && ($_POST['checkexist'] == '1');
		if ($checkexist)
			$chkst = $db->prepare("SELECT * FROM `free` WHERE `BSSID`=? AND `ESSID`=? AND `WiFiKey`=? LIMIT 1");

		$ext = strtolower(getExtension($filename));
		$format = '';
		if ($ext == 'csv' || $ext == 'txt') $format = $ext;
		if ($format == '')
		{
			$format = 'csv';
			echo "Неизвестное расширение/формат файла, подразумевается CSV.<br>\n";
		}
		$warn = array();
		if (($handle = fopen($uploadfile, "r")) !== FALSE)
		{
			$comment = $_POST['comment'];
			if ($comment=='') $comment='none';

			$sql="INSERT INTO `$db_name`.`free` (`comment`, `IP`, `Port`, `Authorization`, `name`, `RadioOff`, `Hidden`, `BSSID`, `ESSID`, `Security`, `WiFiKey`, `WPSPIN`, `LANIP`, `LANMask`, `WANIP`, `WANMask`, `WANGateway`, `DNS`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `comment`=?, `IP`=?, `Port`=?, `Authorization`=?, `name`=?, `RadioOff`=?, `Hidden`=?, `BSSID`=?, `ESSID`=?, `Security`=?, `WiFiKey`=?, `WPSPIN`=?, `LANIP`=?, `LANMask`=?, `WANIP`=?, `WANMask`=?,`WANGateway`=?, `DNS`=?;";
			$stmt = $db->prepare($sql);

			$row = 0;
			$cnt = 0;
			switch ($format)
			{
				case 'csv':
				while (($data = fgetcsv($handle, 1000, ";")) !== FALSE)
				{
					$row++;
					if ($row == 1)
					{
						if (($data[0]!=="IP Address")or($data[1]!=="Port")or($data[4]!=="Authorization")or($data[5]!=="Server name / Realm name / Device type")or($data[6]!=="Radio Off")or($data[7]!=="Hidden")or($data[8]!=="BSSID")or($data[9]!=="ESSID")or($data[10]!=="Security")or($data[11]!=="Key")or($data[12]!=="WPS PIN")or($data[13]!=="LAN IP Address")or($data[14]!=="LAN Subnet Mask")or($data[15]!=="WAN IP Address")or($data[16]!=="WAN Subnet Mask")or($data[17]!=="WAN Gateway")or($data[18]!=="Domain Name Servers"))
						{
							echo "Неподдерживаемый формат отчёта CSV, заголовки отсутствуют!<br>\n";
							break;
						}
					}
					if ($row !== 1)
					{
						$cnt++;
						// Отбираем только валидные точки доступа
						$bssid = $data[8];
						$essid = $data[9];
						$sec = $data[10];
						$key = $data[11];
						$wps = $data[12];
						if ($bssid == '<no wireless>')
						{
							$warn[$row] = 1;
							continue;
						}
						if ((strpos($bssid, ':') === false || $wps == '')
						&& ($essid == '' || $sec == '' || $sec == '-' || $key == '' || $key == '-'))
						{
							if (strpos($bssid, ':') !== false
							|| $essid != ''
							|| $sec != ''
							|| $key != ''
							|| $wps != '')
							{
								$warn[$row] = 2;
							} else {
								$warn[$row] = 0;
							}
							continue;
						}
						if ($checkexist)
							if (APinDB($bssid, $essid, $key))
							{
								$warn[$row] = 3;
								continue;
							}

						$stmt->bind_param("ssssssssssssssssssssssssssssssssssss", // format
								// INSERT
								//    comment   IP        Port      Auth      Name      RadioOff  Hidden    BSSID     ESSID     Security   Key        WPS PIN    LAN IP     LAN Mask   WAN IP     WAN Mask   WAN Gate   DNS Serv
									$comment, $data[0], $data[1], $data[4], $data[5], $data[6], $data[7], $data[8], $data[9], $data[10], $data[11], $data[12], $data[13], $data[14], $data[15], $data[16], $data[17], $data[18],
								// UPDATE
									$comment, $data[0], $data[1], $data[4], $data[5], $data[6], $data[7], $data[8], $data[9], $data[10], $data[11], $data[12], $data[13], $data[14], $data[15], $data[16], $data[17], $data[18]
						);
						$stmt->execute();
					}
				}
				break;
				case 'txt':
				while (($str = fgets($handle)) !== FALSE)
				{
					$data = explode("\t", $str);
					$row++;
					if ($row == 1)
					{
						if (count($data) != 23)
						{
							echo "Неподдерживаемый формат отчёта TXT, нестандартное количество столбцов!<br>\n";
							break;
						}
					}
					$cnt++;
					// Отбираем только валидные точки доступа
					$bssid = $data[8];
					$essid = $data[9];
					$sec = $data[10];
					$key = $data[11];
					$wps = $data[12];
					if ($bssid == '<no wireless>')
					{
						$warn[$row] = 1;
						continue;
					}
					if ((strpos($bssid, ':') === false || $wps == '')
					&& ($essid == '' || $sec == '' || $sec == '-' || $key == '' || $key == '-'))
					{
						if (strpos($bssid, ':') !== false
						|| $essid != ''
						|| $sec != ''
						|| $key != ''
						|| $wps != '')
						{
							$warn[$row] = 2;
						} else {
							$warn[$row] = 0;
						}
						continue;
					}
					if ($checkexist)
						if (APinDB($bssid, $essid, $key))
						{
							$warn[$row] = 3;
							continue;
						}

					$stmt->bind_param("ssssssssssssssssssssssssssssssssssss", // format
							// INSERT
							//    comment   IP        Port      Auth      Name      RadioOff  Hidden    BSSID     ESSID     Security   Key        WPS PIN    LAN IP     LAN Mask   WAN IP     WAN Mask   WAN Gate   DNS Serv
								$comment, $data[0], $data[1], $data[4], $data[5], $data[6], $data[7], $data[8], $data[9], $data[10], $data[11], $data[12], $data[13], $data[14], $data[15], $data[16], $data[17], $data[18],
							// UPDATE
								$comment, $data[0], $data[1], $data[4], $data[5], $data[6], $data[7], $data[8], $data[9], $data[10], $data[11], $data[12], $data[13], $data[14], $data[15], $data[16], $data[17], $data[18]
					);
					$stmt->execute();
				}
				break;
			}
			if ($checkexist) $chkst->close();
			fclose($handle);
			$stmt->close();
			
			if (count($warn) > 0)
			{
				echo "Следующие записи <b>не были</b> внесены в базу:<br>\n<ul>\n";
				foreach ($warn as $line => $wid)
				{
					echo "<li>Строка $line: ";
					switch ($wid)
					{
						case 0:
						echo 'Нет валидных данных точки доступа';
						break;
						case 1:
						echo 'Устройство не имеет беспроводного адаптера';
						break;
						case 2:
						echo 'Не достаточно полезных данных для внесения в базу';
						break;
						case 3:
						echo 'Данная точка доступа уже есть в базе';
						break;
					}
					echo ".</li>\n";
				}
				echo "</ul>\n";
			}
			if ($row > 1) echo "Файл загружен в базу (".($cnt - count($warn))." из $cnt записей).<br>\n";
			
			if (file_exists($uploadfile))
			{
				unlink($uploadfile);
				echo "Временный файл удален.<br>\n";
			} else die("Ошибка: Временный Файл не найден!<br>\n");
		};

	} else {
		die("Ошибка: Файл не был загружен!<br>\n");
	}

	require 'chkxy.php';

	echo "Операция завершена.<br>\n";
}
?></body></html>