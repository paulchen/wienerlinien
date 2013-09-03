<?php
require_once(dirname(__FILE__) . '/../../lib/common.php');

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
$groups = array();
$data = db_query('SELECT id, name FROM line_type ORDER BY pos ASC');
foreach($data as $row) {
	$row['lines'] = array();
	$lines[$row['id']] = $row;
	$groups[$row['id']] = array();
}
$data = db_query('SELECT id, name, type FROM line ORDER BY name ASC');
$line_ids = array();
foreach($data as $row) {
	$lines[$row['type']]['lines'][] = $row;
	$groups[$row['type']][] = $row['id'];
	$line_ids[] = $row['id'];
}
foreach($lines as $type => &$value) {
	usort($value['lines'], 'line_sorter');
}

if(isset($_REQUEST['lines'])) {
	$_lines = explode(',', $_REQUEST['lines']);
	foreach($_lines as $id) {
		if(!in_array($id, $line_ids)) {
			// TODO 404
			die();
		}
	}
}

require_once("$template_dir/map.php");

