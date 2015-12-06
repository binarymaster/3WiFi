<?php
require_once 'db.php';
global $db;
if (!db_connect()) {die("Ошибка подключения к БД");} 

require_once 'auth.php';
global $login, $level, $nick;


if (isset($_POST["login"]) && isset($_POST["password"])) { //Если логин и пароль были отправлены
	if (!$auth->auth($_POST["login"], $_POST["password"])) { //Если логин и пароль введен не правильно
		echo "<h2 style=\"color:red;\">Логин и пароль введен не правильно!</h2>";
	}
}

if (isset($_GET["a"])) { // Если указано действие
	$action = $_GET["a"]; // Запоминаем его
}else $action = 'login'; // Иначе пробуем войти


if ($action == 'logout') { // Если нужно выйти
	global $auth;
	$auth->out(); // Убираем авторизацию
	echo "<h2 style=\"color:red;\">Выходим...</h2>"; // Сообщение о выходе
	echo '<script type="text/javascript">setTimeout("top.location.reload()", 1000)</script>'; // Редирект после выхода
}elseif ($action == 'login' && $auth->getLevel()>0) { // Если пользователь авторизован  
	echo "<h2 style=\"color:red;\">Здравствуйте, ".$auth->getNick()."</h2>"; // Приветствие
	echo '<script type="text/javascript">setTimeout("top.location.reload()", 1000)</script>'; // Обновляем страницу
}else { //Если не авторизован, показываем форму ввода логина и пароля
	$content = file_get_contents('login.html');
	$content = str_replace('%login%', $login, $content);
	$content = str_replace('%action_name%', 'Войти', $content);
	echo str_replace('</body>', $incscript.'</body>', $content);
}

$db->close();
?>
