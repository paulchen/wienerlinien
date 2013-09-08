<?php
require_once(dirname(__FILE__) . '/../../lib/common.php');

$disruptions = get_disruptions();
$feed_date = 0;
foreach($disruptions as $disruption) {
	$feed_date = max($max_time, $disruption['start_time']);
}

$link = "https://{$_SERVER['SERVER_NAME']}" . dirname($_SERVER['PHP_SELF']) . '/';

header('Content-Type: application/rss+xml; charset=UTF-8');

require_once("$template_dir/disruptions_rss.php");

