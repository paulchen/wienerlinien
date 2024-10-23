<?php

require_once(dirname(__FILE__) . '/../../lib/common.php');

if (isset($_REQUEST['id'])) {
	$data = get_disruptions_for_station($_REQUEST['id']);
}
else if (isset($_REQUEST['lines'])) {
	$data = get_disruptions_for_lines(explode(',', $_REQUEST['lines']));
}
else {
	// TODO 404
	die();
}

header('Content-Type: application/json');
add_additional_json_headers();
echo(json_encode($data));


