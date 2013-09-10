<?php
require_once(dirname(__FILE__) . '/../../lib/common.php');

$disruptions = get_disruptions();
$feed_date = 0;
foreach($disruptions as &$disruption) {
	$feed_date = max($feed_date, $disruption['start_time']);

	if(count($disruption['lines']) == 1) {
		$disruption['title'] = str_replace($disruption['lines'][0] . ' ', '', $disruption['title']);
	}
}
unset($disruption);

$link = "https://{$_SERVER['SERVER_NAME']}" . dirname($_SERVER['PHP_SELF']) . '/';

header('Content-Type: application/rss+xml; charset=UTF-8');

require_once("$template_dir/disruptions_rss.php");

