function initialize() {
	var mapOptions = {
		center: new google.maps.LatLng(48.21, 16.37),
		zoom: 12,
		mapTypeId: google.maps.MapTypeId.ROADMAP,
		streetViewControl: false
	};
	var map = new google.maps.Map(document.getElementById("map_canvas"), mapOptions);
}

