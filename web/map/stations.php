<?php

require_once(dirname(__FILE__) . '/../../lib/common.php');

$query = 'SELECT s.id, s.name, ROUND(s.wl_lat, 4) lat, ROUND(s.wl_lon, 4) lon, COUNT(DISTINCT l.id) line_count
	FROM `station` s
		JOIN wl_platform p ON (s.id = p.station)
		JOIN line l ON (p.line = l.id)
	WHERE s.deleted = 0
		AND p.deleted = 0
		AND l.deleted = 0
		AND municipality = 1
	GROUP BY s.id, s.name, s.wl_lat, s.wl_lon
	ORDER BY line_count DESC, s.name ASC';
$data = db_query($query);

header('Content-Type: application/json');
echo json_encode($data);

