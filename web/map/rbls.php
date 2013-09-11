<?php

require_once(dirname(__FILE__) . '/../../lib/common.php');

if(!isset($_REQUEST['ids'])) {
	die();
}
$ids = array_unique(explode(',', $_REQUEST['ids']));
$placeholders = array();
foreach($ids as $id) {
	$placeholders[] = '?';
}
$placeholder_string = implode(',', $placeholders);
$data = db_query("SELECT DISTINCT(rbl) FROM wl_platform WHERE rbl in ($placeholder_string)", $ids);
if(count($data) != count($ids)) {
	// TODO
	die();
}

echo json_encode(fetch_rbls($ids));

/*
$missing_ids = array();
$rbl_data = array();
foreach($ids as $id) {
	// TODO necessary?
	db_query('INSERT INTO active_rbl (rbl) VALUES (?) ON DUPLICATE KEY UPDATE `timestamp` = NOW()', array($id));
	$data = cache_get("rbl_$id");
	if(!$data) {
		$missing_ids[] = $id;
	}
	else {
		$rbl_data[$id] = $data;
	}
}
if(count($missing_ids) > 0) {
	$rbl_data = array_merge($rbl_data, fetch_rbls($data));
}

echo json_encode($rbl_data);
 */
