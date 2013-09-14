<?php
require_once(dirname(__FILE__) . '/../../lib/common.php');

$disruptions = get_disruptions(array('limit' => -1));
$feed_date = 0;
foreach($disruptions as &$disruption) {
	$feed_date = max($feed_date, $disruption['start_time']);

	foreach($disruption['lines'] as $line) {
		$disruption['title'] = str_replace("$line ", '', $disruption['title']);
	}
}
unset($disruption);

$link = "https://{$_SERVER['SERVER_NAME']}" . dirname($_SERVER['PHP_SELF']) . '/';

header('Content-Type: application/rss+xml; charset=UTF-8');

require_once("$template_dir/disruptions_rss.php");

