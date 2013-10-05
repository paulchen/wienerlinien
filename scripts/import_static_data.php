<?php

// TODO check: only run as standalone script from command line

require_once(dirname(__FILE__) . '/../lib/common.php');
require_once(dirname(__FILE__) . '/../lib/Csv.class.php');

$url_lines = 'http://data.wien.gv.at/daten/wfs?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:OEFFLINIENOGD&srsName=EPSG:4326&outputFormat=json';
$url_stations = 'http://data.wien.gv.at/daten/wfs?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:OEFFHALTESTOGD&srsName=EPSG:4326&outputFormat=json';
$url_station_ids = 'http://data.wien.gv.at/daten/wfs?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:HALTESTELLEWLOGD&srsName=EPSG:4326&outputFormat=json';
$url_wl_lines = 'http://data.wien.gv.at/csv/wienerlinien-ogd-linien.csv';
$url_wl_stations = 'http://data.wien.gv.at/csv/wienerlinien-ogd-haltestellen.csv';
$url_wl_platforms = 'http://data.wien.gv.at/csv/wienerlinien-ogd-steige.csv';

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

import_lines($lines_data, true);
import_station_ids($station_id_data, true);
import_stations($stations_data, true);

import_wl_lines($wl_lines_data, true);
import_wl_stations($wl_stations_data, true);
import_wl_platforms($wl_platforms_data, true);

write_log("Starting import script...");

$imported_lines = array();
$imported_stations = array();
$imported_station_ids = array();
$imported_line_station = array();
$imported_line_segment = array();
$imported_platforms = array();

import_lines($lines_data);
import_station_ids($station_id_data);
import_stations($stations_data);
import_wl_lines($wl_lines_data);
import_wl_stations($wl_stations_data);
import_wl_platforms($wl_platforms_data);

check_outdated($imported_lines, 'line');
check_outdated($imported_stations, 'station');
check_outdated($imported_station_ids, 'station_id');
check_outdated($imported_line_station, 'line_station');
check_outdated($imported_line_segment, 'line_segment');
check_outdated($imported_platforms, 'wl_platform');

write_log("Import script successfully completed.");

log_query_stats();

function import_wl_lines($data, $check_only = false) {
	global $imported_lines;

	if($check_only) {
		if(count($data) == 0) {
			write_log('Error: Lines data from Wiener Linien cannot be imported.');
			return false;
		}
		return true;
	}

	write_log("Import lines data from Wiener Linien...");

	$types_data = db_query('SELECT id, wl_name FROM line_type');
	$types = array();
	foreach($types_data as $row) {
		$types[$row['wl_name']] = $row['id'];
	}

	foreach($data as $row) {
		$line_data = db_query('SELECT id FROM line WHERE name = ? AND deleted = 0', array($row['BEZEICHNUNG']));
		if(count($line_data) == 1) {
			$id = $line_data[0]['id'];
		}
		else {
			$type = $types[$row['VERKEHRSMITTEL']];
			db_query('INSERT INTO line (name, type) VALUES (?, ?)', array($row['BEZEICHNUNG'], $type));
			$id = db_last_insert_id();

			write_log("Added line {$row['BEZEICHNUNG']}");
		}

		$timestamp = strtotime($row['STAND']);
		$data = db_query('SELECT wl_id, wl_order, realtime, UNIX_TIMESTAMP(wl_updated) wl_updated FROM line WHERE id = ?', array($id));
		if($data[0]['wl_id'] != $row['LINIEN_ID'] || $data[0]['wl_order'] != $row['REIHENFOLGE'] || $data[0]['realtime'] != $row['ECHTZEIT'] || $data[0]['wl_updated'] != $timestamp) {
			db_query('UPDATE line SET wl_id = ?, wl_order = ?, realtime = ?, wl_updated = FROM_UNIXTIME(?) WHERE id = ?', array($row['LINIEN_ID'], $row['REIHENFOLGE'], $row['ECHTZEIT'], $timestamp, $id));

			write_log("Updated line {$row['BEZEICHNUNG']}");
		}

		$imported_lines[] = $id;
	}

	write_log("Line data successfully imported.");
}

function import_wl_stations($data, $check_only = false) {
	global $imported_stations;

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

		if($row['TYP'] != 'stop') {
			continue;
		}

		$existing_station = db_query('SELECT id FROM station WHERE name = ? AND deleted = 0', array($row['NAME']));
		if(count($existing_station) == 0) {
			db_query('INSERT INTO station (name, municipality) VALUES (?, ?)', array($row['NAME'], $municipality));
			$id = db_last_insert_id();

			write_log("Added station $id ({$row['NAME']}, {$row['GEMEINDE']})");
		}
		else {
			$id = $existing_station[0]['id'];
		}

		$existing_station = db_query('SELECT wl_id, wl_diva, wl_lat, wl_lon, TIMESTAMP(wl_updated) wl_updated FROM station WHERE id = ?', array($id));
		$station = $existing_station[0];

		$timestamp = strtotime($row['STAND']);
		if($station['wl_id'] != $row['HALTESTELLEN_ID']
				|| $station['wl_diva'] != $row['DIVA']
				|| $station['wl_lat'] != $row['WGS84_LAT']
				|| $station['wl_lon'] != $row['WGS84_LON']
				|| strtotime($station['wl_updated']) != $timestamp) {
			db_query('UPDATE station SET wl_id = ?, wl_diva = ?, wl_lat = ?, wl_lon = ?, wl_updated = FROM_UNIXTIME(?) WHERE id = ?', array($row['HALTESTELLEN_ID'], $row['DIVA'], $row['WGS84_LAT'], $row['WGS84_LON'], $timestamp, $id));

			write_log("Updated station $id ({$row['NAME']}, {$row['GEMEINDE']})");
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

		$data1 = db_query('SELECT id, name FROM station WHERE wl_id = ? AND deleted = 0 ORDER BY id DESC LIMIT 0, 1', array($station_wl_id));
		$data2 = db_query('SELECT id, name FROM line WHERE wl_id = ? AND deleted = 0 ORDER BY id DESC LIMIT 0, 1', array($line_wl_id));

		$station_id = isset($data1[0]) ? $data1[0]['id'] : null;
		$line_id = isset($data2[0]) ? $data2[0]['id'] : null;

		$direction = ($row['RICHTUNG'] == 'H') ? 1 : 2;
		$pos = $row['REIHENFOLGE'];
		$rbls = explode(':', $row['RBL_NUMMER']);
		$area = $row['BEREICH'];
		$platform = $row['STEIG'];
		$lat = $row['STEIG_WGS84_LAT'];
		$lon = $row['STEIG_WGS84_LON'];
		$updated = strtotime($row['STAND']);

		foreach($rbls as $rbl) {
			$data3 = db_query('SELECT id FROM wl_platform WHERE station = ? AND line = ? AND wl_id = ? AND direction = ? AND pos = ? AND rbl = ? AND area = ? AND platform = ? AND lat = ? AND lon = ? AND wl_updated = FROM_UNIXTIME(?) AND deleted = 0', array($station_id, $line_id, $wl_id, $direction, $pos, $rbl, $area, $platform, $lat, $lon, $updated));
			if(count($data3) == 0) {
				db_query('INSERT INTO wl_platform (station, line, wl_id, direction, pos, rbl, area, platform, lat, lon, wl_updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?))', array($station_id, $line_id, $wl_id, $direction, $pos, $rbl, $area, $platform, $lat, $lon, $updated));
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

	$data = db_query('SELECT id FROM line WHERE name = ? AND deleted = 0', array($name));
	if(count($data) == 1) {
		$id = $data[0]['id'];
	}
	else {
		if($type == 5 && preg_match('/^S[0-9]+$/', $name)) {
			$type = 7;
		}

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


