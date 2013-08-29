<?php

require_once(dirname(__FILE__) . '/common.php');

$lines = array();
$data = db_query('SELECT id, name FROM line_type');
foreach($data as $row) {
	$lines[$row['id']] = array('name' => $row['name'], 'lines' => array());
}
$data = db_query('SELECT id, name, type FROM line ORDER BY name ASC');
foreach($data as $row) {
	$lines[$row['type']]['lines'][] = $row;
}

require_once("$template_dir/index.php");

