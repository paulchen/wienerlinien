<?php

require_once(dirname(__FILE__) . '/../../lib/common.php');

if(!isset($_REQUEST['id'])) {
	// TODO 404
	die();
}
$data = get_station_data($_REQUEST['id']);
if(!$data) {
	// TODO 404
	die();
}
$station_name = $data['name'];
$platforms = $data['platforms'];

$rbls = array_values(array_unique(array_filter(array_map(function($a) { return $a['rbl']; }, $platforms))));

add_static_cache_headers();
require_once("$template_dir/station.php");

