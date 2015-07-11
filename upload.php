<?php

$uploaddir = '/www/3wifi.tk/www/uploads/';
$uploadfile = $uploaddir . basename($_FILES['userfile']['name']);

if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
	echo "Файл загружен на сервер.<br>\n";
	require 'con_db.php'; /* Коннектор MySQL */
	
	$row = 0;
	$comment=$_POST['comment'];
	if (($handle = fopen($uploadfile, "r")) !== FALSE) {
		$sql="INSERT INTO `$db_name`.`free` (           `comment`, `IP`,       `Port`,    `Authorization`, `name`,   `RadioOff`, `Hidden`, `BSSID`,  `ESSID`,  `Security`, `WiFiKey`,     `WPSPIN`,   `LANIP`,     `LANMask`,   `WANIP`,   `WANMask`,   `WANGateway`,   `DNS`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `comment`=?, `IP`=?, `Port`=?, `Authorization`=?, `name`=?, `RadioOff`=?, `Hidden`=?, `BSSID`=?, `ESSID`=?, `Security`=?, `WiFiKey`=?, `WPSPIN`=?, `LANIP`=?, `LANMask`=?, `WANIP`=?, `WANMask`=?,`WANGateway`=?, `DNS`=?;";
		$stmt = $db->prepare($sql);
	
		while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
			$row++;
			$num = count($data);
			if ($row == 1) {
				if (($data[0]!=="IP Address")or($data[1]!=="Port")or($data[4]!=="Authorization")or($data[5]!=="Server name / Realm name / Device type")or($data[6]!=="Radio Off")or($data[7]!=="Hidden")or($data[8]!=="BSSID")or($data[9]!=="ESSID")or($data[10]!=="Security")or($data[11]!=="Key")or($data[12]!=="WPS PIN")or($data[13]!=="LAN IP Address")or($data[14]!=="LAN Subnet Mask")or($data[15]!=="WAN IP Address")or($data[16]!=="WAN Subnet Mask")or($data[17]!=="WAN Gateway")or($data[18]!=="Domain Name Servers")) {
					echo "Не верный формат файла!<br>\n";
					break;
				};
			};
			if ($row !== 1) {
				$stmt->bind_param("ssssssssssssssssssssssssssssssssssss",     $comment,  $data[0],   $data[1],  $data[4],        $data[5], $data[6],   $data[7], $data[8], $data[9], $data[10],  $data[11], $data[12],   $data[13],  $data[14],   $data[15],  $data[16],  $data[17],      $data[18], $comment, $data[0], $data[1], $data[4], $data[5], $data[6], $data[7], $data[8], $data[9], $data[10], $data[11], $data[12], $data[13], $data[14], $data[15], $data[16], $data[17], $data[18]);
				$stmt->execute();
			};
		};
		fclose($handle);
		$stmt->close();
		
		if ($row > 1) echo "Файл загружен в базу.<br>\n";
		
		if (file_exists($uploadfile)) {
			unlink($uploadfile);
			echo "Временный файл удален.<br>\n";
		} else echo "Временный Файл не найден!<br>\n";
	};

} else {
    echo "Ошибка: Файл не был загружен!<br>\n";
}

require 'chkxy.php';

require 'makemap.php';

echo "Операция завершена.<br>\n";
?>