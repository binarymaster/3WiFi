<?php
$lat = 46.224421;
$lon = 39.834659;
$rad = 7;
if (isset($_GET['lat'])) $lat = (float)$_GET['lat'];
if (isset($_GET['lon'])) $lon = (float)$_GET['lon'];
if (isset($_GET['rad'])) $rad = (float)$_GET['rad'];
?>
<html>
<head>
<title>3WiFi: Поиск диапазонов IP</title>
<meta http-equiv=Content-Type content="text/html;charset=UTF-8">
<link rel=stylesheet href="css/style.css" type="text/css">
<script src="//code.jquery.com/jquery-2.1.4.min.js"></script>
<script type="text/javascript">
function find()
{
	$('#tranges').css('display', 'none');
	$('#fdata').empty();
	$('#fhead').empty();
	$('#fhead').append('<tr><th>IP Range</th><th>Description</th></tr>');
	$('#fdata').append('<tr><td colspan=2><img src="img/loading.gif"></td></tr>');

	$.post('3wifi.php?a=find_ranges', $($('form')[0]).serialize(), function(d)
	{
		if (!d.result)
		{
			$('#fdata > tr:first-child > :first-child').text('Ошибка загрузки данных.');
			return;
		}
		if (!d.auth)
		{
			localStorage.setItem('3wifi.password', '');
			$('#fdata > tr:first-child > :first-child').text('Не авторизован.');
			return;
		}
		localStorage.setItem('3wifi.password', $('input[name=pass]').val());
		if(typeof d.error != "undefined")
		{
			$('#fdata > tr:first-child > :first-child').text(d.error);
			return;
		}
		if (d.data.length > 0)
		{
			$('#fdata').empty();
			$('#fhead').empty();
			$('#fhead').append('<tr><th>IP Range</th><th>Description</th></tr>');
			for (var i = 0; i < d.data.length; i++)
			{
				$('#fdata').append('<tr><td>'+d.data[i].range+'</td><td>'+d.data[i].descr+'</td></tr>');
			}
		} else {
			$('#fdata > tr:first-child > :first-child').text('Ничего не найдено.');
		}
	});
}
function rangesText()
{
	var data = $('#fdata > tr > :first-child');
	var str = '';
	for (var i = 0; i < data.length; i++)
	{
		var td = $(data[i]);
		if (td.prop('colspan') == 2) continue;
		str += td.text()+"\r\n";
	}
	return str;
}
function listRanges()
{
	var str = rangesText();
	if (str == '') {
		$('#tranges').css('display', 'none');		
		return;
	}
	$('#tranges').val(str);
	$('#tranges').css('display', 'block');
}
function initpage()
{
	$('input').bind('keydown', function (e) {
		switch (e.keyCode)
		{
			case 13: // Enter
			find();
			break;
		}
	});
	var pass = localStorage.getItem('3wifi.password');
	if (pass != null) $('input[name=pass]').val(pass);
}
</script>
</head><body onload='initpage()'>

<table>
	<tbody id=fuserform>
		<form enctype="multipart/form-data" method="POST">
			<tr><td>Пароль доступа:</td><td><input name="pass" type="password" value="" /></td></tr>
			<tr><td>Latitude / Широта:</td><td><input name="latitude" type="number" value="<?php echo $lat; ?>" /></td><td>° [-90; 90]</td></tr>
			<tr><td>Longitude / Долгота:</td><td><input name="longitude" type="number" value="<?php echo $lon; ?>" /></td><td>° [-180; 180]</td></tr>
			<tr><td>Search radius / Радиус поиска:</td><td><input name="radius" type="number" value="<?php echo $rad; ?>" /></td><td>км (max 25)</td></tr>
		</form>
	</tbody>
	<tbody>
		<tr><td><button onclick="find()">Найти</button> <button onclick="listRanges()">Список</button></td><td></td></tr>
	</tbody>
</table>
<textarea id=tranges cols=24 rows=8 style="display: none"></textarea>
<br>
<table class=st1>
	<tbody id=fhead></tbody>
	<tbody id=fdata></tbody>
</table>
</body>
</html>