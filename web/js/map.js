var map;
var line_data = new Array();
var segments = new Array();
var stations = new Array();
var shown = new Array();

function initialize() {
	var lat = $.url(true).param('lat') ? $.url(true).param('lat') : 48.21;
	var lon = $.url(true).param('lon') ? $.url(true).param('lon') : 16.37;
	var zoom = $.url(true).param('zoom') ? $.url(true).param('zoom') : 12;

	map = L.map('map_canvas')
	map.setView([lat, lon], zoom);
	map.on('moveend', function() { update_current_view_info() });
	map.on('zoomend', function() { update_current_view_info() });

	var osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
		id: 'openstreetmap.org',
	});

	var basemap = L.tileLayer('https://mapsneu.wien.gv.at/basemap/geolandbasemap/normal/google3857/{z}/{y}/{x}.png', {
		maxZoom: 20,
		attribution: '<a href="https://www.basemap.at/" target="_blank">basemap.at</a>',
		id: 'wien.gv.at',
	});

	var orthofoto = L.tileLayer('https://mapsneu.wien.gv.at/basemap/bmaporthofoto30cm/normal/google3857/{z}/{y}/{x}.jpeg', {
		maxZoom: 20,
		attribution: '<a href="https://www.basemap.at/" target="_blank">basemap.at</a>',
		id: 'wien.gv.at',
	});

	var baseLayers = {
		'OpenStreetMap': osm,
		'basemap.at': basemap,
		'basemap.at Orthofoto': orthofoto,
	};

	var layerControls = L.control.layers(baseLayers).addTo(map);
	basemap.addTo(map);

	update_current_view_info();
}

function update_current_view_info() {
	if(!map) {
		return;
	}

	var latLon = map.getCenter();
	var zoom = map.getZoom();

	$('#current_latlon').html(Math.round(latLon.lat*100)/100 + " " + Math.round(latLon.lng*100)/100);
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
	permalink += '?lat=' + latLon.lat;
	permalink += '&lon=' + latLon.lng;
	permalink += '&zoom=' + zoom;
	permalink += '&lines=' + line_ids.join(',');
	
	window.history.replaceState('', document.title, permalink);

	$('#current_permalink').attr('href', permalink);
}

function hide(ids) {
	$.each(ids, function(index, id) {
		$.each(segments[id], function(index, value) {
			map.removeLayer(value);
		});
		$.each(stations[id], function(index, value) {
			map.removeLayer(value);
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
				value.addTo(map);
			});
			$.each(stations[id], function(index, value) {
				value.addTo(map);
				style_station(id, value);
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
				coordinates.push([lat_lon[0], lat_lon[1]]);
			});
			var segment = L.polyline(coordinates, {
				color: '#' + line_data[id]["color"],
				opacity: 1.0,
				weight: line_data[id]["line_thickness"],
			});
			segment.on('click', function() {
				line_click(id)
			});

			segment.addTo(map);
			segments[id].push(segment);

		});
		$.each(line_data[id]["stations"], function(index, value) {
			var lat = value["lat"];
			var lon = value["lon"];

			var station = L.marker([lat, lon], {
				icon: L.divIcon(),
			    	title: value["name"]
			});
			station.addTo(map);

			style_station(id, station);
			
			station.on('click', function() {
				station_click(id, index);
			});

			stations[id].push(station);
		});
	});

	update_current_view_info();
}

function style_station(id, station) {
	var point_radius = line_data[id]['line_thickness']*1.5;

	station.getElement().style.width = point_radius*2 + 'px';
	station.getElement().style.height = point_radius*2 + 'px';
	station.getElement().style.marginLeft = -point_radius + 'px';
	station.getElement().style.marginTop = -point_radius + '-6px';
	station.getElement().style.borderRadius = point_radius + 'px';
	station.getElement().style.backgroundColor = '#' + line_data[id]["color"];
	station.getElement().style.borderColor = '#' + line_data[id]["color"];
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

