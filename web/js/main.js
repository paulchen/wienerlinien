var googleMap;
var line_data = new Array();
var segments = new Array();
var stations = new Array();
var shown = new Array();

function initialize() {
	var mapOptions = {
		center: new google.maps.LatLng(48.21, 16.37),
		zoom: 12,
		mapTypeId: google.maps.MapTypeId.ROADMAP,
		streetViewControl: false
	};
	googleMap = new google.maps.Map(document.getElementById("map_canvas"), mapOptions);
}

function show_hide(id) {
	if((id in shown) && shown[id]) {
		hide(id);
	}
	else {
		show(id);
	}
}

function hide(id) {
	$.each(segments[id], function(index, value) {
		value.setVisible(false);
	});
	shown[id] = false;
}

function show(id) {
	shown[id] = true;
	if(id in segments) {
		$.each(segments[id], function(index, value) {
			value.setVisible(true);
		});

		return;
	}

	segments[id] = new Array();
	$.each(line_data[id]["segments"], function(index, value) {
		var lat1 = value[0][0];
		var lon1 = value[0][1];
		var lat2 = value[1][0];
		var lon2 = value[1][1];

		var coordinates = new Array();
		$.each(value, function(value_index, lat_lon) {
			coordinates.push(new google.maps.LatLng(lat_lon[0], lat_lon[1]));
		});
		var segment = new google.maps.Polyline({
			path: coordinates,
			strokeColor: '#' + line_data[id]["color"],
			strokeOpacity: 1.0,
			strokeWeight: line_data[id]["line_thickness"]
		});

		segment.setMap(googleMap);
		segments[id].push(segment);
	});
	$.each(line_data[id]["stations"], function(index, value) {
		var lat = value["lat"];
		var lon = value["lon"];

		var station = new google.maps.Marker({
			position: new google.maps.LatLng(lat, lon),
		    	map: googleMap,
		    	icon: {
				path: 'm -5, 0 a 5,5 0 1,0 10,0 a 5,5 0 1,0 -10,0',
		    		strokeColor: '#' + line_data[id]["color"],
		    		fillColor: '#' + line_data[id]["color"],
		    		fillOpacity: 1.0
			}
		});
		/*
		var station = new google.maps.Circle({
			strokeColor: '#' + line_data[id]["color"],
		    	strokeOpacity: 1.0,
		    	strokeWeight: line_data[id]["line_thickness"],
		    	fillColor: '#' + line_data[id]["color"],
		    	fillOpacity: 1.0,
		    	map: googleMap,
		    	center: new google.maps.LatLng(lat, lon),
		    	radius: parseFloat(line_data[id]["line_thickness"])*20
		});
		*/
	});

}

function toggle(id) {
	if(!(id in line_data)) {
		$.ajax({
			url: 'json.php?line='+id,
			dataType: 'json',
			success: function(data, text, xhr) {
				line_data[id] = data;
				show_hide(id);
			}
		});
	}
	else {
		show_hide(id);
	}
}

