<?php
require_once(dirname(__FILE__) . '/../../lib/common.php');

$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
if(!preg_match('/^[0-9]+$/', $page)) {
	$page = 1;
}

$filtered_archive = false;
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
	$filter_settings = array(
		'archive' => $_REQUEST['archive'],
		'page' => $page
	);
	$filter_strings = array();
	if(isset($_REQUEST['lines'])) {
		$lines = array_unique(explode(',', $_REQUEST['lines']));
		$parameters = array();
		foreach($lines as $line) {
			$parameters[] = '?';
		}
		$parameters_string = implode(',', $parameters);
		$query = "SELECT name FROM line WHERE id IN ($parameters_string)";
		$line_names = db_query($query, $lines);
		if(count($line_names) != count($lines)) {
			// TODO
			die();
		}

		$line_names = array_map(function($a) { return $a['name']; }, $line_names);
		usort($line_names, 'line_sorter');
		if(count($line_names) == 1) {
			$filter_strings[] = 'Linie: ' . implode(', ', $line_names);
		}
		else {
			$filter_strings[] = 'Linien: ' . implode(', ', $line_names);
		}

		$filter_settings['lines'] = $lines;
		$filtered_archive = true;
	}
	if(isset($_REQUEST['from'])) {
		if(!preg_match('/^[0-9]+$/', $_REQUEST['from'])) {
			// TODO
			die();
		}

		$filter_settings['from'] = $_REQUEST['from'];
		$filtered_archive = true;

		$filter_strings[] = 'Beginnzeitpunkt: ' . date('d.m.Y H:i:s', $_REQUEST['from']);
	}
	if(isset($_REQUEST['to'])) {
		if(!preg_match('/^[0-9]+$/', $_REQUEST['to'])) {
			// TODO
			die();
		}

		$filter_settings['to'] = $_REQUEST['to'];
		$filtered_archive = true;

		$filter_strings[] = 'Endzeitpunkt: ' . date('d.m.Y H:i:s', $_REQUEST['to']);
	}
	if(isset($_REQUEST['types'])) {
		$types = array_unique(explode(',', $_REQUEST['types']));
		$parameters = array();
		foreach($types as $type) {
			$parameters[] = '?';
		}
		$parameters_string = implode(',', $parameters);
		$query = "SELECT title FROM traffic_info_category WHERE id IN ($parameters_string) ORDER BY id ASC";
		$category_names = db_query($query, $types);
		if(count($category_names) != count($types)) {
			// TODO
			die();
		}

		$category_names = array_map(function($a) { return $a['title']; }, $category_names);
		if(count($category_names) == 1) {
			$filter_strings[] = 'Kategorie: ' . implode(', ', $category_names);
		}
		else {
			$filter_strings[] = 'Kategorien: ' . implode(', ', $category_names);
		}

		$filter_settings['types'] = $types;
		$filtered_archive = true;
	}

	$disruptions = get_disruptions($filter_settings, $pagination_data);
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

