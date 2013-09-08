<?php
require_once(dirname(__FILE__) . '/../../lib/common.php');

$disruptions = get_disruptions();

require_once("$template_dir/disruptions_html.php");

