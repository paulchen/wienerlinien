<?php
require_once(dirname(__FILE__) . '/../../lib/common.php');

if(isset($_REQUEST['id'])) {
	$data = db_query('SELECT `group` FROM traffic_info WHERE id = ?', array($_REQUEST['id']));
	if($data[0]['group']) {
		$disruptions = get_disruptions(array('group' => $data[0]['group']));
	}
	else {
		$disruptions = get_disruptions(array('id' => $_REQUEST['id']));
	}
}
else {
	$disruptions = get_disruptions();
}

require_once("$template_dir/disruptions_html.php");

