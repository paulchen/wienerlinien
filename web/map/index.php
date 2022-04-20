<?php
require_once(dirname(__FILE__) . '/../../lib/common.php');

$lines = array();
$groups = array();
$data = db_query('SELECT id, name FROM line_type ORDER BY pos ASC');
foreach($data as $row) {
	$row['lines'] = array();
	$lines[$row['id']] = $row;
	$groups[$row['id']] = array();
}
$data = db_query('SELECT id, name, type FROM line WHERE deleted=0 ORDER BY name ASC');
$line_ids = array();
foreach($data as $row) {
	$lines[$row['type']]['lines'][] = $row;
	$groups[$row['type']][] = $row['id'];
	$line_ids[] = $row['id'];
}
foreach($lines as $type => &$value) {
	usort($value['lines'], 'line_sorter');
}
unset($value);
usort($data, 'line_sorter');

$line_orders = array();
foreach($data as $row) {
	$line_orders[$row['name']] = count($line_orders);
}

if(isset($_REQUEST['lines']) && $_REQUEST['lines'] != '') {
	$_lines = explode(',', $_REQUEST['lines']);
	foreach($_lines as $id) {
		if(!in_array($id, $line_ids)) {
			// TODO 404
			die();
		}
	}
}

add_static_cache_headers();
require_once("$template_dir/map.php");

