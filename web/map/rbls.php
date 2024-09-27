<?php

require_once(dirname(__FILE__) . '/../../lib/common.php');

if(!isset($_REQUEST['ids'])) {
	die();
}
$ids = array_filter(array_unique(array_map("trim", explode(',', $_REQUEST['ids']))), function($id) { return $id != ''; });
foreach($ids as $id) {
	if(!preg_match('/^[0-9]*$/', $id)) {
		http_response_code(400);
		die('Bad request');
	}
}
if(count($ids) > 0) {
	$placeholders = array();
	foreach($ids as $id) {
		$placeholders[] = '?';
	}
	$placeholder_string = implode(',', $placeholders);
	$data = db_query("SELECT DISTINCT(rbl) FROM wl_platform WHERE rbl in ($placeholder_string) AND deleted = 0", $ids);
	if(count($data) != count($ids)) {
		http_response_code(400);
		die('Bad request');
	}
}

header('Content-Type: application/json');
header('Cache-Control: max-age=0');
echo json_encode(fetch_rbls($ids));

