<?php
require_once(dirname(__FILE__) . '/../../lib/common.php');

if(isset($_REQUEST['id'])) {
	$disruptions = get_disruptions(array('id' => $_REQUEST['id']));
}
else {
	$disruptions = get_disruptions();
}

require_once("$template_dir/disruptions_html.php");

