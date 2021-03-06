<?php

// TODO check: only run as standalone script from command line

$long_running_queries = true;

require_once(dirname(__FILE__) . '/../lib/common.php');
require_once(dirname(__FILE__) . '/../lib/Csv.class.php');

$url_lines = 'https://data.wien.gv.at/daten/geo?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:OEFFLINIENOGD&srsName=EPSG:4326&outputFormat=json';
$url_stations = 'https://data.wien.gv.at/daten/geo?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:OEFFHALTESTOGD&srsName=EPSG:4326&outputFormat=json';
$url_station_ids = 'https://data.wien.gv.at/daten/geo?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:HALTESTELLEWLOGD&srsName=EPSG:4326&outputFormat=json';
$url_wl_lines = 'https://data.wien.gv.at/csv/wienerlinien-ogd-linien.csv';
$url_wl_stations = 'https://data.wien.gv.at/csv/wienerlinien-ogd-haltestellen.csv';
$url_wl_platforms = 'https://data.wien.gv.at/csv/wienerlinien-ogd-steige.csv';

if(!$lines_data = download_json($url_lines, 'lines')) {
	write_log("Error while fetching $url_lines, aborting now");
	die();
}
if(!$stations_data = download_json($url_stations, 'stations')) {
	write_log("Error while fetching $url_stations, aborting now");
	die();
}
if(!$station_id_data = download_json($url_station_ids, 'station_ids')) {
	write_log("Error while fetching $url_station_ids, aborting now");
	die();
}

if(!$wl_lines_data = download_csv($url_wl_lines, 'wl_lines')) {
	write_log("Error while fetching $url_wl_lines, aborting now");
	die();
}
if(!$wl_stations_data = download_csv($url_wl_stations, 'wl_stations')) {
	write_log("Error while fetching $url_wl_stations, aborting now");
	die();
}
if(!$wl_platforms_data = download_csv($url_wl_platforms, 'wl_platforms')) {
	write_log("Error while fetching $url_wl_platforms, aborting now");
	die();
}

write_log('Validating input data...');

$data_ok = import_lines($lines_data, true);
$data_ok &= import_station_ids($station_id_data, true);
$data_ok &= import_stations($stations_data, true);

$data_ok &= import_wl_lines($wl_lines_data, true);
$data_ok &= import_wl_stations($wl_stations_data, true);
$data_ok &= import_wl_platforms($wl_platforms_data, true);

if(!$data_ok) {
	write_log('Unable to import data, aborting now');
	die(1);
}

write_log("Starting import script...");

$imported_lines = array();
$imported_wl_lines = array();
$imported_stations = array();
$imported_station_ids = array();
$imported_wl_stations = array();
$imported_line_station = array();
$imported_line_segment = array();
$imported_platforms = array();

import_lines($lines_data);
import_station_ids($station_id_data);
import_stations($stations_data);
import_wl_lines($wl_lines_data);
import_wl_stations($wl_stations_data);
import_wl_platforms($wl_platforms_data);

check_outdated($imported_wl_lines, 'wl_line');
check_outdated($imported_lines, 'line');
check_outdated($imported_wl_stations, 'wl_station');
check_outdated($imported_stations, 'station');
check_outdated($imported_station_ids, 'station_id');
check_outdated($imported_line_station, 'line_station');
check_outdated($imported_line_segment, 'line_segment');
check_outdated($imported_platforms, 'wl_platform');

write_log("Import script successfully completed.");

log_query_stats();

function import_wl_lines($data, $check_only = false) {
	global $imported_lines, $imported_wl_lines;

	$types_data = db_query('SELECT id, wl_name FROM line_type');
	$types = array();
	foreach($types_data as $row) {
		$types[$row['wl_name']] = $row['id'];
	}

	if($check_only) {
		if(count($data) == 0) {
			write_log('Error: Lines data from Wiener Linien cannot be imported.');
			return false;
		}

		foreach($data as $row) {
			$type = $row['VERKEHRSMITTEL'];
			if(!isset($types[$type])) {
				write_log("Unknown means of transport: $type");
				return false;
			}
		}

		return true;
	}

	write_log("Import lines data from Wiener Linien...");

	foreach($data as $row) {
		$wl_id = $row['LINIEN_ID'];
		$type = $types[$row['VERKEHRSMITTEL']];
		$name = $row['BEZEICHNUNG'];
		$line_data = db_query('SELECT id FROM line WHERE name = ? AND type = ? AND deleted = 0 ORDER BY id ASC', array($name, $type));
		if(count($line_data) > 0) {
			$line_id = $line_data[0]['id'];
		}
		else {
			db_query('INSERT INTO line (name, type) VALUES (?, ?)', array($name, $type));
			$line_id = db_last_insert_id();

			write_log("Added line $name (type $type)");
		}

		$data = db_query('SELECT id, wl_order, realtime FROM wl_line WHERE line = ? AND wl_id = ? AND deleted = 0', array($line_id, $wl_id));
		if(count($data) == 0) {
			db_query('INSERT INTO wl_line (line, wl_id, wl_order, realtime) VALUES (?, ?, ?, ?)', array($line_id, $wl_id, $row['REIHENFOLGE'], $row['ECHTZEIT']));
			$wl_line_id = db_last_insert_id();

			write_log("Inserted wl_line item with line $name and wl_id $wl_id");
		}
		else if(count($data) == 1) {
			$wl_line_id = $data[0]['id'];
			if($data[0]['wl_order'] != $row['REIHENFOLGE'] || $data[0]['realtime'] != $row['ECHTZEIT']) {
				db_query('UPDATE wl_line SET wl_order = ?, realtime = ? WHERE wl_id = ?', array($row['REIHENFOLGE'], $row['ECHTZEIT'], $wl_line_id));

				write_log("Inserted wl_line item with line $name and wl_id $wl_id");
			}
		}
		else {
			write_log("Not updating table wl_line, multiple rows for line $line_id and wl_id $wl_id exist");

			// to make sure these rows don't get deleted by the check_outdated() function
			foreach($data as $row) {
				$imported_wl_lines[] = $row['id'];
			}
		}

		$imported_lines[] = $line_id;
		if(isset($wl_line_id)) {
			$imported_wl_lines[] = $wl_line_id;
		}
	}

	write_log("Line data successfully imported.");
}

function import_wl_stations($data, $check_only = false) {
	global $imported_stations, $imported_wl_stations;

	if($check_only) {
		if(count($data) == 0) {
			write_log('Error: Stations data from Wiener Linien cannot be imported.');
			return false;
		}
		return true;
	}

	write_log("Import stations data from Wiener Linien...");

	foreach($data as $row) {
		$municipality = check_municipality($row['GEMEINDE_ID'], $row['GEMEINDE']);
		$name = $row['NAME'];
		$wl_id = $row['HALTESTELLEN_ID'];

		$existing_station = db_query('SELECT s.id station_id, ws.id wl_station_id
			FROM station s
				JOIN wl_station ws ON (ws.station = s.id)
			WHERE s.name = ?
				AND s.deleted = 0
				AND ws.deleted = 0
				AND ws.wl_id = ?', array($name, $wl_id));
		if(count($existing_station) == 0) {
			db_query('INSERT INTO station (name, municipality) VALUES (?, ?)', array($name, $municipality));
			$id = db_last_insert_id();

			write_log("Added station $id ($name, {$row['GEMEINDE']})");
		}
		else if(count($existing_station) == 1) {
			$id = $existing_station[0]['station_id'];
		}
		else {
			write_log("Not updating table wl_station, multiple rows for station $name and wl_id $wl_id exist");

			// to make sure these rows don't get deleted by the check_outdated() function
			foreach($existing_station as $row) {
				$imported_stations[] = $row['station_id'];
				$imported_wl_stations[] = $row['wl_station_id'];
			}

			continue;
		}

		$existing_wl_station = db_query('SELECT id, station, wl_diva, wl_lat, wl_lon FROM wl_station WHERE wl_id = ? AND deleted = 0', array($wl_id));
		if(count($existing_wl_station) == 0) {
			db_query('INSERT INTO wl_station (station, wl_id, wl_diva, wl_lat, wl_lon) VALUES (?, ?, ?, ?, ?)', array($id, $wl_id, $row['DIVA'], $row['WGS84_LAT'], $row['WGS84_LON']));
			$new_id = db_last_insert_id();

			$imported_wl_stations[] = $new_id;

			write_log("Added wl_station $new_id ($name, {$row['GEMEINDE']}), wl_id $wl_id");
		}
		else if(count($existing_wl_station) == 1) {
			$station = $existing_wl_station[0];
			$wl_station_id = $station['id'];

			if($station['station'] != $id
					|| $station['wl_diva'] != $row['DIVA']
					|| $station['wl_lat'] != $row['WGS84_LAT']
					|| $station['wl_lon'] != $row['WGS84_LON']) {
				if(!$row['WGS84_LAT'] || !$row['WGS84_LON']) {
					db_query('UPDATE wl_station SET station = ?, wl_diva = ?, wl_lat = NULL, wl_lon = NULL WHERE id = ?', array($id, $row['DIVA'], $wl_station_id));
				}
				else {
					db_query('UPDATE wl_station SET station = ?, wl_diva = ?, wl_lat = ?, wl_lon = ? WHERE id = ?', array($id, $row['DIVA'], $row['WGS84_LAT'], $row['WGS84_LON'], $wl_station_id));
				}

				write_log("Updated wl_station $wl_station_id ($name, {$row['GEMEINDE']}), wl_id $wl_id");
			}

			$imported_wl_stations[] = $station['id'];
		}
		else {
			write_log("Not updating table wl_station, multiple rows for wl_id $wl_id exist");

			// to make sure these rows don't get deleted by the check_outdated() function
			foreach($existing_wl_station as $row) {
				$imported_stations[] = $row['station'];
				$imported_wl_stations[] = $row['id'];
			}

			continue;
		}

		$imported_stations[] = $id;
	}
	
	write_log("Stations data successfully imported.");
}

function import_wl_platforms($data, $check_only = false) {
	global $imported_platforms;

	if($check_only) {
		if(count($data) == 0) {
			write_log('Error: Platforms data from Wiener Linien cannot be imported.');
			return false;
		}
		return true;
	}

	write_log("Import platforms data from Wiener Linien...");

	foreach($data as $row) {
		$line_wl_id = $row['FK_LINIEN_ID'];
		$station_wl_id = $row['FK_HALTESTELLEN_ID'];
		$wl_id = $row['STEIG_ID'];

		$data1 = db_query('SELECT s.id id, s.name name
			FROM station s
				JOIN wl_station ws ON (s.id = ws.station)
			WHERE ws.wl_id = ?
				AND s.deleted = 0
				AND ws.deleted = 0
			ORDER BY id DESC
			LIMIT 0, 1', array($station_wl_id));
		$data2 = db_query('SELECT l.id, l.name
			FROM line l
				JOIN wl_line w ON (l.id = w.line)
			WHERE w.wl_id = ?
				AND w.deleted = 0
				AND l.deleted = 0
			ORDER BY l.id DESC
			LIMIT 0, 1', array($line_wl_id));

		$station_id = isset($data1[0]) ? $data1[0]['id'] : null;
		$line_id = isset($data2[0]) ? $data2[0]['id'] : null;

		$direction = ($row['RICHTUNG'] == 'H') ? 1 : 2;
		$pos = $row['REIHENFOLGE'];
		$rbls = explode(':', $row['RBL_NUMMER']);
		$area = $row['BEREICH'];
		$platform = $row['STEIG'];
		$lat = $row['STEIG_WGS84_LAT'];
		$lon = $row['STEIG_WGS84_LON'];

		foreach($rbls as $rbl) {
			if($rbl) {
				$data3 = db_query('SELECT id FROM wl_platform WHERE station = ? AND line = ? AND wl_id = ? AND direction = ? AND pos = ? AND rbl = ? AND area = ? AND platform = ? AND lat = ? AND lon = ? AND deleted = 0', array($station_id, $line_id, $wl_id, $direction, $pos, $rbl, $area, $platform, $lat, $lon));
			}
			else {
				$data3 = db_query('SELECT id FROM wl_platform WHERE station = ? AND line = ? AND wl_id = ? AND direction = ? AND pos = ? AND rbl IS NULL AND area = ? AND platform = ? AND lat = ? AND lon = ? AND deleted = 0', array($station_id, $line_id, $wl_id, $direction, $pos, $area, $platform, $lat, $lon));
			}
			if(count($data3) == 0) {
				if(!$rbl) {
					db_query('INSERT INTO wl_platform (station, line, wl_id, direction, pos, area, platform, lat, lon) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', array($station_id, $line_id, $wl_id, $direction, $pos, $area, $platform, $lat, $lon));
				}
				else {
					db_query('INSERT INTO wl_platform (station, line, wl_id, direction, pos, rbl, area, platform, lat, lon) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array($station_id, $line_id, $wl_id, $direction, $pos, $rbl, $area, $platform, $lat, $lon));
				}
				$id = db_last_insert_id();

				$imported_platforms[] = $id;
				if(isset($data1[0]) && isset($data2[0])) {
					write_log("Added platform $id ({$data1[0]['name']}, {$data2[0]['name']})");
				}
				else if(isset($data1[0])) {
					write_log("Added platform $id ({$data1[0]['name']}, unknown line)");
				}
				else if(isset($data2[0])) {
					write_log("Added platform $id (unknown station, {$data2[0]['name']})");
				}
				else {
					write_log("Added platform $id (unknown station, unknown line)");
				}
			}
			else {
				$imported_platforms[] = $data3[0]['id'];
			}
		}
	}

	$data = db_query('SELECT id FROM wl_platform_keep');
	foreach($data as $row) {
		if(!in_array($row['id'], $imported_platforms)) {
			$imported_platforms[] = $row['id'];
		}
	}

	write_log("Platforms data successfully imported.");
}

function fetch_line($name) {
	global $imported_lines;

	$data = db_query('SELECT id FROM line WHERE name = ? AND deleted = 0', array($name));
	if(count($data) == 0) {
		write_log("Adding unknown line: $name");

		$line_types = db_query('SELECT id, name_pattern FROM line_type WHERE name_pattern IS NOT NULL');
		foreach($line_types as $type) {
			if(preg_match($type['name_pattern'], $name)) {
				db_query('INSERT INTO line (name, type) VALUES (?, ?)', array($name, $type['id']));
				$id = db_last_insert_id();
				write_log("Added line $name (type {$type['id']})");
				$imported_lines[] = $id;

				return fetch_line($name);
			}
		}

		// TODO we have a problem here...
	}
	$imported_lines[] = $data[0]['id'];
	return $data[0]['id'];
}

function process_line($name, $type) {
	global $imported_lines;

	if($type == 5 && preg_match('/^S[0-9]+$/', $name)) {
		$type = 7;
	}

	$data = db_query('SELECT id FROM line WHERE name = ? AND type = ? AND deleted = 0 ORDER BY id ASC', array($name, $type));
	if(count($data) == 0) {
		$data = db_query('SELECT id FROM line WHERE name = ? AND deleted = 0 ORDER BY id ASC', array($name));
	}

	if(count($data) > 0) {
		$id = $data[0]['id'];
	}
	else {
		db_query('INSERT INTO line (name, type) VALUES (?, ?)', array($name, $type));
		$id = db_last_insert_id();

		write_log("Added line $name (type $type)");
	}

	$imported_lines[] = $id;

	return $id;
}

function process_line_station($line, $station) {
	global $imported_line_station;

	$data = db_query('SELECT id FROM line_station WHERE deleted = 0 AND station = ? AND line = ?', array($station, $line));
	if(count($data) == 0) {
		db_query('INSERT INTO line_station (station, line) VALUES (?, ?)', array($station, $line));
		$id = db_last_insert_id();

		write_log("Added line/station association $id ($line/$station)");
	}
	else {
		$id = $data[0]['id'];
	}

	$imported_line_station[] = $id;
}

function process_point($lat, $lon) {
	$data = db_query('SELECT id FROM segment_point WHERE lat = ? AND lon = ?', array($lat, $lon));
	if(count($data) == 1) {
		return $data[0]['id'];
	}

	db_query('INSERT INTO segment_point (lat, lon) VALUES (?, ?)', array($lat, $lon));
	$id = db_last_insert_id();

	write_log("Added point $id ($lat, $lon)");

	return $id;
}

function process_segment($point1, $point2) {
	$data = db_query('SELECT id FROM segment WHERE point1 = ? AND point2 = ?', array($point1, $point2));
	if(count($data) == 1) {
		return $data[0]['id'];
	}

	db_query('INSERT INTO segment (point1, point2) VALUES (?, ?)', array($point1, $point2));
	$id = db_last_insert_id();

	write_log("Added segment $id ($point1-$point2)");

	return $id;
}

function process_line_segment($line, $segment) {
	global $imported_line_segment;

	$data = db_query('SELECT id FROM line_segment WHERE deleted = 0 AND segment = ? AND line = ?', array($segment, $line));
	if(count($data) == 0) {
		db_query('INSERT INTO line_segment (segment, line) VALUES (?, ?)', array($segment, $line));
		$id = db_last_insert_id();

		write_log("Added line/segment association $id ($line/$segment)");
	}
	else {
		$id = $data[0]['id'];
	}

	$imported_line_segment[] = $id;
}

function check_municipality($id, $name) {
	$data = db_query('SELECT id FROM municipality WHERE wl_id = ? AND name = ?', array($id, $name));
	if(count($data) == 1) {
		return $data[0]['id'];
	}

	db_query('INSERT INTO municipality (wl_id, name) VALUES (?, ?)', array($id, $name));
	$db_id = db_last_insert_id();

	write_log("Added municipality $db_id ($id, $name)");
	return $db_id;
}

function process_station($name, $short_name, $lines, $lat, $lon) {
	global $imported_stations;

	$municipality_wl_id = 90000;
	$municipality_name = 'Wien';
	$municipality_id = check_municipality($municipality_wl_id, $municipality_name);

	$data = db_query('SELECT id FROM station WHERE deleted = 0 AND name = ? AND short_name = ? AND lat = ? AND lon = ? AND municipality = ?', array($name, $short_name, $lat, $lon, $municipality_id));
	if(count($data) == 1) {
		$id = $data[0]['id'];
	}
	else {
		write_log("Added station $name ($municipality_name, $short_name, $lines, $lat, $lon)...");

		$data = db_query('SELECT id FROM station_id WHERE name = ?', array($name));
		if(count($data) == 0) {
			db_query('INSERT INTO station (name, short_name, lat, lon, station_id, municipality) VALUES (?, ?, ?, ?, NULL, ?)', array($name, $short_name, $lat, $lon, $municipality_id));
		}
		else {
			$station_id = $data[0]['id'];
			db_query('INSERT INTO station (name, short_name, lat, lon, station_id, municipality) VALUES (?, ?, ?, ?, ?, ?)', array($name, $short_name, $lat, $lon, $station_id, $municipality_id));
		}
		$id = db_last_insert_id();
	}
	
	foreach(explode(', ', $lines) as $line) {
		$line_id = fetch_line($line);
		process_line_station($line_id, $id);
	}

	$imported_stations[] = $id;
}

function import_station_ids($data, $check_only = false) {
	global $imported_station_ids;

	if($check_only) {
		if(!isset($data->features)) {
			write_log('Error: station IDs data cannot be imported');
		}
		return isset($data->features);
	}

	write_log("Importing station IDs...");

	foreach($data->features as $feature) {
		$lat = $feature->geometry->coordinates[1];
		$lon = $feature->geometry->coordinates[0];

		$id = $feature->properties->WL_NUMMER;
		$name = $feature->properties->BEZEICHNUNG;

		$imported_station_ids[] = $id;

		$data = db_query('SELECT id FROM station_id WHERE id = ? AND deleted = 0', array($id));
		if(count($data) == 0) {
			db_query('INSERT INTO station_id (id, name, lat, lon) VALUES (?, ?, ?, ?)', array($id, $name, $lat, $lon));

			write_log("Imported station $id ($name)");
		}
	}

	write_log("Station IDs successfully imported.");
}

function import_stations($data, $check_only = false) {
	if($check_only) {
		if(!isset($data->features)) {
			write_log('Error: stations data cannot be imported');
		}
		return isset($data->features);
	}

	write_log("Importing stations...");

	foreach($data->features as $feature) {
		process_station($feature->properties->HTXT, $feature->properties->HTXTK, $feature->properties->HLINIEN, $feature->geometry->coordinates[1], $feature->geometry->coordinates[0]);
	}

	write_log("Stations successfully imported.");
}

function import_lines($data, $check_only = false) {
	if($check_only) {
		if(!isset($data->features)) {
			write_log('Error: lines data cannot be imported');
		}
		return isset($data->features);
	}

	write_log("Importing lines...");

	foreach($data->features as $feature) {
		$coordinates = $feature->geometry->coordinates;
		$lines = $feature->properties->LBEZEICHNUNG;
		$type = $feature->properties->LTYP;

		$line_ids = array();
		foreach(explode(', ', $lines) as $line) {
			$line_ids[] = process_line($line, $type);
		}

		$point_ids = array();
		foreach($coordinates as $point) {
			$point_ids[] = process_point($point[1], $point[0]);
		}

		for($a=0; $a<count($point_ids)-1;$a++) {
			$segment_id = process_segment($point_ids[$a], $point_ids[$a+1]);
			foreach($line_ids as $line_id) {
				process_line_segment($line_id, $segment_id);
			}
		}
	}

	write_log("Lines successfully imported.");
}

touch(dirname(__FILE__) . '/../log/last_data_update');

