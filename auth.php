<?php
$magic = '3wifi-magic-word';
$pass_level1 = 'antichat';
$pass_level2 = 'secret_password';

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
$level = 0;
if ($pass == $pass_level1) $level = 1;
if ($pass == $pass_level2) $level = 2;
?>