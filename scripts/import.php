<?php

// TODO check: only run as standalone script from command line

$url_stations = 'http://data.wien.gv.at/daten/wfs?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:OEFFHALTESTOGD&srsName=EPSG:4326&outputFormat=json';
$url_lines = 'http://data.wien.gv.at/daten/wfs?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:OEFFLINIENOGD&srsName=EPSG:4326&outputFormat=json';

$stations_data = download($url_stations, 'stations');
$lines_data = download($url_lines, 'lines');

write_log("Starting import script...");

// TODO check for existing files, do not refetch if a sufficiently new file exists

import_stations($stations_data);
import_lines($lines_data);

write_log("Import script successfully completed.");

function download($url, $prefix) {
	$cache_dir = dirname(__FILE__) . '/../cache/';
	$timestamp = date('YmdHi');
	$filename = "$cache_dir${prefix}_$timestamp.json";

	write_log("Fetching $url to $filename...");

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$data = curl_exec($curl);
	curl_close($curl);
	file_put_contents($filename, $data);

	write_log("Fetching completed");
}

function import_stations($data) {
	write_log("Importing stations...");

	// TODO
	
	write_log("Stations successfully imported.");
}

function import_lines($data) {
	write_log("Importing lines...");

	// TODO

	write_log("Lines successfully imported.");
}

function write_log($message) {
	$logfile = dirname(__FILE__) . '/../log/log';
	$timestamp = date('Y-m-d H:i:s');

	$file = fopen($logfile, 'a');
	fputs($file, "[$timestamp] - $message\n");
	fclose($file);
}


