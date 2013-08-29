var googleMap;

function initialize() {
	var mapOptions = {
		center: new google.maps.LatLng(48.21, 16.37),
		zoom: 12,
		mapTypeId: google.maps.MapTypeId.ROADMAP,
		streetViewControl: false
	};
	googleMap = new google.maps.Map(document.getElementById("map_canvas"), mapOptions);
}

