var googleMap;
var line_data = new Array();
var segments = new Array();
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
	$.each(line_data[id], function(index, value) {
		var lat1 = value[0][0];
		var lon1 = value[0][1];
		var lat2 = value[1][0];
		var lon2 = value[1][1];

		var coordinates = [
			new google.maps.LatLng(lat1, lon1),
			new google.maps.LatLng(lat2, lon2)
		];
		var segment = new google.maps.Polyline({
			path: coordinates,
			strokeColor: '#FF0000',
			strokeOpacity: 1.0,
			strokeWeight: 2
		});

		segment.setMap(googleMap);
		segments[id].push(segment);
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

