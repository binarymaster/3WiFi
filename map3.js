ymaps.ready(init);

function init () {
    var myMap = new ymaps.Map('map', {
            center: [55.76, 37.64],
            zoom: 18
        }),
		
		remoteObjectManager = new ymaps.RemoteObjectManager('getmap.php?bbox=%b',
		{   
			// Опции кластеров задаются с префиксом cluster.
			clusterHasBalloon: false,
			// Опции объектов задаются с префиксом geoObject
			geoObjectOpenBalloonOnClick: false
		});

	myMap.geoObjects.add(remoteObjectManager);
};
