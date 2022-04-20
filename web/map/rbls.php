<?php

require_once(dirname(__FILE__) . '/../../lib/common.php');

if(!isset($_REQUEST['ids'])) {
	die();
}
$ids = array_filter(array_unique(array_map("trim", explode(',', $_REQUEST['ids']))), function($id) { return $id != ''; });
if(count($ids) > 0) {
	$placeholders = array();
	foreach($ids as $id) {
		$placeholders[] = '?';
	}
	$placeholder_string = implode(',', $placeholders);
	$data = db_query("SELECT DISTINCT(rbl) FROM wl_platform WHERE rbl in ($placeholder_string) AND deleted = 0", $ids);
	if(count($data) != count($ids)) {
		// TODO
		die();
	}
}

header('Content-Type: application/json');
add_static_cache_headers();
echo json_encode(fetch_rbls($ids));

