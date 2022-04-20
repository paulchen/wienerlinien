<?php

require_once(dirname(__FILE__) . '/../../lib/common.php');

$query = "SELECT s.id, s.name, ROUND(AVG(ws.wl_lat), 4) lat, ROUND(AVG(ws.wl_lon), 4) lon, COUNT(DISTINCT l.id) line_count, GROUP_CONCAT(DISTINCT l.id SEPARATOR ',') line_list
	FROM `station` s
		JOIN wl_platform p ON (s.id = p.station)
		JOIN line l ON (p.line = l.id)
		JOIN wl_station ws ON (ws.station = s.id)
	WHERE s.deleted = 0
		AND p.deleted = 0
		AND l.deleted = 0
	GROUP BY s.id, s.name
	ORDER BY line_count DESC, s.name ASC";
$stations = db_query($query);
foreach ($stations as &$station) {
	$station['line_list'] = explode(',', $station['line_list']);
	unset($station['line_count']);
}
unset($station);

$line_types = db_query('SELECT id, color FROM line_type ORDER BY id ASC');
$lines = db_query('SELECT id, name, type, color FROM line l LEFT JOIN line_color lc ON (l.id = lc.line) WHERE deleted = 0 ORDER BY id ASC');
foreach($lines as &$line) {
	if(!$line['color']) {
		unset($line['color']);
	}
}
unset($line);
usort($lines, function($a, $b) { return line_sorter($a['name'], $b['name']); });

$data = array(
	'line_types' => $line_types,
	'lines' => $lines,
	'stations' => $stations,
);

header('Content-Type: application/json');
add_static_cache_headers();
echo json_encode($data, JSON_NUMERIC_CHECK);

