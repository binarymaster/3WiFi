<?php
require_once 'db.php';

ini_set('session.cookie_lifetime', USER_TIMEOUT + 60*60);
session_start();
if (!isset($_SESSION['SessionCreated']))
{
	$_SESSION['SessionCreated'] = time();
}
else if (time() - $_SESSION['SessionCreated'] > SESSION_TIMEOUT)
{
	session_regenerate_id(true);
	$_SESSION['SessionCreated'] = time();
}

/*
 * Класс авторизации пользователей для сайта 3WiFi
 * @link https://github.com/binarymaster/3WiFi
 */

class User {
/**
 * Класс пользователя
 */

 // Статусы пользователя
	const USER_ADMIN = 3; // Администратор
	const USER_ADVANCED = 2; // Разработчик
	const USER_BASIC = 1; // Пользователь
	const USER_GUEST = 0; // Гостевая учетная запись
	const USER_UNAUTHORIZED = -1; // Не авторизован
	const USER_BAN = -2; // Забанен

	const LOG_AUTHORIZATION = 1;
	const LOG_LOGOUT = 2;
	const LOG_REGISTRATION = 3;
	const LOG_CREATEINVITE = 4;
	const LOG_DELETEINVITE = 5;
	const LOG_GET_RAPIKEY = 6;
	const LOG_GET_WAPIKEY = 7;
	const LOG_CREATE_RAPIKEY = 8;
	const LOG_CREATE_WAPIKEY = 9;
	const LOG_AUTHORIZATION_DATAONLY = 10;
	const LOG_GET_APIKEYS = 11;

	public $uID = NULL;
	public $puID = NULL;
	public $InviterNickName = NULL;
	public $Login = '';
	public $Nick = '';
	public $RegDate = NULL;
	public $HashPass = NULL;
	public $HashKey = NULL;
	public $Salt = NULL;
	public $Level = self::USER_UNAUTHORIZED;
	public $HashIP = NULL;
	public $invites = 0;
	public $ReadApiKey = 'N/A';
	public $WriteApiKey = 'N/A';
	public $ApiAccess = '';

	public $LastUpdate = 0;
	public $LastError = '';

	private static $mysqli;

	function __construct($db=NULL)
	{
		global $db;

		if (is_null($db))
		{
			db_connect();
		}
		self::$mysqli = $db;
	}

	function __destruct() {
		if (!is_null(self::$mysqli)) self::$mysqli->close();
	}

	private function quote($var) {
		return self::$mysqli->real_escape_string($var);
	}

	public function newHashKey() {
	/**
	 * Генерирует новый хэш-ключ (Нужен для автоматической авторизации по кукам)
	 * @return string
	 */
		return md5(uniqid(rand(),true));
	}

	public function newHashIP($Salt = null, $IP=-1) {
	/**
	 * Возвращает правильный хэш-ip для указанного IP и Соли (Нужно для автоматической авторизации по кукам)
	 * @param string [$Salt] (без параметра - соль пользователя, либо указанная соль)
	 * @param string [$IP] (без параметра - IP хоста, NULL - IP пользователя, либо указанный IP)
	 * @return string
	 */
		$Salt = (is_null($Salt) ? $this->Salt : $Salt);
		$IP =  (is_null($IP) ? $this->IP : ($IP===-1 ? $_SERVER['REMOTE_ADDR'] : $IP) );
		return md5(md5($IP).$Salt);
	}

	public function saveDB() {
	/**
	 * Сохраняет состояние в БД
	 * @return int uID
	 */
		if (is_null($this->uID))
		{ // uID = NULL - новый пользователь, добавляем
			$sql = "INSERT INTO `users` (`puid`, `login`, `nick`, `pass_hash`, `autologin`, `salt`, `level`, `ip_hash`, `invites`) 
					VALUES (".$this->puID.", '{$this->quote($this->Login)}', '{$this->quote($this->Nick)}', '{$this->quote($this->HashPass)}', '{$this->quote($this->HashKey)}', '{$this->quote($this->Salt)}', ".(int)$this->Level.", '{$this->quote($this->HashIP)}', {$this->quote($this->invites)})";
		}
		else
		{
			// обновляем пользователя с указанным uID
			$sql = "UPDATE `users` SET `puid`=".$this->puID.",`login`='{$this->quote($this->Login)}', `nick`='{$this->quote($this->Nick)}', `pass_hash`='{$this->quote($this->HashPass)}', `autologin`='{$this->quote($this->HashKey)}', `salt`='{$this->quote($this->Salt)}', `level`=".(int)$this->Level.", `ip_hash`='{$this->quote($this->HashIP)}', `invites`={$this->quote($this->invites)}
			WHERE  `uID`={$this->quote($this->uID)}"; // Для совместимости получим его же uID обратно
		}
		$res = self::$mysqli->query($sql);

		if (!isset($this->uID))
		{
			$this->uID = (($row = $res->insert_id) ? (int)$res->insert_id : null); // Запоминаем полученный uID
		}
		return $this->uID; // И возвращаем uID обратно
	}

	public function loadDB($uID = NULL) {
	/**
	 * Загружает пользователя из БД
	 * Параметр uID указывает на то, что нам необходимо загрузить конкретного пользователя
	 * Отсутствие параметра сообщает, что в $this->Login $this->HashKey $user->HashIP загружены
	 * данные из кук, которые надо проверить на валидность.
	 * Результат true означает, что пользователь существует, загружен.
	 * Результат false говорит что пользователь не существует, либо авторизация не удалась.
	 * @param int [uID]
	 * @return bool $result
	 */

		if (is_null($uID)) { // если uID не указан, ищем по Login:HashKey:HashIP
			$sql = "SELECT * FROM `users` WHERE `Login`={$this->quote($Login)} AND `HashKey`='{$this->quote($HashKey)}' AND `HashIP`='{$this->quote($HashIP)}' LIMIT 1;";
			$result = false; // Пользователь даказывает верность загрузки
		} else {
			$sql = "SELECT * FROM `users` WHERE `uID`={$this->quote($uID)} LIMIT 1";
			$result = true; // Сервер доказывает верность загрузки;
		}

		$res = self::$mysqli->query($sql);

		if ($res->num_rows == 1) // Такой пользователь существует
		{
			$row = $res->fetch_assoc();
			$this->uID		= (int)$uID;
			$this->puID		= (int)$row['puid'];
			$this->Login	=      $row['login'];
			$this->Nick		=      $row['nick'];
			$this->RegDate	=      $row['regdate'];
			$this->LastUpdate = (int)$row['lastupdate'];
			$this->HashPass	=      $row['pass_hash'];
			$this->HashKey	=      $row['autologin'];
			$this->Salt		=      $row['salt'];
			$this->Level	= (int)$row['level'];
			$this->HashIP	=      $row['ip_hash'];
			$this->invites	= (int)$row['invites'];
			$this->ReadApiKey	=  $row['rapikey'];
			$this->WriteApiKey	=  $row['wapikey'];

			// 1. Вне зависимости от успеха загрузки проверим авторизацию.
			// 2. Вне зависимости от успеха авторизации, если запрашивали
			// конкретного пользователя, вернем успешную загрузку.
			$result = $result OR (  $this->HashIP == md5(md5($_SERVER['REMOTE_ADDR']).$this->Salt )  );
		}
		else $result = false; // Пользователь не найден пользователь, а значит и авторизация не удалась.

		$res->close();

		return $result;
	}

	public function saveSession() {
	/**
	 * Сохраняет состояние в сессию
	 * @return bool true
	 */
		$_SESSION['uID'] 		= $this->uID;
		$_SESSION['puID'] 		= $this->puID;
		$_SESSION['InviterNickName'] = $this->InviterNickName;
		$_SESSION['Login'] 		= $this->Login;
		$_SESSION['Nick'] 		= $this->Nick;
		$_SESSION['RegDate'] = $this->RegDate;
		$_SESSION['HashPass'] 	= $this->HashPass;
		$_SESSION['HashKey'] 	= $this->HashKey;
		$_SESSION['Salt'] 		= $this->Salt;
		$_SESSION['HashIP'] 	= $this->HashIP;
		$_SESSION['invites'] 	= $this->invites;
		$_SESSION['ReadApiKey'] 	= $this->ReadApiKey;
		$_SESSION['WriteApiKey'] 	= $this->WriteApiKey;
		$_SESSION['LastActivity'] = time();

		return true;
	}

	public function loadSession() {
	/**
	 * Загружает пользователя из сессии
	 * @return bool $result
	 */
		if (isset($_SESSION['uID'])) // сессия открыта
		{
			if (!isset($_SESSION['LastActivity']))
			{
				$_SESSION['LastActivity'] = 0;
			}
			$this->uID = $_SESSION['uID'];

			$res = self::$mysqli->query('SELECT level FROM users WHERE uid='.(int)$this->uID);
			if (self::$mysqli->errno != 0)
			{
				$this->LastError = 'database';
				return false;
			}
			if ($res->num_rows == 0)
			{
				$this->LastError = 'database';
				return false;
			}
			$data = $res->fetch_assoc();
			$res->close();
			$this->Level = (int)$data['level'];

			$timeout = ($this->Level < self::USER_BASIC ? GUEST_TIMEOUT : USER_TIMEOUT);
			if (time() - $_SESSION['LastActivity'] > $timeout)
			{
				$this->out();
				return false;
			}
			$this->puID 	  = $_SESSION['puID'];
			$this->InviterNickName = $_SESSION['InviterNickName'];
			$this->Login	  = $_SESSION['Login'];
			$this->Nick 	  = $_SESSION['Nick'];
			$this->RegDate 	  = $_SESSION['RegDate'];
			$this->HashPass	  = $_SESSION['HashPass'];
			$this->HashKey 	  = $_SESSION['HashKey'];
			$this->Salt		  = $_SESSION['Salt'];
			$this->HashIP 	  = $_SESSION['HashIP'];
			$this->invites    = $_SESSION['invites'];
			$this->ReadApiKey  = $_SESSION['ReadApiKey'];
			$this->WriteApiKey = $_SESSION['WriteApiKey'];
			$_SESSION['LastActivity'] = time();
			return true;
		}
		else return false;
	}

	public function saveCookies() {
	/**
	 * Сохраняет состояние в куки
	 * @return bool true
	 */
		$Cookie=array(	'Login' 	=> $this->Login,
						'HashKey'	=> $this->HashKey,
						'HashIP'	=> $this->HashIP	);

		setcookie('Auth', json_encode($Cookie), time()+3*24*60*60); // Устанавливаем куки на 3 дня

		return true;
	}

	public function loadCookies() {
	/**
	 * Загружает пользователя из куки
	 * @return bool $result
	 */
		if (isset($_COOKIE['Auth'])) // Есть кука
		{
			$Cookie = json_decode($_COOKIE['Auth'], true); // получаем куку
			$this->Login	= $Cookie['Login'];
			$this->HashKey	= $Cookie['HashKey'];
			$this->HashIP	= $Cookie['HashIP'];
			return true;
		} else return false;
	}

	public function save() {
	/**
	 * Сохраняет состояние авторизации
	 * В БД, сессии и куках
	 * @return bool true
	 */
		$this->saveDB(); // сохраним изменения в базе
		$this->saveSession(); // сохраним сессию
		//$this->saveCookies(); // сохраним куки

		return true;
	}

	public function load() {
	/**
	 * Восстанавливает состояние авторизации
	 * из БД, сессии и кук
	 * @return bool true
	 */
		if (!$this->loadSession())
		{
			return false;
		}
		$this->isActual();
		/*
		// Загрузили через сохраненную сессию
		elseif ($this->loadCookies())  // Пытаемся загрузить куки
		{							// Куки есть, загружены.
			return $this->loadDB(); // Загружаем остальное из базы и авторизуемся.
		}
		else {return false;} // ни сессии, ни кук - не авторизован
		*/
		return true;
	}

	public function GenerateRandomString($size=32, $syms = true)
	{
		$Str = '';

		$arr = array('a','b','c','d','e','f',
                 'g','h','i','j','k','l',
                 'm','n','o','p','r','s',
                 't','u','v','x','y','z',
                 'A','B','C','D','E','F',
                 'G','H','I','J','K','L',
                 'M','N','O','P','R','S',
                 'T','U','V','X','Y','Z',
                 '1','2','3','4','5','6',
                 '7','8','9','0');
		$symb_arr = Array( '!', '@',
				 '#', '$', '%', '^', '&',
				 '*', '(', ')', '-', '_',
				 '+', '=', '|', '.', ',');

		if ($syms) $arr = array_merge($arr, $symb_arr);

		for ($i = 0; $i < $size; $i++)
		{
			$Str .= $arr[rand(0, sizeof($arr) - 1)];
		}
		return $Str;
	}

	public function isActual($fix=true)
	{
		if ($this->uID == NULL) return false;
		if (is_null(self::$mysqli)) return false;

		$sql = 'SELECT UNIX_TIMESTAMP(lastupdate) FROM users WHERE uid='.$this->uID;
		$res = self::$mysqli->query($sql);

		if ($res->num_rows != 1) return false;

		$row = $res->fetch_row();
		$res->close();
		if ($this->LastUpdate < $row[0])
		{
			if (!$fix) return false;

			$this->loadDB($this->uID);
			$this->saveSession(); // сохраним сессию
			//$this->saveCookies(); // сохраним куки
		}
		return true;
	}

	public function checkQueryTime()
	{
		if (GUEST_WAIT <= 0) return true;
		if ($this->uID == NULL) return true;
		if ($this->Level > self::USER_GUEST) return true;
		if (is_null(self::$mysqli)) return true;

		$sql = 'SELECT NOW() - querytime FROM users WHERE uid='.$this->uID;
		$res = self::$mysqli->query($sql);

		if ($res->num_rows != 1) return true;

		$row = $res->fetch_row();
		$res->close();
		return ($row[0] >= GUEST_WAIT);
	}

	public function updateQueryTime()
	{
		if ($this->uID == NULL) return false;
		if (is_null(self::$mysqli)) return false;

		return (self::$mysqli->query('UPDATE users SET querytime=NOW() WHERE uid='.$this->uID) ? true : false);
	}

	public function setUser($uID=NULL, $puID=0, $Login='', $Nick='', $HashPass=NULL, $HashKey=NULL, $Salt=NULL, $Level=self::USER_UNAUTHORIZED, $HashIP=NULL, $invites=0)
	{
	/**
	 * Запоминает указанные данные
	 * @params [User]
	 * @return bool true
	 */
		$this->uID = $uID;
		$this->puID = $puID;
		$this->InviterNickName = $this->getUserNameById($puID);
		$this->Login = $Login;
		$this->Nick = $Nick;
		$this->HashPass = $HashPass;
		$this->HashKey = $HashKey;
		$this->Salt = $Salt;
		$this->Level = $Level;
		$this->HashIP = $HashIP;
		$this->invites = $invites;
	}

	public function changeNick($NewNick)
	{
		if (is_null($this->uID))
		{
			return false;
		}

		$this->Nick = $NewNick;
		$this->save();
		return true;
	}

	public function changePass($NewPass)
	{
		if (is_null($this->uID))
		{
			return false;
		}

		$Salt = $this->GenerateRandomString(32);

		$this->Salt = $Salt;
		$this->HashPass = md5($NewPass.$Salt);
		$this->HashKey = $this->newHashKey();
		$this->HashIP = $this->newHashIP();

		$this->save();
		return true;
	}

	public function resetPass($login)
	{
		$salt = $this->GenerateRandomString(32);
		$pass = $this->GenerateRandomString(10, false);
		$hash = md5($pass.$salt);

		return (self::$mysqli->query("UPDATE users SET pass_hash='$hash',salt='{$this->quote($salt)}' WHERE login='{$this->quote($login)}'") ? $pass : false);
	}

	public function Registration($Login, $Nick, $Password, $Invite)
	{
		$Salt = $this->GenerateRandomString(32);

		$InviteInfo = $this->getInviteInfo($Invite);
		if ($InviteInfo['uid'] != NULL) return false;

		$ParentUser = $this->getUserInfo($InviteInfo['puid']);
		$Invites = 0;
		if ($ParentUser['level'] >= self::USER_ADVANCED)
		{
			switch($InviteInfo['level'])
			{
				case self::USER_BASIC:
					$Invites = 3;
					break;
				case self::USER_ADVANCED:
					$Invites = 10;
					break;
			}
		}
		if ($ParentUser['level'] == self::USER_BAN && !ALLOW_BANNED_INVITE_REG)
			$InviteInfo['level'] = self::USER_BAN;
		$this->setUser(NULL, $InviteInfo['puid'], $Login, $Nick, md5($Password.$Salt), '', $Salt, (int)$InviteInfo['level'], NULL, $Invites);
		$this->save();
		if ($this->Auth($Password, $Login))
		{
			$sql = "UPDATE invites SET uid=".$this->uID." WHERE invite='".self::quote($Invite)."'";
			$res = self::$mysqli->query($sql);
			self::$mysqli->query('UPDATE users SET regdate=NOW() WHERE uid='.$this->uID);
			if ($this->Level == self::USER_BAN && !ALLOW_BANNED_INVITE_REG)
				self::$mysqli->query("UPDATE users SET ban_reason='inherit' WHERE uid=".$this->uID);
			$this->eventLog(self::LOG_REGISTRATION, 1, 'Invite: '.$Invite);
			return true;
		}
		return false;
	}

	public function Auth($password, $login = NULL, $getDataOnly=false) {
	/**
	 * Авторизует пользователя по паролю:[логину],
	 * возвращает удалось ли авторизоваться
	 * @param string $password
	 * @param string [$login]
	 * @return bool

	 */

		$result = false;
		$login = ( is_null($login) ? $this->Login : $login );
		$res = self::$mysqli->query("SELECT * FROM `users` WHERE `login`='{$this->quote($login)}' LIMIT 1");
		if ($res->num_rows == 1) // Если логин существует
		{
			$row = $res->fetch_assoc();
			if (md5($password.$row['salt']) == $row['pass_hash'])
			{
				$this->loadDB($row['uid']);
				$this->setUser($row['uid'], $row['puid'], $login, $row['nick'], $row['pass_hash'], $this->newHashKey(), $row['salt'], $row['level'], $this->newHashIP(), $row['invites']);
				$this->RegDate = $row['regdate'];
				if (!$getDataOnly)
				{
					$this->save();
					$this->eventLog(self::LOG_AUTHORIZATION, 1);
				}
				else
				{
					$this->eventLog(self::LOG_AUTHORIZATION_DATAONLY, 1);
				}
				$result = true;
			}
		}
		else
		{ // Логин не существует, авторизация провалилась
			$this->eventLog(self::LOG_AUTHORIZATION, 0);
		}
		$res->close();
		return $result;
	}

	/**
	 * Метод проверяет существование пользователя по логину
	 * @param string $login
	 * @return bool
	 */
	public function isUserLogin($login)
	{
		$res = self::$mysqli->query("SELECT `uid` FROM `users` WHERE `Login`='{$this->quote($login)}'");
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
		$res = self::$mysqli->query("SELECT `uid` FROM `users` WHERE `Nick`='{$this->quote($nick)}'");
		$result = ($res->num_rows == 1);// Если пользователь существует
		$res->close();
		return $result;
	}

	public function getUserNameById($uID)
	{
		$res = self::$mysqli->query('SELECT `nick` FROM `users` WHERE `uid`='.$uID);
		if ($res->num_rows < 1)
		{
			return false;
		}
		$row = $res->fetch_assoc();
		$res->close();

		return $row['nick'];
	}

	public function out() {
	/**
	 * Метод осуществляет выход пользователя
	 * @return bool true
	 */
		$_SESSION = array(); // Очищаем сессию
		session_destroy(); // Уничтожаем
		//setcookie('Auth', '', time()-3600); // Удаляем авто авторизацию
		$this->eventLog(self::LOG_LOGOUT, 1);
		return true;
	}

	public function isValidInvite($invite) {
	/**
	 * Метод проверяет действует ли код приглашения
	 * @param string $invite
	 * @return bool
	 */
		$ban = self::USER_BAN;
		if (INVITE_VALID_FROM_BANNED)
			$sql = "SELECT invite FROM invites WHERE invite='{$this->quote($invite)}' AND uid IS NULL LIMIT 1;";
		else
			$sql = "SELECT invite FROM invites JOIN users ON invites.puid = users.uid WHERE invite='{$this->quote($invite)}' AND invites.uid IS NULL AND users.level != $ban LIMIT 1;";
		$res = self::$mysqli->query($sql);
		$result = ($res->num_rows == 1);
		$res->close();
		return $result;
	}

	public function getInviteInfo($invite) {

		$ban = self::USER_BAN;
		if (INVITE_VALID_FROM_BANNED)
			$sql = "SELECT * FROM invites WHERE invite='{$this->quote($invite)}' LIMIT 1;";
		else
			$sql = "SELECT * FROM invites JOIN users ON invites.puid = users.uid WHERE invite='{$this->quote($invite)}' AND users.level != $ban LIMIT 1;";
		$res = self::$mysqli->query($sql);
		if (!$res) return false;
		$result = $res->fetch_assoc();
		$res->close();
		return $result;
	}
	public function getUserInfo($uid) {

		$res = self::$mysqli->query('SELECT * FROM users WHERE `uid`='.$uid.' LIMIT 1');
		if (!$res) return false;

		$result = $res->fetch_assoc();
		$res->close();
		return $result;
	}

	public function listInvites($uid = null) {
	/**
	 * Метод возвращает список активных и использованных приглашений пользователя
	 * @param int $uid
	 * @return array $Invites
	 */
		if (is_null($uid)) $uid = $this->uID;
		if ($uid == NULL) return false;

		$sql = 'SELECT time, users.regdate, invite, nick, IF(users.level IS NULL, invites.level, users.level) AS level FROM invites LEFT JOIN users USING(`uid`) WHERE invites.puid='.$this->quote($uid).' ORDER BY time';
		$res = self::$mysqli->query($sql);
		for ($result=array();$row=$res->fetch_assoc();$row['level']=(int)$row['level'],$result[]=$row);
		$res->close();
		return $result;
	}

	public function createInvite($level = self::USER_BASIC, $uid = NULL)
	{
		if (is_null($uid)) $uid = $this->uID;
		if ($uid == NULL) return false;

		// Если не осталось инвайтов (и не админ)
		if ($this->invites <= 0 && $this->Level < self::USER_ADMIN)
		{
			$this->LastError = 'limit';
			return false;
		}
		// Только админ может пригласить пользователя с нестандартным уровнем
		if ($this->Level < self::USER_ADMIN && $level != self::USER_BASIC)
		{
			$this->LastError = 'lowlevel';
			return false;
		}
		// Нельзя создавать инвайты с отрицательным уровнем
		if ($level < self::USER_GUEST)
		{
			$this->LastError = 'form';
			return false;
		}

		$invite = $this->GenerateRandomString(12, false);

		$res = self::$mysqli->query("INSERT INTO invites SET invite='$invite', puid=$uid, level=$level");
		if (self::$mysqli->errno != 0)
		{
			$this->LastError = 'database';
			return false;
		}
		if ($this->Level < self::USER_ADMIN) $res = self::$mysqli->query("UPDATE users SET invites=invites-1 WHERE uid=$uid");
		$this->eventLog(self::LOG_CREATEINVITE, 1, $invite);
		return true;
	}

	public function updateInvite($invite, $level)
	{
		$uid = $this->uID;
		if ($uid == null) return false;

		if ($this->Level < self::USER_ADMIN && $level != self::USER_BASIC)
		{
			$this->LastError = 'lowlevel';
			return false;
		}
		if ($level < self::USER_GUEST)
		{
			$this->LastError = 'form';
			return false;
		}

		$res = self::$mysqli->query("SELECT invite, uid FROM invites WHERE puid=$uid AND invite='".self::quote($invite)."'");
		if (self::$mysqli->errno != 0)
		{
			$this->LastError = 'database';
			return false;
		}
		$row = $res->fetch_row();
		$invite = $row[0];
		$uid = $row[1];
		$res->close();
		if ($invite == null)
		{
			$this->LastError = 'form';
			return false;
		}

		self::$mysqli->query("UPDATE invites SET level=$level WHERE invite='".self::quote($invite)."'");
		if (self::$mysqli->errno != 0)
		{
			$this->LastError = 'database';
			return false;
		}
		if ($uid != null && $this->Level == self::USER_ADMIN)
		{
			self::$mysqli->query("UPDATE users SET level=$level WHERE uid=$uid");
			if (self::$mysqli->errno != 0)
			{
				$this->LastError = 'database';
				return false;
			}
		}
		return true;
	}

	public function deleteInvite($invite)
	{
		$uid = $this->uID;
		if ($uid == NULL) return false;

		$res = self::$mysqli->query("DELETE FROM invites WHERE puid=$uid AND uid IS NULL AND invite='".self::quote($invite)."'");
		if (self::$mysqli->errno != 0)
		{
			$this->LastError = 'database';
			return false;
		}
		if (self::$mysqli->affected_rows == 0)
		{
			$this->LastError = 'login';
			return false;
		}
		if ($this->Level < self::USER_ADMIN) $res = self::$mysqli->query("UPDATE users SET invites=invites+1 WHERE uid=$uid");
		$this->eventLog(self::LOG_DELETEINVITE, 1, $invite);
		return true;
	}

	public function getApiKeys()
	{
		$uid = $this->uID;
		if ($uid == NULL) return false;
		if ($this->Level < self::USER_GUEST)
		{
			$this->LastError = 'lowlevel';
			return false;
		}

		$res = self::$mysqli->query('SELECT rapikey, wapikey FROM users WHERE uid='.(int)$uid);
		if (self::$mysqli->errno != 0)
		{
			$this->LastError = 'database';
			return false;
		}
		if ($res->num_rows == 0)
		{
			$this->LastError = 'database';
			return false;
		}
		$data = $res->fetch_assoc();
		$res->close();
		$this->eventLog(self::LOG_GET_APIKEYS, 1, '');
		return $data;
	}

	public function createApiKey($type)
	{
		$uid = $this->uID;
		if ($uid == NULL) return false;
		if ($type == NULL)
		{
			$this->LastError = 'form';
			return false;
		}
		if ($this->Level < self::USER_BASIC)
		{
			$this->LastError = 'lowlevel';
			return false;
		}

		$ApiKey = $this->GenerateRandomString(32, false);
		switch($type)
		{
			case 1:
				$sql = 'UPDATE users SET rapikey="'.$ApiKey.'" WHERE uid='.(int)$uid;
				break;
			case 2:
				$sql = 'UPDATE users SET wapikey="'.$ApiKey.'" WHERE uid='.(int)$uid;
				break;
		}

		$res = self::$mysqli->query($sql);
		if (self::$mysqli->errno != 0)
		{
			$this->LastError = 'database';
			return false;
		}
		$this->eventLog((8+$type-1), 1, $ApiKey);
		return $ApiKey;
	}

	public function eventLog($Action, $Status, $Data='')
	{
		$IP = $_SERVER['REMOTE_ADDR'];
		$IP = _ip2long($IP);
		if ($IP === false) return;

		$sql = 'INSERT INTO logauth SET IP='.$IP.', uid='.$this->uID.',action='.$Action.', data="'.$Data.'", status='.$Status;
		self::$mysqli->query($sql);
	}

	public function isLogged()
	{
		return !is_null($this->uID);
	}

	public function genToken()
	{
		$_SESSION['token'] = randomStr(8, false);
		return $_SESSION['token'];
	}

	public function checkToken($token)
	{
		return (is_string($token) && $token != '' && $token == $_SESSION['token']);
	}

	public function AuthByApiKey($ApiKey, $loadData=false)
	{
		$ApiKey = self::quote($ApiKey);
		$sql = "SELECT uid, IF(rapikey = '$ApiKey', 'read', IF(wapikey = '$ApiKey', 'write', NULL)) AS access FROM users WHERE rapikey = '$ApiKey' OR wapikey = '$ApiKey'";
		$res = self::$mysqli->query($sql);

		if ($res->num_rows != 1)
		{
			$this->LastError = 'unauthorized';
			return false;
		}
		$row = $res->fetch_assoc();
		$res->close();
		$this->ApiAccess = $row['access'];
		if ($loadData)
		{
			$this->loadDB($row['uid']);
		}
		return true;
	}
}
