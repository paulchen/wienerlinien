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

header('Content-Type: application/json');
add_static_cache_headers();
add_additional_json_headers();
echo(json_encode($data));


