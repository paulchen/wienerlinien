<?php

require_once(dirname(__FILE__) . '/../../lib/common.php');

$query = "SELECT s.id, s.name, ROUND(AVG(ws.wl_lat), 4) lat, ROUND(AVG(ws.wl_lon), 4) lon, COUNT(DISTINCT l.id) line_count
	FROM `station` s
		JOIN wl_platform p ON (s.id = p.station)
		JOIN line l ON (p.line = l.id)
		JOIN wl_station ws ON (ws.station = s.id)
	WHERE s.deleted = 0
		AND p.deleted = 0
		AND l.deleted = 0
		AND municipality IN (SELECT id FROM municipality WHERE name = 'Wien')
	GROUP BY s.id, s.name
	ORDER BY line_count DESC, s.name ASC";
$data = db_query($query);

header('Content-Type: application/json');
echo json_encode($data);

