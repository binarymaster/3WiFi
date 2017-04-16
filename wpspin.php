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

/**
 * API функция предсказания WPS PIN по BSSID
 */
function API_pin_search($bssid)
{
	$result = array();
	$algos = array(
		array('generator' => new WpsGen24bit(), 'score' => 0.0),
		array('generator' => new WpsGenAsus(), 'score' => 0.0),
		array('generator' => new WpsGenDlink1(), 'score' => 0.0),
		array('generator' => new WpsGen32bit(), 'score' => 0.0),
		array('generator' => new WpsGen28bit(), 'score' => 0.0),
		array('generator' => new WpsGenAirocon(), 'score' => 0.0),
		array('generator' => new WpsGenDlink(), 'score' => 0.0),
		array('generator' => new WpsGenEasybox(), 'score' => 0.0)
	);
	$total_score = 0.0;
	$unkn = array();
	if ($res = QuerySql("SELECT DISTINCT hex(`BSSID`),`WPSPIN` FROM `BASE_TABLE` WHERE `NoBSSID` = 0 AND `BSSID` BETWEEN (0x$bssid & 0xFFFFFF000000) AND (0x$bssid | 0xFFFFFF) ORDER BY ABS(`BSSID` - 0x$bssid) LIMIT 1000"))
	{
		while ($row = $res->fetch_row())
		{
			$pin = $row[1];
			$bss = str_pad($row[0], 12, '0', STR_PAD_LEFT);
			if ($pin == 1)
			{
				$total_score += 1.0 / sqrt(abs(hexdec(substr($bss, 6, 6)) - hexdec(substr($bssid, 6, 6))) + 1);
				continue;
			}

			$found = false;
			foreach ($algos as &$algo)
			{
				if ($algo['generator']->getPin($bss) == $pin)
				{
					$plus_score = 1.0 / sqrt(abs(hexdec(substr($bss, 6, 6)) - hexdec(substr($bssid, 6, 6))) + 1);
					$total_score += $plus_score;
					$algo['score'] += $plus_score;
					$found = true;
				}
			}
			unset($algo);
			if (!$found)
			{
				$unkn_len = count($unkn);
				if (array_key_exists($pin, $unkn))
				{
					$plus_score = 1.0 / sqrt(abs(hexdec(substr($unkn[$pin], 6, 6)) - hexdec(substr($bssid, 6, 6))) + 1);
					$plus_score += 1.0 / sqrt(abs(hexdec(substr($bss, 6, 6)) - hexdec(substr($bssid, 6, 6))) + 1);
					$total_score += $plus_score;
					$algos[] = array('generator' => new WpsGenStatic((int)($pin/10)), 'score' => $plus_score);
					unset($unkn[$pin]);
				}
				else if ($unkn_len > 1 && $unkn_len < 11)
				{
					$pins = array_keys($unkn);
					for ($i = 0; $i < $unkn_len - 1; $i++)
					{
						for ($j = $i + 1; $j < $unkn_len; $j++)
						{
							if ($pins[$i] == $pins[$j] || $pins[$i] == $pin)
							{
								continue;
							}
							$k = (hexdec(substr($unkn[$pins[$i]], 6, 6)) - hexdec(substr($unkn[$pins[$j]], 6, 6))) / ((int)($pins[$i]/10) - (int)($pins[$j]/10));
							if ($k == 0)
							{
								continue;
							}
							if ($k == (hexdec(substr($bss, 6, 6)) - hexdec(substr($unkn[$pins[$i]], 6, 6))) / ((int)($pin/10) - (int)($pins[$i]/10)))
							{
								$found = true;
								$plus_score = 1.0 / sqrt(abs(hexdec(substr($bss, 6, 6)) - hexdec(substr($bssid, 6, 6))) + 1);
								$plus_score += 1.0 / sqrt(abs(hexdec(substr($unkn[$pins[$i]], 6, 6)) - hexdec(substr($bssid, 6, 6))) + 1);
								$plus_score += 1.0 / sqrt(abs(hexdec(substr($unkn[$pins[$j]], 6, 6)) - hexdec(substr($bssid, 6, 6))) + 1);
								$total_score += $plus_score;
								$algos[] = array(
									'generator' =>  new WpsGenLinear($k, bcsub(hex2dec($bss), bcmul((int)($pin/10), $k))),
									'score' => $plus_score);
								unset($unkn[$pins[$i]]);
								unset($unkn[$pins[$j]]);
								break 2;
							}
						}
					}
					if (!$found)
					{
						$unkn[$pin] = $bss;
					}
				}
				else
				{
					$unkn[$pin] = $bss;
				}
			}
		}
		$res->close();
	}
	usort($algos, function($a, $b){return ($b['score'] > $a['score']) - ($b['score'] < $a['score']);});
	$result['scores'] = array();
	$bssid = WpspinGenerator::formatBssid($bssid);
	$pins = array_keys($unkn, $bssid);
	if (count($pins) > 0 && count($pins) < 4)
	{
		foreach ($pins as $pin)
		{
			$result['scores'][] = array(
				'name' => 'From DB',
				'value' => str_pad($pin, 8, '0', STR_PAD_LEFT),
				'score' => 1
			);
			unset($unkn[$pin]);
		}
	}
	foreach ($unkn as $bss)
	{
		$total_score += 1.0 / sqrt(abs(hexdec(substr($bss, 6, 6)) - hexdec(substr($bssid, 6, 6))) + 1);
	}
	foreach ($algos as $algo)
	{
		if ($algo['score'] == 0)
		{
			continue;
		}
		$result['scores'][] = array(
			'name' => $algo['generator']->getName(),
			'value' => $algo['generator']->getPin($bssid),
			'score' => $algo['score'] / $total_score
		);
	}
	return $result;
}
?>