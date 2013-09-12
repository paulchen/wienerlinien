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

$platforms = db_query("SELECT s.name station_name, p.rbl rbl,
			GROUP_CONCAT(DISTINCT l.name ORDER BY wl_order ASC SEPARATOR ',') line_names,
			GROUP_CONCAT(DISTINCT l.id ORDER BY wl_order ASC SEPARATOR ',') line_ids
		FROM station s
			LEFT JOIN wl_platform p ON (s.id = p.station)
			LEFT JOIN line l ON (p.line = l.id)
		WHERE s.station_id = ?
		GROUP BY p.rbl
		ORDER BY wl_order ASC", array($station_id));
$station_name = $platforms[0]['station_name'];
foreach($platforms as &$platform) {
	$platform['line_names'] = explode(',', $platform['line_names']);
	$platform['line_ids'] = explode(',', $platform['line_ids']);
}
unset($platform);

$rbls = array_values(array_unique(array_filter(array_map(function($a) { return $a['rbl']; }, $platforms))));

require_once("$template_dir/station.php");

