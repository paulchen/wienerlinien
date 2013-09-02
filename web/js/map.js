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

function hide(ids) {
	$.each(ids, function(index, id) {
		$.each(segments[id], function(index, value) {
			value.setVisible(false);
		});
		$.each(stations[id], function(index, value) {
			value.setVisible(false);
		});
		shown[id] = false;
	});
}

function load(ids) {
	$.ajax({
		url: 'json.php?lines=' + ids.join(','),
		dataType: 'json',
		success: function(data, text, xhr) {
			$.each(data, function(index, line) {
				line_data[line['line']] = line;
			});
			show(ids);
		}
	});
}

function show(ids) {
	var missing_ids = new Array();
	$.each(ids, function(index, id) {
		if(!(id in line_data)) {
			missing_ids.push(id);
		}
	});
	if(missing_ids.length > 0) {
		load(missing_ids);
		return;
	}

	$.each(ids, function(index, id) {
		shown[id] = true;
		if(id in segments) {
			$.each(segments[id], function(index, value) {
				value.setVisible(true);
			});
			$.each(stations[id], function(index, value) {
				value.setVisible(true);
			});

			return;
		}

		segments[id] = new Array();
		stations[id] = new Array();
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

			var point_radius = line_data[id]['line_thickness']*1.5;
			var path = 'm -X, 0 a X,X 0 1,0 Y,0 a X,X 0 1,0 -Y,0'
			path = path.replace(/X/g, point_radius);
			path = path.replace(/Y/g, point_radius*2);

			var station = new google.maps.Marker({
				position: new google.maps.LatLng(lat, lon),
				map: googleMap,
				icon: {
					path: path,
					strokeColor: '#' + line_data[id]["color"],
					fillColor: '#' + line_data[id]["color"],
					fillOpacity: 1.0
				}
			});
			stations[id].push(station);
		});
	});
}

function toggle(id) {
	var ids = new Array(1);
	ids[0] = id;
	if(!(id in shown) || !shown[id]) {
		show(ids);
	}
	else {
		hide(ids);
	}
}

function show_group(group) {
	$.each(groups[group], function(index, value) {
		$('#checkbox_line_' + value).prop('checked', true);
	});
	show(groups[group]);
}

function hide_group(group) {
	$.each(groups[group], function(index, value) {
		$('#checkbox_line_' + value).prop('checked', false);
	});
	hide(groups[group]);
}

function invert_group(group) {
	var show_ids = new Array();
	var hide_ids = new Array();

	$.each(groups[group], function(index, value) {
		if(shown[value]) {
			$('#checkbox_line_' + value).prop('checked', false);
			hide_ids.push(value);
		}
		else {
			$('#checkbox_line_' + value).prop('checked', true);
			show_ids.push(value);
		}
	});
	show(show_ids);
	hide(hide_ids);
}
