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

$result = array('segments' => $segments);

header('Content-Type: application/json');
echo json_encode($result);

