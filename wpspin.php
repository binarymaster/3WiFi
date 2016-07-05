<?php

/**
 * Абстрактный класс для генераторов WPS PIN по BSSID
 */
abstract class WpspinGenerator
{

	/**
	 * @var string Название алгоритма
	 */
	protected $name = "Noname";

	/**
	 * Возвращает название алгоритма
	 * 
	 * @return string Название алгоритма
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Генерирует WPS PIN
	 * 
	 * @param string $bssid BSSID точки доступа в виде строки из 12 hex-цифр 
	 * @return string 8-циферный WPS PIN
	 */
	public function getPin($bssid)
	{
		return str_pad($this->getPinInt($bssid), 8, '0', STR_PAD_LEFT);
	}

	/**
	 * Генерирует WPS PIN в виде числа
	 * 
	 * @param string $bssid BSSID точки доступа в виде строки из 12 hex-цифр 
	 * @return int WPS PIN
	 */
	public function getPinInt($bssid)
	{
		$pin = $this->getBasePin($bssid);
		return $pin * 10 + $this->calcChecksum($pin);
	}

	/**
	 * Генерирует WPS PIN без контрольной суммы
	 * 
	 * @param string $bssid BSSID точки доступа в виде строки из 12 hex-цифр 
	 * @return int WPS PIN
	 */
	abstract public function getBasePin($bssid);

	/**
	 * Вычисляет контрольную сумму для WPS PIN
	 * 
	 * @param int $pin WPS PIN без последней цифры
	 * @return int Контрольная сумма
	 */
	static final public function calcChecksum($pin)
	{
		$accum = 0;
		while ($pin)
		{
			$accum += 3 * ($pin % 10);
			$pin = (int) ($pin / 10);
			$accum += $pin % 10;
			$pin = (int) ($pin / 10);
		}
		return (10 - $accum % 10) % 10;
	}

	/**
	 * Преобразует BSSID к строке из 12 hex-цифр в верхнем регистре
	 * 
	 * Удаляет все недопустимые символы из строки, при необходимости дополняя
	 * её нулями или обрезая до длины в 12 символов
	 * 
	 * @param string $bssid BSSID точки доступа
	 * @return string Форматированный BSSID
	 */
	static final public function formatBssid($bssid)
	{
		$bssid = preg_replace('/[^0-9A-Fa-f]/', '', $bssid);
		$bssid = str_pad($bssid, 12, '0', STR_PAD_LEFT);
		$bssid = substr($bssid, 0, 12);
		return strtoupper($bssid);
	}

}

/**
 * Генератор WPS PIN на основе последних 24 бит BSSID
 */
class WpsGen24bit extends WpspinGenerator
{

	/**
	 * {@inheritdoc}
	 */
	protected $name = "24-bit PIN";

	/**
	 * {@inheritdoc}
	 */
	public function getBasePin($bssid)
	{
		$pin = hexdec(substr($bssid, 6, 6)) % 10000000;
		return $pin;
	}

}

/**
 * Генератор WPS PIN на основе последних 28 бит BSSID
 */
class WpsGen28bit extends WpspinGenerator
{

	/**
	 * {@inheritdoc}
	 */
	protected $name = "28-bit PIN";

	/**
	 * {@inheritdoc}
	 */
	public function getBasePin($bssid)
	{
		$pin = hexdec(substr($bssid, 5, 7)) % 10000000;
		return $pin;
	}

}

/**
 * Генератор WPS PIN на основе последних 32 бит BSSID
 */
class WpsGen32bit extends WpspinGenerator
{

	/**
	 * {@inheritdoc}
	 */
	protected $name = "32-bit PIN";

	/**
	 * {@inheritdoc}
	 */
	public function getBasePin($bssid)
	{
		$pin = (hexdec($bssid[4]) * 8435456 + hexdec(substr($bssid, 5, 7)) % 10000000) % 10000000;
		return $pin;
	}

}

/**
 * Генератор WPS PIN для некоторых моделей D-Link
 * 
 * http://www.devttys0.com/2014/10/reversing-d-links-wps-pin-algorithm/
 */
class WpsGenDlink extends WpspinGenerator
{

	/**
	 * {@inheritdoc}
	 */
	protected $name = "D-Link PIN";

	/**
	 * {@inheritdoc}
	 */
	public function getBasePin($bssid)
	{
		$pin = hexdec(substr($bssid, 6, 6));
		$pin ^= hexdec(str_repeat($bssid[11], 5)) * 16 + 5;
		$pin ^= 0xFF00;
		$pin %= 10000000;
		if ($pin < 1000000)
		{
			$pin += ($pin % 9 + 1) * 1000000;
		}
		return $pin;
	}

}

/**
 * Генератор WPS PIN для некоторых моделей D-Link
 * 
 * http://www.devttys0.com/2014/10/reversing-d-links-wps-pin-algorithm/
 */
class WpsGenDlink1 extends WpspinGenerator
{

	/**
	 * {@inheritdoc}
	 */
	protected $name = "D-Link PIN +1";

	/**
	 * {@inheritdoc}
	 */
	public function getBasePin($bssid)
	{
		$pin = hexdec(substr($bssid, 6, 6)) + 1;
		$pin ^= hexdec(str_repeat(dechex($pin & 0xF), 5)) * 16 + 5;
		$pin ^= 0xFF00;
		$pin %= 10000000;
		if ($pin < 1000000)
		{
			$pin += ($pin % 9 + 1) * 1000000;
		}
		return $pin;
	}

}

/**
 * Генератор WPS PIN для Vodafone EasyBox
 * 
 * https://www.sec-consult.com/fxdata/seccons/prod/temedia/advisories_txt/20130805-0_Vodafone_EasyBox_Default_WPS_PIN_Vulnerability_v10.txt
 */
class WpsGenEasybox extends WpspinGenerator
{

	/**
	 * {@inheritdoc}
	 */
	protected $name = "Vodafone EasyBox PIN";

	/**
	 * {@inheritdoc}
	 */
	public function getBasePin($bssid)
	{
		$sn = $mac = hexdec(substr($bssid, 8, 4));

		$sn_int = array();
		for ($i = 3; $i >= 0; $i--)
		{
			$sn_int[$i] = $sn % 10;
			$sn = (int) ($sn / 10);
		}

		$mac_int = array();
		for ($i = 3; $i >= 0; $i--)
		{
			$mac_int[$i] = $mac & 0xF;
			$mac >>= 4;
		}

		$k1 = ($sn_int[0] + $sn_int[1] + $mac_int[2] + $mac_int[3]) & 0xF;
		$k2 = ($sn_int[2] + $sn_int[3] + $mac_int[0] + $mac_int[1]) & 0xF;

		$pin = dechex($k1 ^ $sn_int[3]);
		$pin .= dechex($k1 ^ $sn_int[2]);
		$pin .= dechex($k2 ^ $mac_int[1]);
		$pin .= dechex($k2 ^ $mac_int[2]);
		$pin .= dechex($mac_int[2] ^ $sn_int[3]);
		$pin .= dechex($mac_int[3] ^ $sn_int[2]);
		$pin .= dechex($k1 ^ $sn_int[1]);

		return hexdec($pin) % 10000000;
	}

}

/**
 * Генератор WPS PIN для некоторых моделей ASUS и Airocon
 * 
 * https://forum.antichat.ru/posts/3978417/
 */
class WpsGenAsus extends WpspinGenerator
{

	/**
	 * {@inheritdoc}
	 */
	protected $name = "ASUS PIN";

	/**
	 * {@inheritdoc}
	 */
	public function getBasePin($bssid)
	{
		$b = array();
		for ($i = 0; $i < 6; $i++)
		{
			$b[$i] = hexdec(substr($bssid, 2 * $i, 2));
		}
		$s = $b[1] + $b[2] + $b[3] + $b[4] + $b[5];

		$pin = 0;
		for ($i = 0; $i < 7; $i++)
		{
			$pin = $pin * 10 + ($b[$i % 6] + $b[5]) % (10 - (($i + $s) % 7));
		}
		return $pin;
	}

}

/**
 * Генератор WPS PIN для некоторых Airocon Realtek
 * 
 * https://forum.antichat.ru/posts/3975451/
 */
class WpsGenAirocon extends WpspinGenerator
{

	/**
	 * {@inheritdoc}
	 */
	protected $name = "Airocon Realtek PIN";

	/**
	 * {@inheritdoc}
	 */
	public function getBasePin($bssid)
	{
		$b = array();
		for ($i = 0; $i < 6; $i++)
		{
			$b[$i] = hexdec(substr($bssid, 2 * $i, 2));
		}

		$pin = 0;
		for ($i = 0; $i < 7; $i++)
		{
			$pin = $pin * 10 + ($b[$i % 6] + $b[($i + 1) % 6]) % 10;
		}
		return $pin;
	}

}

/**
 * Генератор WPS PIN, линейно зависимых от BSSID
 */
class WpsGenLinear extends WpspinGenerator
{

	/**
	 * {@inheritdoc}
	 */
	protected $name = "Linear sequence";
	private $k;
	private $x0;

	/**
	 * Создаёт экземпляр генератора линейной последовательности
	 * 
	 * @param type $k Отношение приращений BSSID и WPS PIN
	 * @param type $x0 Значение BSSID, соответствующее нулевому WPS PIN
	 */
	public function __construct($k, $x0)
	{
		$this->k = $k;
		$this->x0 = $x0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getBasePin($bssid)
	{
		$bssid = bcadd(bcmul(hexdec(substr($bssid, 0, 6)), 0x1000000), hexdec(substr($bssid, 6, 6)));
		$dif = bcsub($bssid, $this->x0);
		$pin = bcmod(bcdiv($dif, $this->k), 10000000);
		if ($pin < 0)
		{
			$pin += 10000000;
		}
		return $pin;
	}

}

/**
 * Генератор WPS PIN, возвращающий фиксированное значение WPS PIN
 */
class WpsGenStatic extends WpspinGenerator
{

	/**
	 * {@inheritdoc}
	 */
	protected $name = "Static PIN";
	private $pin;

	/**
	 * Создаёт экземпляр генератора 
	 * 
	 * @param type $pin
	 */
	public function __construct($pin)
	{
		$this->pin = $pin;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getBasePin($bssid)
	{
		return $this->pin;
	}

}
