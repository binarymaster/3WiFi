ymaps.ready(init);

function init () {
    var myMap = new ymaps.Map('map', {
            center: [55.76, 37.64],
            zoom: 18
        }),
		
		loadingObjectManager = new ymaps.LoadingObjectManager('getmap.php?bbox=%b',
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