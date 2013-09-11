<?php

require_once(dirname(__FILE__) . '/../../lib/common.php');

if(!isset($_REQUEST['id'])) {
	// TODO 404
	die();
}

$data = db_query('SELECT station_id FROM station WHERE id = ?', array($_REQUEST['id']));
if(count($data) != 1) {
	// TODO 404
	die();
}
$station_id = $data[0]['station_id'];

$stations = db_query('SELECT name FROM station WHERE station_id = ?', array($station_id));
$station_name = $stations[0]['name'];

require_once("$template_dir/station.php");

