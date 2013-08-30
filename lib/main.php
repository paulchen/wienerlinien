<?php

require_once(dirname(__FILE__) . '/common.php');

function line_sorter($a, $b) {
	preg_match('/^([A-Z]*)([0-9]*)([A-Z]*)$/', $a['name'], $matches_a);
	preg_match('/^([A-Z]*)([0-9]*)([A-Z]*)$/', $b['name'], $matches_b);

	if($matches_a[1] != '' && $matches_b[1] == '') {
		return -1;
	}
	if($matches_a[1] != '' && $matches_b[1] != '' && $matches_a[1] < $matches_b[1]) {
		return -1;
	}
	if($matches_a[1] != '' && $matches_b[1] != '' && $matches_a[1] > $matches_b[1]) {
		return 1;
	}

	if(intval($matches_a[2]) < intval($matches_b[2])) {
		return -1;
	}
	if(intval($matches_a[2]) > intval($matches_b[2])) {
		return 1;
	}

	if($matches_a[3] < $matches_b[3]) {
		return -1;
	}
	if($matches_a[3] > $matches_b[3]) {
		return 1;
	}

	return 0;
}

$lines = array();
$data = db_query('SELECT id, name FROM line_type ORDER BY pos ASC');
foreach($data as $row) {
	$lines[$row['id']] = array('name' => $row['name'], 'lines' => array());
}
$data = db_query('SELECT id, name, type FROM line ORDER BY name ASC');
foreach($data as $row) {
	$lines[$row['type']]['lines'][] = $row;
}
foreach($lines as $type => &$value) {
	usort($value['lines'], 'line_sorter');
}

require_once("$template_dir/index.php");

