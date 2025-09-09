<?php

// TODO check: only run as standalone script from command line

$use_transaction = true;
$long_running_queries = true;

require_once(dirname(__FILE__) . '/../lib/common.php');
require_once(dirname(__FILE__) . '/../lib/Csv.class.php');

$url_lines = 'https://data.wien.gv.at/daten/geo?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:OEFFLINIENOGD&srsName=EPSG:4326&outputFormat=json';
$url_stations = 'https://data.wien.gv.at/daten/geo?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:OEFFHALTESTOGD&srsName=EPSG:4326&outputFormat=json';
$url_station_ids = 'https://data.wien.gv.at/daten/geo?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:HALTESTELLEWLOGD&srsName=EPSG:4326&outputFormat=json';
$url_wl_lines = 'https://www.wienerlinien.at/ogd_realtime/doku/ogd/wienerlinien-ogd-linien.csv';
$url_wl_stations = 'https://www.wienerlinien.at/ogd_realtime/doku/ogd/wienerlinien-ogd-haltestellen.csv';
$url_wl_haltepunkte = 'https://www.wienerlinien.at/ogd_realtime/doku/ogd/wienerlinien-ogd-haltepunkte.csv';
$url_wl_fahrwege = 'https://www.wienerlinien.at/ogd_realtime/doku/ogd/wienerlinien-ogd-fahrwegverlaeufe.csv';
$url_wl_steige = 'https://www.wienerlinien.at/ogd_realtime/doku/ogd/wienerlinien-ogd-steige.csv';

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
if(!$wl_haltepunkte_data = download_csv($url_wl_haltepunkte, 'wl_haltepunkte')) {
	write_log("Error while fetching $url_wl_haltepunkte, aborting now");
	die();
}
if(!$wl_fahrwege_data = download_csv($url_wl_fahrwege, 'wl_fahrwege')) {
	write_log("Error while fetching $url_wl_fahrwege, aborting now");
	die();
}
if(!$wl_steige_data = download_csv($url_wl_steige, 'wl_steige')) {
	write_log("Error while fetching $url_wl_steige, aborting now");
	die();
}

write_log('Validating input data...');

$municipalities = fetch_municipalities();
$stations = fetch_stations();
$lines = fetch_lines();
$line_stations = fetch_line_stations();

$data_ok = import_lines($lines_data, true);
$data_ok &= import_station_ids($station_id_data, true);
$data_ok &= import_stations($municipalities, $stations, $lines, $line_stations, $stations_data, true);

$data_ok &= import_wl_lines($wl_lines_data, true);
$data_ok &= import_wl_stations($municipalities, $wl_stations_data, true);
$data_ok &= import_wl_platforms($wl_haltepunkte_data, $wl_fahrwege_data, $wl_steige_data, true);

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
import_stations($municipalities, $stations, $lines, $line_stations, $stations_data);
import_wl_lines($wl_lines_data);
import_wl_stations($municipalities, $wl_stations_data);
import_wl_platforms($wl_haltepunkte_data, $wl_fahrwege_data, $wl_steige_data);

check_outdated($imported_wl_lines, 'wl_line');
check_outdated($imported_lines, 'line');
check_outdated($imported_wl_stations, 'wl_station');
check_outdated($imported_stations, 'station');
check_outdated($imported_station_ids, 'station_id');
check_outdated($imported_line_station, 'line_station');
check_outdated($imported_line_segment, 'line_segment');
check_outdated($imported_platforms, 'wl_platform');

cleanup();

write_log("Import script successfully completed.");

log_query_stats();
$db->commit();

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
			$type = $row['MeansOfTransport'];
			if(!isset($types[$type])) {
				write_log("Unknown means of transport: $type");
				return false;
			}
		}

		return true;
	}

	write_log("Import lines data from Wiener Linien...");

	foreach($data as $row) {
		$wl_id = $row['LineID'];
		$type = $types[$row['MeansOfTransport']];
		$name = $row['LineText'];
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
			db_query('INSERT INTO wl_line (line, wl_id, wl_order, realtime) VALUES (?, ?, ?, ?)', array($line_id, $wl_id, $row['SortingHelp'], $row['Realtime']));
			$wl_line_id = db_last_insert_id();

			write_log("Inserted wl_line item with line $name and wl_id $wl_id");
		}
		else if(count($data) == 1) {
			$wl_line_id = $data[0]['id'];
			if($data[0]['wl_order'] != $row['SortingHelp'] || $data[0]['realtime'] != $row['Realtime']) {
				db_query('UPDATE wl_line SET wl_order = ?, realtime = ? WHERE wl_id = ?', array($row['SortingHelp'], $row['Realtime'], $wl_line_id));

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

function import_wl_stations(&$municipalities, $data, $check_only = false) {
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
		$municipality = check_municipality($municipalities, $row['MunicipalityID'], $row['Municipality']);
		$name = $row['PlatformText'];
		$wl_diva = $row['DIVA'];

		$existing_station = db_query('SELECT s.id station_id, ws.id wl_station_id
			FROM station s
				JOIN wl_station ws ON (ws.station = s.id)
			WHERE s.name = ?
				AND s.deleted = 0
				AND ws.deleted = 0
				AND ws.wl_diva = ?', array($name, $wl_diva));
		if(count($existing_station) == 0) {
			db_query('INSERT INTO station (name, municipality) VALUES (?, ?)', array($name, $municipality));
			$id = db_last_insert_id();

			write_log("Added station $id ($name, {$row['Municipality']})");
		}
		else if(count($existing_station) == 1) {
			$id = $existing_station[0]['station_id'];
		}
		else {
			write_log("Not updating table wl_station, multiple rows for station $name and wl_diva $wl_diva exist");

			// to make sure these rows don't get deleted by the check_outdated() function
			foreach($existing_station as $row) {
				$imported_stations[] = $row['station_id'];
				$imported_wl_stations[] = $row['wl_station_id'];
			}

			continue;
		}

		$existing_wl_station = db_query('SELECT id, station, wl_lat, wl_lon FROM wl_station WHERE wl_diva = ? AND deleted = 0', array($wl_diva));
		if(count($existing_wl_station) == 0) {
			if(!$row['Latitude'] || !$row['Longitude']) {
				db_query('INSERT INTO wl_station (station, wl_diva) VALUES (?, ?)', array($id, $wl_diva));
			}
			else {
				db_query('INSERT INTO wl_station (station, wl_diva, wl_lat, wl_lon) VALUES (?, ?, ?, ?)', array($id, $wl_diva, $row['Latitude'], $row['Longitude']));
			}
			$new_id = db_last_insert_id();

			$imported_wl_stations[] = $new_id;

			write_log("Added wl_station $new_id ($name, {$row['Municipality']}), wl_diva $wl_diva");
		}
		else if(count($existing_wl_station) == 1) {
			$station = $existing_wl_station[0];
			$wl_station_id = $station['id'];

			if($station['station'] != $id
					|| $station['wl_lat'] != $row['Latitude']
					|| $station['wl_lon'] != $row['Longitude']) {
				if(!$row['Latitude'] || !$row['Longitude']) {
					db_query('UPDATE wl_station SET station = ?, wl_lat = NULL, wl_lon = NULL WHERE id = ?', array($id, $wl_station_id));
				}
				else {
					db_query('UPDATE wl_station SET station = ?, wl_lat = ?, wl_lon = ? WHERE id = ?', array($id, $row['Latitude'], $row['Longitude'], $wl_station_id));
				}

				write_log("Updated wl_station $wl_station_id ($name, {$row['Municipality']}), wl_diva $wl_diva");
			}

			$imported_wl_stations[] = $station['id'];
		}
		else {
			write_log("Not updating table wl_station, multiple rows for wl_diva $wl_diva exist");

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

function fetch_wl_lines() {
	$data = db_query('SELECT l.id, l.name, w.wl_id wl_id
		FROM line l
			JOIN wl_line w ON (l.id = w.line)
		WHERE w.deleted = 0
			AND l.deleted = 0
		ORDER BY l.id ASC');
	$result = array();
	foreach($data as ['id' => $id, 'name' => $name, 'wl_id' => $wl_id]) {
		$result[$wl_id] = array('id' => $id, 'name' => $name);
	}
	return $result;
}

function fetch_wl_stations() {
	$data = db_query('SELECT s.id id, s.name name, ws.wl_diva wl_diva
		FROM station s
			JOIN wl_station ws ON (s.id = ws.station)
		WHERE s.deleted = 0
			AND ws.deleted = 0
		ORDER BY id ASC');
	$result = array();
	foreach($data as ['id' => $id, 'name' => $name, 'wl_diva' => $wl_diva]) {
		$result[$wl_diva] = array('id' => $id, 'name' => $name);
	}
	return $result;
}

function import_wl_platforms($haltepunkte, $fahrwege, $steige, $check_only = false) {
	global $imported_platforms;

	if($check_only) {
		if(count($haltepunkte) == 0 || count($fahrwege) == 0) {
			write_log('Error: Platforms data from Wiener Linien cannot be imported.');
			return false;
		}
		return true;
	}

	write_log("Import platforms data from Wiener Linien...");

	$wl_lines = fetch_wl_lines();
	$wl_stations = fetch_wl_stations();

	$punkte = array();
	foreach($haltepunkte as $row) {
		$punkte[$row['StopID']] = $row;
	}

	$platforms = array();
	foreach($steige as $row) {
		$platforms[$row['StopID']] = $row['Platform'];
	}

	foreach($fahrwege as $row) {
		if ($row['PatternID'] > 2) {
			continue;
		}

		$line_wl_id = $row['LineID'];
		$rbl = $row['StopID'];

		if (!isset($punkte[$rbl])) {
			continue;
		}
		$wl_diva = $punkte[$rbl]['DIVA'];

		$data1 = isset($wl_stations[$wl_diva]) ? array($wl_stations[$wl_diva]) : array();
		$data2 = isset($wl_lines[$line_wl_id]) ? array($wl_lines[$line_wl_id]) : array();

		$station_id = isset($data1[0]) ? $data1[0]['id'] : null;
		$line_id = isset($data2[0]) ? $data2[0]['id'] : null;

		$direction = $row['Direction'];
		if ($line_id && !$direction) {
			continue;
		}
		$pos = $row['StopSeqCount'];
		$lat = $punkte[$rbl]['Latitude'];
		$lon = $punkte[$rbl]['Longitude'];
		$platform = isset($platforms[$rbl]) ? $platforms[$rbl] : $rbl;

		$data3 = db_query('SELECT id FROM wl_platform WHERE station = ? AND line = ? AND direction = ? AND pos = ? AND rbl = ? AND platform = ? AND lat = ? AND lon = ? AND deleted = 0', array($station_id, $line_id, $direction, $pos, $rbl, $platform, $lat, $lon));
		if(count($data3) == 0) {
			if (!$line_id && !$direction) {
				continue;
			}
			db_query('INSERT INTO wl_platform (station, line, direction, pos, rbl, platform, lat, lon) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', array($station_id, $line_id, $direction, $pos, $rbl, $platform, $lat, $lon));
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

	$data = db_query('SELECT id FROM wl_platform_keep');
	foreach($data as $row) {
		if(!in_array($row['id'], $imported_platforms)) {
			$imported_platforms[] = $row['id'];
		}
	}

	write_log("Platforms data successfully imported.");
}

function fetch_lines() {
	$data = db_query('SELECT id, name FROM line WHERE deleted = 0');
	$result = array();
	foreach($data as ['id' => $id, 'name' => $name]) {
		$result[$name] = $id;
	}
	return $result;
}

function fetch_line(&$existing_lines, $name) {
	global $imported_lines;

	if(isset($existing_lines[$name])) {
		$id = $existing_lines[$name];
		$imported_lines[] = $id;
		return $id;
	}

	write_log("Adding unknown line: $name");

	$line_types = db_query('SELECT id, name_pattern FROM line_type WHERE name_pattern IS NOT NULL');
	foreach($line_types as $type) {
		if(preg_match($type['name_pattern'], $name)) {
			db_query('INSERT INTO line (name, type) VALUES (?, ?)', array($name, $type['id']));
			$id = db_last_insert_id();
			write_log("Added line $name (type {$type['id']})");

			$imported_lines[] = $id;
			$existing_lines[$name] = $id;

			return $id;
		}
	}

	// TODO we have a problem here...
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

function fetch_line_stations() {
	$data = db_query('SELECT id, station, line FROM line_station WHERE deleted = 0');
	$result = array();
	foreach($data as ['id' => $id, 'station' => $station, 'line' => $line]) {
		$key = "$station-$line";
		$result[$key] = $id;
	}
	return $result;
}

function process_line_station(&$line_stations, $line, $station) {
	global $imported_line_station;

	$key = "$station-$line";
	if(!isset($line_stations[$key])) {
		db_query('INSERT INTO line_station (station, line) VALUES (?, ?)', array($station, $line));
		$id = db_last_insert_id();

		write_log("Added line/station association $id ($line/$station)");

		$line_stations[$key] = $id;
	}
	else {
		$id = $line_stations[$key];
	}

	$imported_line_station[] = $id;
}

function fetch_points() {
	$data = db_query('SELECT id, CAST(lat AS DOUBLE) AS lat, CAST(lon AS DOUBLE) AS lon FROM segment_point');
	$result = array();
	foreach($data as ['id' => $id, 'lat' => $lat, 'lon' => $lon]) {
		if(!isset($result["$lat"])) {
			$result["$lat"] = array();
		}
		$result["$lat"]["$lon"] = $id;
	}
	return $result;
}

function process_point(&$points, $lat, $lon) {
	$lat = "$lat";
	$lon = "$lon";
	if(isset($points[$lat]) && isset($points[$lat][$lon])) {
		return $points[$lat][$lon];
	}

	db_query('INSERT INTO segment_point (lat, lon) VALUES (?, ?)', array($lat, $lon));
	$id = db_last_insert_id();

	write_log("Added point $id ($lat, $lon)");

	if(!isset($points[$lat])) {
		$points[$lat] = array();
	}
	$points[$lat][$lon] = $id;

	return $id;
}

function fetch_segments() {
	$data = db_query('SELECT id, CAST(point1 AS CHAR) AS point1, CAST(point2 AS CHAR) AS point2 FROM segment');
	$result = array();
	foreach($data as ['id' => $id, 'point1' => $point1, 'point2' => $point2]) {
		if(!isset($result[$point1])) {
			$result[$point1] = array();
		}
		$result[$point1][$point2] = $id;
	}
	return $result;
}

function process_segment(&$segments, $point1, $point2) {
	$existing_segments = array();
	if(isset($segments[$point1]) && isset($segments[$point1][$point2])) {
		$existing_segments[] = $segments[$point1][$point2];
	}
	if(isset($segments[$point2]) && isset($segments[$point2][$point1])) {
		$existing_segments[] = $segments[$point2][$point1];
	}
	if(count($existing_segments) > 0) {
		return array_unique($existing_segments);
	}

	db_query('INSERT INTO segment (point1, point2) VALUES (?, ?)', array($point1, $point2));
	$id = db_last_insert_id();

	write_log("Added segment $id ($point1-$point2)");

	if(!isset($segments[$point1])) {
		$segments[$point1] = array();
	}
	$segments[$point1][$point2] = $id;

	return array($id);
}

function fetch_line_segments() {
	$data = db_query('SELECT id, CAST(line AS CHAR) AS line, CAST(segment AS CHAR) AS segment FROM line_segment WHERE deleted = 0');
	$result = array();
	foreach($data as ['id' => $id, 'line' => $line, 'segment' => $segment]) {
		if(!isset($result[$line])) {
			$result[$line] = array();
		}
		$result[$line][$segment] = $id;
	}
	return $result;
}

function process_line_segment(&$line_segments, $line, $segments) {
	global $imported_line_segment;

	$found = false;
	if(isset($line_segments[$line])) {
		foreach($segments as $segment) {
			if(isset($line_segments[$line][$segment])) {
				$found = true;
				$id = $line_segments[$line][$segment];
				break;
			}
		}
	}
	if(!$found) {
		db_query('INSERT INTO line_segment (segment, line) VALUES (?, ?)', array($segments[0], $line));
		$id = db_last_insert_id();

		write_log("Added line/segment association $id ($line/{$segments[0]})");

		if(!isset($line_segments[$line])) {
			$line_segments[$line] = array();
		}
		$line_segments[$line][$segments[0]] = $id;
	}

	$imported_line_segment[] = $id;
}

function fetch_municipalities() {
	$data = db_query('SELECT id, wl_id, name FROM municipality');
	$result = array();
	foreach($data as ['id' => $id, 'wl_id' => $wl_id, 'name' => $name]) {
		if(!isset($result[$wl_id])) {
			$result[$wl_id] = array();
		}
		$result[$wl_id][$name] = $id;
	}
	return $result;
}

function check_municipality(&$municipalities, $id, $name) {
	if(isset($municipalities[$id]) && isset($municipalities[$id][$name])) {
		return $municipalities[$id][$name];
	}

	db_query('INSERT INTO municipality (wl_id, name) VALUES (?, ?)', array($id, $name));
	$db_id = db_last_insert_id();

	write_log("Added municipality $db_id ($id, $name)");

	if(!isset($municipalities[$id])) {
		$municipalities[$id] = array();
	}
	$municipalities[$id][$name] = $db_id;

	return $db_id;
}

function fetch_stations() {
	$data = db_query('SELECT id, name, short_name, CAST(lat AS DOUBLE) AS lat, CAST(lon AS DOUBLE) AS lon, municipality FROM station WHERE deleted = 0');
	$result = array();
	foreach($data as ['id' => $id, 'name' => $name, 'short_name' => $short_name, 'lat' => $lat, 'lon' => $lon, 'municipality' => $municipality]) {
		$key = "$name$short_name$lat$lon$municipality";
		$result[$key] = $id;
	}
	return $result;
}

function process_station(&$municipalities, &$stations, &$existing_lines, &$line_stations, $name, $short_name, $lines, $lat, $lon) {
	global $imported_stations;

	$municipality_wl_id = 90000;
	$municipality_name = 'Wien';
	$municipality_id = check_municipality($municipalities, $municipality_wl_id, $municipality_name);

	$key = "$name$short_name$lat$lon$municipality_id";
	if(isset($stations[$key])) {
		$id = $stations[$key];
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

		$stations[$key] = $id;
	}
	
	foreach(explode(', ', $lines) as $line) {
		$line_id = fetch_line($existing_lines, $line);
		process_line_station($line_stations, $line_id, $id);
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

function import_stations(&$municipalities, &$stations, &$existing_lines, &$line_stations, $data, $check_only = false) {
	if($check_only) {
		if(!isset($data->features)) {
			write_log('Error: stations data cannot be imported');
		}
		return isset($data->features);
	}

	write_log("Importing stations...");

	foreach($data->features as $feature) {
		process_station($municipalities, $stations, $existing_lines, $line_stations, $feature->properties->HTXT, $feature->properties->HTXTK, $feature->properties->HLINIEN, $feature->geometry->coordinates[1], $feature->geometry->coordinates[0]);
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

	$points = fetch_points();
	$segments = fetch_segments();
	$line_segments = fetch_line_segments();
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
			$point_ids[] = process_point($points, $point[1], $point[0]);
		}

		for($a=0; $a<count($point_ids)-1;$a++) {
			$segment_ids = process_segment($segments, $point_ids[$a], $point_ids[$a+1]);
			foreach($line_ids as $line_id) {
				process_line_segment($line_segments, $line_id, $segment_ids);
			}
		}
	}

	write_log("Lines successfully imported.");
}

function cleanup() {
	write_log('Cleaning up table line_segment');
	db_query('DELETE FROM line_segment WHERE deleted = 1');

	write_log('Cleaning up table segment');
	db_query('DELETE FROM segment WHERE id NOT IN (SELECT segment FROM line_segment)');

	write_log('Cleaning up table segment_point');
	db_query('DELETE FROM segment_point WHERE id NOT IN ((SELECT point1 AS point FROM segment) UNION (SELECT point2 AS point FROM segment))');

	write_log('Cleanup completed');
}

$expiration = time() + 86400;
db_query('INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?', array('static_data_expiration', $expiration, $expiration));
touch(dirname(__FILE__) . '/../log/last_data_update');

