var googleMap;
var line_data = new Array();
var segments = new Array();
var stations = new Array();
var shown = new Array();

function initialize() {
	var lat = $.url(true).param('lat') ? $.url(true).param('lat') : 48.21;
	var lon = $.url(true).param('lon') ? $.url(true).param('lon') : 16.37;
	var zoom = $.url(true).param('zoom') ? $.url(true).param('zoom') : 12;

	var mapOptions = {
		center: new google.maps.LatLng(lat, lon),
		zoom: parseInt(zoom),
		mapTypeId: google.maps.MapTypeId.ROADMAP,
		streetViewControl: false
	};
	googleMap = new google.maps.Map(document.getElementById("map_canvas"), mapOptions);
	google.maps.event.addListener(googleMap, 'center_changed', update_current_view_info);
	google.maps.event.addListener(googleMap, 'zoom_changed', update_current_view_info);
	update_current_view_info();
}

function update_current_view_info() {
	if(!googleMap) {
		return;
	}

	var latLon = googleMap.getCenter();
	var zoom = googleMap.getZoom();

	$('#current_latlon').html(Math.round(latLon.lat()*100)/100 + " " + Math.round(latLon.lng()*100)/100);
	$('#current_zoom').html(zoom);

	var line_names = new Array();
	var line_ids = new Array();
	$.each(shown, function(index, value) {
		if(value) {
			line_names.push(line_data[index]['name']);
			line_ids.push(index);
		}
	});
	if(line_names.length == 0) {
		$('#current_lines').html('keine');
	}
	else {
		line_names.sort(function(a, b) {
			if(line_orders[a] < line_orders[b]) {
				return -1;
			}
			if(line_orders[a] > line_orders[b]) {
				return 1;
			}
			return 0;
		});
		$('#current_lines').html(line_names.join(', '));
	}

	var permalink = document.location.href;
	permalink = permalink.substring(0, permalink.lastIndexOf('/')) + '/';
	permalink += '?lat=' + latLon.lat();
	permalink += '&lon=' + latLon.lng();
	permalink += '&zoom=' + zoom;
	permalink += '&lines=' + line_ids.join(',');
	
	window.history.replaceState('', document.title, permalink);

	$('#current_permalink').attr('href', permalink);
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

	update_current_view_info();
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
			google.maps.event.addListener(segment, 'click', function() {
				line_click(id)
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
				},
			    	title: value["name"]
			});
			google.maps.event.addListener(station, 'click', function() {
				station_click(id, index);
			});

			stations[id].push(station);
		});
	});

	update_current_view_info();
}

function show_overlay(url) {
	$.fancybox.open({
		href: url,
		type: 'iframe',
		padding: 5,
		openEffect: 'elastic',
		closeEffect: 'elastic',
		openSpeed: 150,
		closeSpeed: 150,
		width: '530px',
		height: '90%',
		fitToView: false,
		autoSize: false
	});
}

function line_click(id) {
	show_overlay('line.htm?id=' + id);
}

function station_click(line, station) {
	show_overlay('station.htm?id=' + line_data[line]["stations"][station]["id"]);
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

