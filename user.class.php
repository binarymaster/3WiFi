<?php
/*
 * Класс авторизации пользователей для сайта 3WiFi
 * @link https://github.com/binarymaster/3WiFi 
 */

$magic = '3wifi-magic-word';
$pass_level1 = 'antichat';
$pass_level2 = 'secret_password';
global $magic, $pass_level1, $pass_level2;

class User {
/** 
 * Класс пользователя
 */ 

 // Статусы пользователя
	const USER_ADMIN = 2; // Администратор
	const USER_ACTIVE = 1; // Пользователь
	const USER_INACTIVE = 0; // Не активирован
	const USER_UNAUTHORIZED = NULL; // Не авторизован
	const USER_BAN = -1; // Забанен

	public $Level = USER_UNAUTHORIZED;
	public $Login = '';
	public $HashPass = NULL;
	public $Nick = '';
	public $uID = NULL;
	public $HashKey = NULL;
	public $IP = NULL;
	public $HashIP = NULL;
	public $Salt = NULL;
	
	function __construct ($db) {
	/**
	 * Создает объект user подключенный к БД
	 * @param msqli $db
	 */
		$this->db = $db;
	}
	
	private function quote($var) {
		return $this->db->real_escape_string($var);
	}

	public function newHashKey() {
	/**
	 * Генерирует новый хэш-ключ (Нужен для автоматической авторизации по кукам)
	 * @return string 
	 */
		return = md5(uniqid(rand(),true));
	}
	
	public function newHashIP($Salt = null, $IP = $_SERVER['REMOTE_ADDR']) {
	/**
	 * Возвращает правильный хэш-ip для указанного IP и Соли (Нужно для автоматической авторизации по кукам)
	 * @param string [$Salt] (без параметра - соль пользователя, либо указанная соль)
	 * @param string [$IP] (без параметра - IP хоста, NULL - IP пользователя, либо указанный IP)
	 * @return string 
	 */
		$Salt = (is_null($Salt) ? $this->Salt : $Salt);
		$IP =  (is_null($IP) ? $this->IP : $IP);
		return md5(md5($IP).$Salt);
	}
	
	public function getSessionLevel($default = NULL) {
	/**
	 * Проверяет, авторизован пользователь или нет
	 * Устанавливает уровень доступа если авторизован, иначе NULL (гость)
	 * @param int [$default]
	 * @return int 
	 */
		$this->Level = ( (isset($_SESSION["level"])) ? $_SESSION["level"] : NULL); 
		return $this->Level;
	}
	
	public function saveDB() {
	/**
	 * Сохраняет состояние в БД
	 * @return int uID 
	 */
		if (is_null($this->uID)) { // uID = NULL - новый пользователь, добавляем
			$sql = "INSERT INTO `users` (`Login`, `Nick`, `HashPass`, `HashKey`, `Salt`, `Level`, `HashIP`, `maxInvites`) 
					VALUES ('{$this->quote($this->Login)}', '{$this->quote($this->Nick)}', '{$this->quote($this->HashPass)}', '{$this->quote($this->HashKey)}', '{$this->quote($this->Salt)}', {$this->quote($this->Level)}, '{$this->quote($this->HashIP)}', {$this->quote($this->maxInvites)});
					SELECT LAST_INSERT_ID();"; // 
		} else { // обновляем пользователя с указанным uID
			$sql = "UPDATE `users` SET `Login`='{$this->quote($this->Login)}', `Nick`='{$this->quote($this->Nick)}', `HashPass`='{$this->quote($this->HashPass)}', `HashKey`='{$this->quote($this->HashKey)}', `Salt`='{$this->quote($this->Salt)}', `Level`={$this->quote($this->Level)}, `HashIP`='{$this->quote($this->HashIP)}', `maxInvites`={$this->quote($this->maxInvites)}
			WHERE  `uID`={$this->quote($this->uID)};
			SELECT {$this->quote($this->uID)};"; // Для совместимости получим его же uID обратно
		}
		
		$res = $this->query($sql);
		$this->uID = (($row = $res->fetch_row()) ? (int)$row[0] : null); // Запоминаем полученный uID
		$res->close();
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
			$sql = "SELECT * FROM `users` WHERE `Login`={$this->quote($Login)} AND `HashKey`='{$this->quote($HashKey)}' AND `HashIP`='{$this->quote($HashIP)}' LIMIT 1;"
			$result = false; // Пользователь даказывает верность загрузки
		} else {
			$sql = "SELECT * FROM `users` WHERE `uID`={$this->quote($uID)} LIMIT 1";
			$result = true; // Сервер доказывает верность загрузки;
		}
		
		$res = $this->query($sql);
		
		if ($res->num_rows == 1) // Такой пользователь существует
		{
			$row = $res->fetch_row();
			$this->uID			= (int)$uID;
			$this->Login		=      $row[1];
			$this->Nick			=      $row[2];
			$this->HashPass		=      $row[3];
			$this->HashKey		=      $row[4];
			$this->Salt			=      $row[5];
			$this->Level		= (int)$row[6];
			$this->HashIP		=      $row[7];
			$this->maxInvites	= (int)$row[8];
			
			// 1. Вне зависимости от успеха загрузки проверим авторизацию.
			// 2. Вне зависимости от успеха авторизации, если запрашивали 
			// конкретного пользователя, вернем успешную загрузку.
			$result = $result OR (  $this->HashIP == md5( md5($_SERVER['REMOTE_ADDR']).$this->Salt )  );
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
		$_SESSION["uID"] 		= $this->uID;
		$_SESSION["Login"] 		= $this->Login;
		$_SESSION["Nick"] 		= $this->Nick;
		$_SESSION["HashPass"] 	= $this->HashPass;
		$_SESSION["HashKey"] 	= $this->HashKey;
		$_SESSION["Salt"] 		= $this->Salt;
		$_SESSION["Level"] 		= $this->Level;
		$_SESSION["HashIP"] 	= $this->HashIP;
		$_SESSION["maxInvites"] = $this->maxInvites;
	
		return true;
	}

	public function loadSession() {
	/**
	 * Загружает пользователя из сессии
	 * @return bool $result 
	 */
		if (isset($_SESSION["uID"])) // сессия открыта
		{
			$this->uID 		  = $_SESSION["uID"];
			$this->Login	  = $_SESSION["Login"];
			$this->Nick 	  = $_SESSION["Nick"];
			$this->HashPass	  = $_SESSION["HashPass"];
			$this->HashKey 	  = $_SESSION["HashKey"];
			$this->Salt		  = $_SESSION["Salt"];
			$this->Level	  = $_SESSION["Level"];
			$this->HashIP 	  = $_SESSION["HashIP"];
			$this->maxInvites = $_SESSION["maxInvites"];
			$resturn true;
		}
		else return false;
	}
	

	public function saveCookies() {
	/**
	 * Сохраняет состояние в куки
	 * @return bool true 
	 */
		$Cookie=array(	"Login" 	=> $this->Login, 
						"HashKey"	=> $this->HashKey,
						"HashIP"	=> $this->HashIP	);
	
		setcookie("Auth",serialize($Cookie),time()+3*24*60*60); // Устанавливаем куки на 3 дня				

		return true;
	}

	public function loadCookies() {
	/**
	 * Загружает пользователя из куки
	 * @return bool $result 
	 */
		if (isset($_COOKIE["Auth"]) // Есть кука 
		{
			$Cookie = unserialize($_COOKIE["Auth"]); // получаем куку			
			$this->Login	= $Cookie["Login"];
			$this->HashKey	= $Cookie["HashKey"];
			$this->HashIP	= $Cookie["HashIP"]);
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
		$this->saveCookies(); // сохраним куки

		return true;
	}

	public function load() {
	/**
	 * Восстонавливает состояние авторизации
	 * из БД, сессии и кук
	 * @return bool true
	 */
		if ($this->loadSession()) {return true;} // Загрузили через сохраненную сессию
		elseif ($this->loadCookies())  // Пытаемся загрузить куки
		{							// Куки есть, загружены. 
			return $this->loadDB(); // Загружаем остальное из базы и авторизуемся.
		}
		else {return false;} // ни сессии, ни кук - не авторизован
	
		return true;
	}

	public function Auth($password, $login = NULL) {
	/**
	 * Авторизует пользователя по паролю:[логину], 
	 * возвращает удалось ли авторизоваться
	 * @param string $password
	 * @param string [$login] 
	 * @return bool 
	 */
		$login = ( is_null($login) ? $this->Login : $login );
		
		$res = $this->db->query("SELECT * FROM `users` WHERE `login`='{$this->quote($login)}' LIMIT 1");
		
		if ($res->num_rows == 1) // Если логин существует
		{
			$row = $res->fetch_row();
			$_uid 		 = (int)$row[0];
			// Login известен
			$_nick 		 =      $row[2];
			$_HashPass 	 =      $row[3];
			$_HashKey 	 =      $row[4];
			$_Salt 		 =      $row[5];
			$_Level 	 = (int)$row[6];
			// HashIP установим новый
			$_maxInvites = (int)$row[8];
			
			if (md5( md5($password).$salt ) == $_HashPass) { // Если пароль указан правильно
				$this->setUser($_uid, $login, $_nick, $_HashPass, $this->newHashKey, $_Salt, $_Level, $this->newHashIP($_Salt), $_maxInvites);
				$this->save(); // сохраним состояние авторизации				
				return true;
			}else { // Пароль не подошел
				return false;
			}
		
		}else { // Логин не существует, авторизация провалилась
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
		$res = $this->db->query("SELECT `uid` FROM `users` WHERE `Login`='{$this->quote($login)}'");
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
		$res = $this->db->query("SELECT `uid` FROM `users` WHERE `Nick`='{$this->quote($nick)}'");
		$result = ($res->num_rows == 1);// Если пользователь существует
		$res->close();
		return $result; 
	}

	public function out() {
	/**
	 * Метод осуществляет выход пользователя
	 * @return bool true
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
		$res = $this->db->query("SELECT `id` FROM `invites` WHERE `invite`='{$this->quote($invite)}' AND `uid2` IS NULL LIMIT 1;");
		$result = ($res->num_rows == 1); // Если код существует и действителен
		$res->close();
		return $result;
	}
	
	public function listActiveInvites($uid) {
	/**
	 * Метод возвращает список активных приглашений пользователя
	 * @param int $uid
	 * @return array $ActiveInvites
	 */
		$res = $this->db->query("SELECT * FROM `invites` WHERE `uid1`='{$this->quote($uid)}' AND `uid2` IS NULL;");
		for ($result = array (); $row = $res->fetch_assoc(); $result[] = $row);
		$res->close();
		return $result;
	}
}
?>