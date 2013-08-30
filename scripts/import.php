<?php

// TODO check: only run as standalone script from command line

// TODO -> config file
require_once(dirname(__FILE__) . '/../lib/common.php');

$url_lines = 'http://data.wien.gv.at/daten/wfs?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:OEFFLINIENOGD&srsName=EPSG:4326&outputFormat=json';
$url_stations = 'http://data.wien.gv.at/daten/wfs?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:OEFFHALTESTOGD&srsName=EPSG:4326&outputFormat=json';
$url_station_ids = 'http://data.wien.gv.at/daten/wfs?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:HALTESTELLEWLOGD&srsName=EPSG:4326&outputFormat=json';
$url_wl_lines = 'http://data.wien.gv.at/csv/wienerlinien-ogd-linien.csv';
$url_wl_stations = 'http://data.wien.gv.at/csv/wienerlinien-ogd-haltestellen.csv';
$url_wl_platforms = 'http://data.wien.gv.at/csv/wienerlinien-ogd-steige.csv';

$lines_data = download_json($url_lines, 'lines');
$stations_data = download_json($url_stations, 'stations');
$station_id_data = download_json($url_station_ids, 'station_ids');

$wl_lines_data = download_csv($url_wl_lines, 'wl_lines');
$wl_stations_data = download_csv($url_wl_stations, 'wl_stations');
$wl_platforms_data = download_csv($url_wl_platforms, 'wl_platforms');

write_log("Starting import script...");

$imported_lines = array();
$imported_stations = array();
$imported_station_ids = array();
$imported_line_station = array();
$imported_line_segment = array();

import_lines($lines_data);
import_station_ids($station_id_data);
import_stations($stations_data);

check_outdated($imported_lines, 'line', array('id'));
check_outdated($imported_stations, 'station', array('id'));
check_outdated($imported_station_ids, 'station_id', array('id'));
check_outdated($imported_line_station, 'line_station', array('id'));
check_outdated($imported_line_segment, 'line_segment', array('id'));

write_log("Import script successfully completed.");

function check_outdated($current_ids, $table) {
	write_log("Searching for outdated entries in table '$table'...");

	$result = db_query("SELECT id FROM $table");
	foreach($result as $row) {
		if(!in_array($row['id'], $current_ids)) {
			write_log("Found outdated item with id $id");
			db_query('UPDATE $table SET deleted = 1, timestamp_deleted = NOW() WHERE id = ?', array($id));
		}
	}
}

function download_json($url, $prefix) {
	return json_decode(iconv('ISO-8859-15', 'UTF-8', download($url, $prefix, 'json')));
}

function download_csv($url, $prefix) {
	return download($url, $prefix, 'csv');
}

function download($url, $prefix, $extension) {
	global $cache_expiration;

	$cache_dir = dirname(__FILE__) . '/../cache/';
	$timestamp = date('YmdHis');
	$filename = "$cache_dir${prefix}_$timestamp.$extension";

	$dir = opendir($cache_dir);
	$found_file = null;
	$found_timestamp = 0;
	while(($file = readdir($dir)) !== false) {
		if(mb_substr($file, 0, mb_strlen($prefix, 'UTF-8')+1, 'UTF-8') == "${prefix}_") {
			$timestamp = mb_substr($file, mb_strlen($prefix, 'UTF-8')+1, mb_strlen($file, 'UTF-8')-mb_strlen($prefix, 'UTF-8')-2-mb_strlen($extension, 'UTF-8'));
			$date = DateTime::createFromFormat('YmdHis', $timestamp);
			if(!$date) {
				continue;
			}
			$time = $date->getTimestamp();

			if(time() - $time < $cache_expiration && $found_timestamp < $time) {
				$found_file = $file;
				$found_timestamp = $time;
			}
		}
	}
	closedir($dir);

	if($found_file != null) {
		write_log("Using cached file $found_file");

		return file_get_contents("$cache_dir$found_file");
	}

	write_log("Fetching $url to $filename...");

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$data = curl_exec($curl);
	curl_close($curl);
	file_put_contents($filename, $data);

	write_log("Fetching completed");

	return $data;
}

function fetch_line($name) {
	// TODO what if the line does not exist?
	$data = db_query('SELECT id FROM line WHERE name = ? AND deleted = 0', array($name));
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

function process_station($name, $short_name, $lines, $lat, $lon) {
	global $imported_stations;


	$data = db_query('SELECT id FROM station WHERE deleted = 0 AND name = ? AND short_name = ? AND lat = ? AND lon = ?', array($name, $short_name, $lat, $lon));
	if(count($data) == 1) {
		$id = $data[0]['id'];
	}
	else {
		write_log("Added station $name ($short_name, $lines, $lat, $lon)...");

		$data = db_query('SELECT id FROM station_id WHERE name = ?', array($name));
		if(count($data) == 0) {
			db_query('INSERT INTO station (name, short_name, lat, lon, station_id) VALUES (?, ?, ?, ?, NULL)', array($name, $short_name, $lat, $lon));
		}
		else {
			$station_id = $data[0]['id'];
			db_query('INSERT INTO station (name, short_name, lat, lon, station_id) VALUES (?, ?, ?, ?, ?)', array($name, $short_name, $lat, $lon, $station_id));
		}
		$id = db_last_insert_id();
	}
	
	foreach(explode(', ', $lines) as $line) {
		$line_id = fetch_line($line);
		process_line_station($line_id, $id);
	}

	$imported_stations[] = $id;
}

function import_station_ids($data) {
	global $imported_station_ids;

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

function import_stations($data) {
	write_log("Importing stations...");

	foreach($data->features as $feature) {
		process_station($feature->properties->HTXT, $feature->properties->HTXTK, $feature->properties->HLINIEN, $feature->geometry->coordinates[1], $feature->geometry->coordinates[0]);
	}

	write_log("Stations successfully imported.");
}

function import_lines($data) {
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

function write_log($message) {
	global $debug;

	$logfile = dirname(__FILE__) . '/../log/log';
	$timestamp = date('Y-m-d H:i:s');

	$file = fopen($logfile, 'a');
	fputs($file, "[$timestamp] - $message\n");
	fclose($file);

	if($debug) {
		echo "[$timestamp] - $message\n";
	}
}


