<?php 
require_once(dirname(__FILE__) . '/../../lib/common.php');

if(!isset($_REQUEST['id'])) {
	// TODO 404
	die();
}
$line_id = $_REQUEST['id'];

$data = db_query('SELECT l.name line_name, s.id id, s.name name, p.direction direction, p.pos pos, p.lat, p.lon
	FROM wl_platform p
		JOIN station s ON (p.station = s.id)
		JOIN line l ON (l.id = p.line)
	WHERE l.id = ?
		AND l.deleted = 0
		AND s.deleted = 0
		AND p.deleted = 0
	ORDER BY p.direction ASC, p.pos ASC', array($line_id));
$stations = array();
$known_station_ids = array();
foreach($data as $row) {
	$line_name = $row['line_name'];

	$station_id = $row['id'];
	if(in_array($station_id, $known_station_ids)) {
		continue;
	}
	$known_station_ids[] = $station_id;

	unset($row['direction']);
	unset($row['pos']);
	$stations[] = $row;
}

$directions_difference = false;
for($a=0; $a<count($data)/2; $a++) {
	if($data[$a]['id'] != $data[count($data)-1-$a]['id']) {
		$directions_difference = true;
		break;
	}
}

$routes = array(array());
if($directions_difference) {
	$routes[] = array();
}
$previous_ids = array(0, 0);
foreach($data as $row) {
	$direction = $row['direction'];
	$row['first'] = false;
	$row['last'] = true;

	if($direction == 1) {
		if($previous_ids[0] == $row['id']) {
			continue;
		}
		$previous_ids[0] = $row['id'];

		if(count($routes[0]) == 0) {
			$row['first'] = true;
		}
		else {
			$routes[0][count($routes[0])-1]['last'] = false;
		}
		$routes[0][] = $row;
	}
	else if($directions_difference) {
		if($previous_ids[1] == $row['id']) {
			continue;
		}
		$previous_ids[1] = $row['id'];

		if(count($routes[1]) == 0) {
			$row['first'] = true;
		}
		else {
			$routes[1][count($routes[1])-1]['last'] = false;
		}
		$routes[1][] = $row;
	}
}

// print_r($routes);
// print_r($stations);
// print_r($data);

require_once("$template_dir/line.php");

