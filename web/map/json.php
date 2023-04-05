<?php

require_once(dirname(__FILE__) . '/../../lib/common.php');

if(!isset($_REQUEST['lines'])) {
	die();
}

$line_ids = explode(',', $_REQUEST['lines']);
$placeholder_array = array();
foreach($line_ids as $line_id) {
	if(!preg_match('/^[0-9]+$/', $line_id)) {
		// TODO HTTP response: illegal request
		die();
	}
	$placeholder_array[] = '?';
}

$placeholders = implode(', ', $placeholder_array);

$data = db_query("SELECT l.id id, l.name name, COALESCE(c.color, t.color) color, t.line_thickness
		FROM line l
			LEFT JOIN line_color c ON (l.id = c.line)
			JOIN line_type t ON (l.type = t.id)
		WHERE l.deleted = 0
			AND l.id IN ($placeholders)", $line_ids);
if(count($data) != count($line_ids)) {
	// TODO HTTP response: not found
	die();
}
$line_data = array();
foreach($data as $row) {
	$line_data[$row['id']] = $row;
}

$result = array();
foreach($line_ids as $line_id) {
	$data = db_query('SELECT sp1.lat lat1, sp1.lon lon1, sp2.lat lat2, sp2.lon lon2
			FROM line l
				JOIN line_segment ls ON (l.id = ls.line)
				JOIN segment s ON (ls.segment = s.id)
				JOIN segment_point sp1 ON (s.point1 = sp1.id)
				JOIN segment_point sp2 ON (s.point2 = sp2.id)
			WHERE l.id = ?
				AND l.deleted = 0
				AND ls.deleted = 0', array($line_id));
	$segments = array();
	foreach($data as $row) {
		$segments[] = array(array($row['lat1'], $row['lon1']), array($row['lat2'], $row['lon2']));
	}
	unset($data);

	$changed = true;
	while($changed) {
		$changed = false;

		for($a=0; $a<count($segments); $a++) {
			for($b=0; $b<count($segments); $b++) {
				if($a == $b) {
					continue;
				}

				if($segments[$a][count($segments[$a])-1][0] == $segments[$b][0][0] && $segments[$a][count($segments[$a])-1][1] == $segments[$b][0][1]) {
					for($c=1; $c<count($segments[$b]); $c++) {
						$segments[$a][] = $segments[$b][$c];
					}

					unset($segments[$b]);
					$segments = array_values($segments);
					$changed = true;
					continue 3;
				}
			}
		}
	}

	$data = db_query('SELECT s.id id, s.name name, p.direction direction, p.pos pos, p.lat, p.lon
		FROM wl_platform p
			JOIN station s ON (p.station = s.id)
		WHERE p.line = ?
			AND p.deleted = 0
			AND s.deleted = 0
		ORDER BY p.direction ASC, p.pos ASC', array($line_id));
	$stations = array();
	$known_station_ids = array();
	foreach($data as $row) {
		$station_id = $row['id'];
		if(in_array($station_id, $known_station_ids)) {
			continue;
		}
		$known_station_ids[] = $station_id;

		unset($row['direction']);
		unset($row['pos']);
		$stations[] = $row;
	}

	$result[] = array('line' => $line_id, 'name' => $line_data[$line_id]['name'], 'segments' => $segments, 'color' => $line_data[$line_id]['color'], 'line_thickness' => $line_data[$line_id]['line_thickness'], 'stations' => $stations);
}

header('Content-Type: application/json');
add_static_cache_headers();
echo json_encode($result);

