<?php
$lat = 55.76;
$lon = 37.64;
if (isset($_GET['lat']) && isset($_GET['lon']))
{
	$lat = (float)$_GET['lat'];
	$lon = (float)$_GET['lon'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Free WiFi MAP</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<script src="//api-maps.yandex.ru/2.1/?lang=ru-RU" type="text/javascript"></script>
<script src="//yandex.st/jquery/2.1.0/jquery.min.js" type="text/javascript"></script>
<script type="text/javascript">
ymaps.ready(init);

function init () {
	var pass = localStorage.getItem('3wifi.password');
	if (pass == null) pass = '';

	myMap = new ymaps.Map('map', {
		center: [<?php echo $lat; ?>, <?php echo $lon; ?>],
		zoom: 18
	}),

	loadingObjectManager = new ymaps.LoadingObjectManager('3wifi.php?a=map&pass='+pass+'&bbox=%b',
	{   
		// Включаем кластеризацию.
		clusterize: true,
		// Опции кластеров задаются с префиксом cluster.
		clusterHasBalloon: false,
		// Опции объектов задаются с префиксом geoObject
		geoObjectOpenBalloonOnClick: false
	});

	myMap.geoObjects.add(loadingObjectManager);
};
</script>
<style>
html, body, #map {
	width: 100%; height: 100%; padding: 0; margin: 0;
}
</style>
</head>
<body>
	<div id="map"></div>
</body>
</html>
