<?php

require_once(dirname(__FILE__) . '/../lib/common.php');

if(!isset($_REQUEST['line'])) {
	die();
}

$data = db_query('SELECT sp1.lat lat1, sp1.lon lon1, sp2.lat lat2, sp2.lon lon2
		FROM line l
			JOIN line_segment ls ON (l.id = ls.line)
			JOIN segment s ON (ls.segment = s.id)
			JOIN segment_point sp1 ON (s.point1 = sp1.id)
			JOIN segment_point sp2 ON (s.point2 = sp2.id)
		WHERE l.id = ?', array($_REQUEST['line']));
$result = array();
foreach($data as $row) {
	$result[] = array(array($row['lat1'], $row['lon1']), array($row['lat2'], $row['lon2']));
}
unset($data);

header('Content-Type: application/json');
echo json_encode($result);

