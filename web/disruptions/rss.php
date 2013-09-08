<?php
require_once(dirname(__FILE__) . '/../../lib/common.php');

$disruptions = get_disruptions();
$link = "https://{$_SERVER['SERVER_NAME']}" . dirname($_SERVER['PHP_SELF']) . '/';

header('Content-Type: application/rss+xml; charset=UTF-8');

require_once("$template_dir/disruptions_rss.php");

