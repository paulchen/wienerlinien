<?php
require_once(dirname(__FILE__) . '/../../lib/common.php');

$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
if(!preg_match('/^[0-9]+$/', $page)) {
	$page = 1;
}

if(isset($_REQUEST['id'])) {
	$data = db_query('SELECT `group` FROM traffic_info WHERE id = ?', array($_REQUEST['id']));
	if($data[0]['group']) {
		$disruptions = get_disruptions(array('group' => $data[0]['group']));
	}
	else {
		$disruptions = get_disruptions(array('id' => $_REQUEST['id']));
	}
}
else if(isset($_REQUEST['archive'])) {
	$settings = array(
		'archive' => $_REQUEST['archive'],
		'page' => $page
	);
	if(isset($_REQUEST['lines'])) {
		$lines = array_unique(explode(',', $_REQUEST['lines']));
		$parameters = array();
		foreach($lines as $line) {
			$parameters[] = '?';
		}
		$parameters_string = implode(',', $parameters);
		$query = "SELECT id, name FROM line WHERE id IN ($parameters_string)";
		$data = db_query($query, $lines);
		if(count($data) != count($lines)) {
			// TODO
			die();
		}

		$settings['lines'] = $lines;
	}
	if(isset($_REQUEST['from'])) {
		if(!preg_match('/^[0-9]+$/', $_REQUEST['from'])) {
			// TODO
			die();
		}

		$settings['from'] = $_REQUEST['from'];
	}
	if(isset($_REQUEST['to'])) {
		if(!preg_match('/^[0-9]+$/', $_REQUEST['to'])) {
			// TODO
			die();
		}

		$settings['to'] = $_REQUEST['to'];
	}
	if(isset($_REQUEST['types'])) {
		$types = array_unique(explode(',', $_REQUEST['types']));
		$parameters = array();
		foreach($types as $type) {
			$parameters[] = '?';
		}
		$parameters_string = implode(',', $parameters);
		$query = "SELECT id, name FROM traffic_info_category WHERE id IN ($parameters_string)";
		$data = db_query($query, $types);
		if(count($data) != count($types)) {
			// TODO
			die();
		}

		$settings['types'] = $types;
	}

	$disruptions = get_disruptions($settings, $pagination_data);
}
else {
	$disruptions = get_disruptions(array('page' => $page), $pagination_data);
}
foreach($disruptions as &$disruption) {
	foreach($disruption['lines'] as $line) {
		$disruption['title'] = str_replace("$line ", '', $disruption['title']);
	}
}
unset($disruption);

if(isset($pagination_data)) {
	$pagination_names = array(
		'first' => 'Erste Seite',
		'previous' => 'Vorherige Seite',
		'next' => 'Nächste Seite',
		'last' => 'Letzte Seite'
	);
	$request_vars = $_REQUEST;
	foreach($pagination_data as $name => &$item) {
		if(isset($pagination_data['current'])) {
			unset($pagination_data['current']);
		}
		$request_vars['page'] = $item;
		$url_parts = array();
		foreach($request_vars as $key => $value) {
			$url_parts[] = urlencode($key) . '=' . urlencode($value);
		}
		$item = array(
			'name' => $pagination_names[$name],
			'url' => '?' . implode('&amp;', $url_parts)
		);
	}
	unset($item);
}

require_once("$template_dir/disruptions_html.php");

