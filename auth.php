<?php
require_once 'db.php';
session_start(); // Запускаем сессии

global $magic, $pass_level1, $pass_level2;
$magic = '3wifi-magic-word';
$pass_level1 = 'antichat';
$pass_level2 = 'secret_password';

class AuthClass {
/** 
 * Класс для авторизации
 */ 

	public function setAutoLogin($login) {
	/**
	 * Устанавливает хэш для автоматической авторизации по кукам
	 * Возвращает новый хэш
	 * @return string 
	 */
		$newhash = md5(uniqid(rand(),true));
		global $db;
		QuerySql("UPDATE `users` SET `autologin`='".quote($newhash)."' WHERE  `login`='".quote($login)."';");
		return $newhash;
	}
	
	public function setAutoIP($login,$ip, $salt) {
	/**
	 * Устанавливает хэш ip для автоматической авторизации по кукам
	 * Возвращает новый хэш
	 * @return string 
	 */
		$newhash = md5($ip.$salt);
		global $db;
		QuerySql("UPDATE `users` SET `ip_hash`='.".quote($newhash)."' WHERE  `login`='".quote($login)."';");
		return $newhash;
	}
	
	public function getLevel() {
	/**
	 * Проверяет, авторизован пользователь или нет
	 * Возвращает уровень доступа если авторизован, иначе 0 (гость)
	 * @return int 
	 */
		if (isset($_SESSION["level"])) { //Если сессия существует
			return $_SESSION["level"]; //Возвращаем значение переменной сессии level (хранит уровень доступа, 0 - гость, 1 - пользователь, 2 - админ)
		}
		elseif (isset($_COOKIE["auth"])){
			$auth_cookie = unserialize($_COOKIE["auth"]);
			return $this->auto_auth($auth_cookie["login"], $auth_cookie["auto_login"], $auth_cookie["ip_hash"]);
		}
		else return 0; //Пользователь не авторизован, т.к. переменная level не создана
	}
	
	public function auth($login, $password) {
	/**
	 * Авторизация пользователя по паролю
	 * @param string $login
	 * @param string $passwors 
	 * @return bool 
	 */
		global $db;
		$res = QuerySql("SELECT * FROM users WHERE `login`='$login'");
		
		if ($res->num_rows == 1) // Если логин существует
		{
			$row = $res->fetch_row();
			$uid = $row[0];
			$nick = $row[2];
			$pass_hash = $row[3];
			$auto_login = $row[4];
			$salt = $row[5];
			$level = $row[6];
			
			if (md5($password.$salt) == $pass_hash) { // Если пароль указан правильно
				$_SESSION["uid"] = $uid;
				$_SESSION["login"] = $login;				
				$_SESSION["nick"] = $nick;
				$_SESSION["pass_hash"] = $pass_hash;
				$auto_login = $this->setAutoLogin($login); // Устанавливаем новый хэш автологина
				$_SESSION["auto_login"] = $auto_login;
				$_SESSION["salt"] = $salt;
				$_SESSION["level"] = $level; //Делаем пользователя авторизованным
				$ip_hash = $this->setAutoIP($login,$_SERVER['REMOTE_ADDR'],$salt);
				$auth_cookie=array("login" => $login, "auto_login" => $auto_login, "ip_hash" => $ip_hash);
				setcookie("auth",serialize($auth_cookie),time()+3*24*60*60); // Устанавливаем кук на 3 дня				
				return true;
			}else { // Пароль не подошел
				$_SESSION["level"] = 0;
				return false;
			}
		
		}else {
			$_SESSION["level"] = 0;			
			return false;
		}
		
		$res->close();
	}

	public function auto_auth($login, $hash, $ip_hash_c) {
	/**
	 * Автоматическая авторизация пользователя по кукам
	 * @param string $login
	 * @param string $hash 
	 * @return bool
	 */
		global $db;
		$res = QuerySql("SELECT * FROM users WHERE `login`='".quote($login)."'");
		
		if ($res->num_rows == 1) // Если логин существует
		{
			$row = $res->fetch_row();
			$uid = $row[0];
			$nick = $row[2];
			$pass_hash = $row[3];
			$auto_login = $row[4];
			$salt = $row[5];
			$level = $row[6];
			$ip_hash = $row[7];
			
			if ($hash == $auto_login && $ip_hash == md5($_SERVER['REMOTE_ADDR'].$salt) && $ip_hash == $ip_hash_c) { // Если пароль указан правильно
				$_SESSION["uid"] = $uid;
				$_SESSION["login"] = $login;				
				$_SESSION["nick"] = $nick;
				$_SESSION["pass_hash"] = $pass_hash;
				$auto_login = $this->setAutoLogin($login); // Устанавливаем новый хэш автологина
				$_SESSION["auto_login"] = $auto_login;
				$_SESSION["salt"] = $salt;
				$_SESSION["level"] = $level; //Делаем пользователя авторизованным
				$auth_cookie=array("login" => $login, "auto_login" => $auto_login, "ip_hash" => $ip_hash);
				setcookie("auth",serialize($auth_cookie),time()+time()+3*24*60*60); // Обновляем кук на 3 дня
				return true;
			}else { // Хэш не подошел
				$_SESSION["level"] = 0;
				return false;
			}
		
		}else {
			$_SESSION["level"] = 0;			
			return false;
		}
		
		$res->close();
	}
	
	public function getLogin() {
	/**
	 * Метод возвращает логин авторизованного пользователя 
	 * @return string $login
	 */
		if ($this->getLevel()>0) { //Если пользователь авторизован
			return $_SESSION["login"]; //Возвращаем логин, который записан в сессию
		}else return '';
	}
 
	public function getNick() {
	/**
	 * Метод возвращает ник авторизованного пользователя 
	 * @return string $nick
	 */
		if ($this->getLevel()>0) { //Если пользователь авторизован
			return $_SESSION["nick"]; //Возвращаем ник, который записан в сессию
		}else return '';
	}

	public function getUID() {
	/**
	 * Метод возвращает uid авторизованного пользователя 
	 * @return string $uid
	 */
		if ($this->getLevel()>0) { //Если пользователь авторизован
			return $_SESSION["uid"]; //Возвращаем uid, который записан в сессию
		}else return 'NULL';
	}

	/**
	 * Метод возвращает ник пользователя по его uID
	 * @param string $uid
	 * @return bool $nick
	 */
	public function getUserNick($uid) {
		global $db;
		$res = QuerySql("SELECT `nick` FROM users WHERE `uid`='".quote($uid)."'");
	
		if ($res->num_rows == 1) // Если пользователь существует
		{
			$row = $res->fetch_row();
			$nick = $row[0];
			return $nick;
		} else {
			return false;
		}
		$res->close();
	}

	/**
	 * Метод проверяет существование пользователя по логину
	 * @param string $login
	 * @return bool
	 */
	public function isUserLogin($login) {
		global $db;
		$res = QuerySql("SELECT `uid` FROM users WHERE `login`='".quote($login)."'");
		$result = ($res->num_rows == 1);// Если пользователь существует
		$res->close();
		return $result; 
	}

	public function isUserNick($nick) {
	/**
	 * Метод проверяет существование пользователя по нику
	 * @param string $nick
	 * @return bool
	 */
		global $db;
		$res = QuerySql("SELECT `uid` FROM users WHERE `nick`='".quote($nick)."'");
		$result = ($res->num_rows == 1); // Если пользователь существует
		$res->close();
		return $result;
	}

	public function out() {
	/**
	 * Метод осуществляет выход
	 * @return true
	 */
		$_SESSION = array(); // Очищаем сессию
		session_destroy(); // Уничтожаем
		setcookie("auth","",time()-3600); // Удаляем авто авторизацию
		return true;
	}
	
	public function isValidInvite($invite) {
	/**
	 * Метод проверяет действует ли код приглашения
	 * @param string $invite
	 * @return bool
	 */
		global $db;
		$res = QuerySql("SELECT `id` FROM `invites` WHERE `invite`='".quote($invite)."' AND `uid2` IS NULL LIMIT 1;");
		$result = ($res->num_rows == 1); // Если код существует и действителен
		$res->close();
		return $result;
	}
}

global $auth, $level, $login, $nick, $uid;
$auth = new AuthClass();
$level = $auth->getLevel();
$login = $auth->getLogin();
$nick = $auth->getNick();
$uid = $auth->getUID();

if ($level == 0) {
	$pass = '';
	if (isset($_POST['pass']))
	{
		$pass = $_POST['pass'];
	} else {
		if (isset($_GET['pass']))
		{
			$pass = $_GET['pass'];
		} else {
			if (isset($argv[1]))
				$pass = $argv[1];
		}
	}
	if ($pass == $pass_level1) $level = 1;
	if ($pass == $pass_level2) $level = 2;
}
?>