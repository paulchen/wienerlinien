#!/usr/bin/php
<?php

if(!isset($argv)) {
	die();
}

require_once(dirname(__FILE__) . '/../lib/common.php');

$lockfile = fopen($disruptions_lockfile, 'w');
if(!flock($lockfile, LOCK_EX + LOCK_NB)) {
	$categories = cache_get('disruptions_munin');
	fclose($lockfile);
}
else {
	fclose($lockfile);
	unlink($disruptions_lockfile);
}

if(!isset($categories) || $categories == null) {
	$categories = db_query('SELECT id, title FROM traffic_info_category ORDER BY id ASC');
	cache_set('disruptions_munin', $categories, 3600);
}

if(isset($argv[1])) {
	if($argv[1] == 'autoconf') {
		echo "yes\n";
	}
	else if($argv[1] == 'config') {
		echo "graph_title Anzahl Störungen\n";
		echo "graph_args -l 0\n";
		echo "graph_vlabel Störungen\n";
		echo "graph_category other\n";
		echo "graph_info Anzahl Störungen im Netz der Wiener Linien\n";
		echo "disruptions_total.label Gesamt\n";
		echo "disruptions_total.info Gesamtanzahl der Störungen\n";

		foreach($categories as $category) {
			echo "disruptions{$category['id']}.label Typ {$category['id']}\n";
			echo "disruptions{$category['id']}.info {$category['title']}\n";
		}
	}
	die();
}

$disruptions = get_disruptions(array('limit' => -1));
echo 'disruptions_total.value ' . count($disruptions) . "\n";
foreach($categories as $category) {
	$disruption_count = 0;
	foreach($disruptions as $disruption) {
		if($disruption['category_id'] == $category['id']) {
			$disruption_count++;
		}
	}
	echo "disruptions{$category['id']}.value $disruption_count\n";
}

