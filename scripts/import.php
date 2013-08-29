<?php

// TODO check: only run as standalone script from command line

// TODO -> config file
$debug = true;

$url_stations = 'http://data.wien.gv.at/daten/wfs?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:OEFFHALTESTOGD&srsName=EPSG:4326&outputFormat=json';
$url_lines = 'http://data.wien.gv.at/daten/wfs?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:OEFFLINIENOGD&srsName=EPSG:4326&outputFormat=json';

$stations_data = download($url_stations, 'stations');
$lines_data = download($url_lines, 'lines');

write_log("Starting import script...");

// TODO check for existing files, do not refetch if a sufficiently new file exists

$imported_stations = array();
$imported_lines = array();
$imported_line_station = array();
$imported_line_segment = array();

import_lines($lines_data);
import_stations($stations_data);

write_log("Import script successfully completed.");

function download($url, $prefix) {
	$cache_dir = dirname(__FILE__) . '/../cache/';
	$timestamp = date('YmdHis');
	$filename = "$cache_dir${prefix}_$timestamp.json";

	$dir = opendir($cache_dir);
	$found_file = null;
	$found_timestamp = 0;
	while(($file = readdir($dir)) !== false) {
		if(mb_substr($file, 0, mb_strlen($prefix, 'UTF-8')+1, 'UTF-8') == "${prefix}_") {
			$timestamp = mb_substr($file, mb_strlen($prefix, 'UTF-8')+1, mb_strlen($file, 'UTF-8')-mb_strlen($prefix, 'UTF-8')-6);
			$date = DateTime::createFromFormat('YmdHis', $timestamp);
			$time = $date->getTimestamp();

			// TODO customizable threshold for outdated data
			if(time() - $time < 3600 && $found_timestamp < $time) {
				$found_file = $file;
				$found_timestamp = $time;
			}
		}
	}
	closedir($dir);

	if($found_file != null) {
		write_log("Using cached file $found_file");

		$data = file_get_contents("$cache_dir$found_file");

		return json_decode(iconv('ISO-8859-15', 'UTF-8', $data));
	}

	write_log("Fetching $url to $filename...");

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$data = curl_exec($curl);
	curl_close($curl);
	file_put_contents($filename, $data);

	write_log("Fetching completed");

	return json_decode(iconv('ISO-8859-15', 'UTF-8', $data));
}

function fetch_line($name) {
	// TODO
}

function process_line($name, $type) {
	// TODO
}

function process_line_station($line, $station) {
	// TODO
}

function process_station($name, $short_name, $lines, $lat, $lon) {
	global $imported_stations;

	write_log("Processing station $name ($short_name, $lines, $lat, $lon)...");

	// TODO query for station with $name, $short_name, $lat, $lon
	// TODO add new station
	
	foreach(explode(', ', $lines) as $line) {
		$line_id = fetch_line($line);
//		process_line_station($line_id, $id);
	}

	// TODO
	// $imported_stations[] = $id;
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

	// TODO

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


