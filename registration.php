<?php
require_once 'auth.php';
require_once 'utils.php';
require_once 'db.php';
global $level, $db, $auth;
if (!db_connect()) {die("Ошибка подключения к БД");} // Подключаемся к БД или умираем смертью храбрых

$action = getParam('a','reg'); // По-умолчанию регистрация

switch ($action)
{
	// РЕГИСТРАЦИЯ НОВОГО ПОЛЬЗОВАТЕЛЯ
	case 'reg':
	if ($login<>'') // Уже авторизован и пытается зарегистрироваться
	{
		echo "<h2 style=\"color:red;\">Вы уже зарегистрированы и авторизованы, $nick.</h2>";
		break;
	}
	
	// Получаем данные формы, если заполнена
	$newLogin = getParam('login');
	$newNick = getParam('nick');
	$newPassword = getParam('password');
	$newInvite = getParam('invite');
	
	// Если форма заполнена,
	// проверяем правильность заполнения
	// и свободен ли логин\ник
	$error = 0; // счетчик ошибок
	
	if (!is_null($newLogin)) { // логин указан
		if (strlen($newLogin)<6) { // логин должен быть больше 5 знаков
			MessageRed("Логин должен содержать 6 и более символов");
			$error++;
		} elseif ($auth->isUserLogin($newLogin)) { // если такой логин уже существует
			MessageRed("Логин $newLogin занят!");
			$error++;
		}
	} else $error++; // логин не указан, не страшно при первом шаге, но нужно указать

	if (!is_null($newNick)) { // ник указан
		if (strlen($newNick)<3) { // логин должен быть больше 6 знаков
			MessageRed("Ник должен содержать 3 и более символов");
			$error++;
		} elseif ($auth->isUserNick($newNick)) { // Ник уже существует
			MessageRed("Ник $newNick занят!");
			$error++;
		}
	} else $error++; // ник не указан, не страшно при первом шаге, но нужно указать
	
	if (!is_null($newPassword)) {// Пароль указан
		if (strlen($newPassword) < 6) { // короткий пароль
			MessageRed("Пароль должен быть не менее 6 символов!");
			$error++;
		}
	} else $error++; // пароль не задан

	if (!is_null($newInvite)) {// Код приглашения указан
		if (!$auth->isValidInvite($newInvite)) { //неверный код
			MessageRed("Код приглашения не действителен!");
			$error++;
		}
	} else $error++; // код не задан
	
	// Если есть ошибки выводим форму регистрации
	// Иначе регистрируем пользователя
	if ($error > 0) {
		$content = file_get_contents('registration.html');
		$content = str_replace('%login%', $newLogin, $content);
		$content = str_replace('%nick%', $newNick, $content);
		$content = str_replace('%invite%', $newInvite, $content);
		$content = str_replace('%action_name%', 'Регистрация', $content);

		echo str_replace('</body>', $incscript.'</body>', $content);
	
	} else { // Регистрируем в базе
		$i=0;
		$newSalt = md5(uniqid(rand(),true)); // Генерируем соль
		$newPassHash = md5($newPassword.$newSalt); // Создаём хэш пароля
		// Записываем в базу нового пользователя
		QuerySql("INSERT INTO `users` (`login`, `nick`, `pass_hash`, `salt`) VALUES ('".quote($newLogin)."', '".quote($newNick)."', '".quote($newPassHash)."', '".quote($newSalt)."');");
		QuerySql("INSERT INTO `users` (`login`, `nick`, `pass_hash`, `salt`) VALUES ('".quote($newLogin)."', '".quote($newNick)."', '".quote($newPassHash)."', '".quote($newSalt)."');");
		// Входим в систему под новым пользователем
		$auth->auth($newLogin,$newPassword);
		$newUid = $auth->getUID(); // Получаем uid нового пользователя
		// Погашаем код приглашения
		QuerySql("UPDATE `invites` SET `uid2`='".quote($newUid)."' WHERE  `invite`='".quote($newInvite)."' AND `uid2` IS NULL LIMIT 1;");
		
		// Регистрация завершена
		MessageRed("Привет, $newNick!"); // Приветствие
		echo '<script type="text/javascript">setTimeout("top.location.reload()", 1000)</script>'; // Обновляем страницу
	}
	break;
	
	// УПРАВЛЕНИЕ ПРИГЛАШЕНИЯМИ
	case 'inv':
	MessageRed('Вы пока не можете приглашать новых пользователей');
	break;
}

$db->close();
?>
