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

$platforms = db_query('SELECT s.name station_name, p.rbl rbl, l.name line_name, l.id line_id
		FROM station s
			LEFT JOIN wl_platform p ON (s.id = p.station)
			LEFT JOIN line l ON (p.line = l.id)
		WHERE s.station_id = ?
		ORDER BY l.wl_order ASC, direction ASC', array($station_id));
$station_name = $platforms[0]['station_name'];
$rbls = array_values(array_unique(array_filter(array_map(function($a) { return $a['rbl']; }, $platforms))));

require_once("$template_dir/station.php");

