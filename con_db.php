<?php
function db_connect()
{
	/* Данные входа в БД */
	$db_serv = "localhost";
	$db_name = "3wifi";
	$db_user = "root";
	$db_pass = "";
	global $db;

	$result = false;
	$tries = 3;
	while (!$result && $tries--)
	{
		/* Подключаемся к БД */
		$db = mysqli_connect($db_serv, $db_user, $db_pass, $db_name);

		/* Проверка подключения */
		if ($db->connect_errno)
		{
			sleep(3);
		} else
			$result = true;
	}
	return $result;
}
?>