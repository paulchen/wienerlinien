<?php

require_once(dirname(__FILE__) . '/../../lib/common.php');

if(!isset($_REQUEST['id'])) {
	// TODO 404
	die();
}
$data = get_disruptions_for_station($_REQUEST['id']);

header('Content-Type: application/json');
echo(json_encode($data));


