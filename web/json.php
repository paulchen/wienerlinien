<?php

require_once(dirname(__FILE__) . '/../lib/common.php');

if(!isset($_REQUEST['line'])) {
	die();
}

// TODO check for line being an integer number > 0
// TODO check for non-existance of line (move queries for type and colors up here)

// TODO replace $_REQUEST['line'] by $line in all queries
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

$data = db_query('SELECT type FROM line WHERE id = ?', array($_REQUEST['line']));
$type = $data[0]['type'];

$data = db_query('SELECT color, line_thickness FROM line_type WHERE id = ?', array($type));
$color = $data[0]['color'];
$line_thickness = $data[0]['line_thickness'];

$data = db_query('SELECT color FROM line_color WHERE line = ?', array($_REQUEST['line']));
if(count($data) == 1) {
	$color = $data[0]['color'];
}

$result = array('segments' => $segments, 'color' => $color, 'line_thickness' => $line_thickness);

header('Content-Type: application/json');
echo json_encode($result);

