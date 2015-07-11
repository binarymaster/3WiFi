<?php
/* Подключаемся к БД */
$db_serv = "localhost";
$db_name = "3wifi_tk";
$db_user = "user_3wifi_tk";
$db_pass = "";

$db = mysqli_connect($db_serv, $db_user, $db_pass, $db_name);
/* проверка подключения */
if ($db->connect_errno) {
    echo "Не удалось подключиться к MySQL: (" . $db->connect_errno . ") " . $db->connect_error;
exit();
}


?>