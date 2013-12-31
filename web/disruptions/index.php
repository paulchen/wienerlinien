<?php
$request_changed = false;
foreach($_REQUEST as $key => &$value) {
	if(is_array($value)) {
		$value = implode(',', $value);
		$request_changed = true;
	}
}
unset($value);
if($request_changed) {
	$redirect_parts = array();
	foreach($_REQUEST as $key => $value) {
		$redirect_parts[] = urlencode($key) . '=' . urlencode($value);
	}
	$redirect_url = '?' . implode('&', $redirect_parts);
	header("Location: $redirect_url");
	die();
}

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
	$selected_types = array();
	$selected_lines = array();

	$lines = array();
	$data = db_query('SELECT id, name FROM line_type WHERE id IN (1,2,4,8) ORDER BY pos ASC');
	foreach($data as $row) {
		$row['lines'] = array();
		$lines[$row['id']] = $row;
	}
	$data = db_query('SELECT id, name, type FROM line ORDER BY name ASC');
	$line_ids = array();
	foreach($data as $row) {
		if(!isset($lines[$row['type']])) {
			continue;
		}
		$lines[$row['type']]['lines'][] = $row;
	}
	foreach($lines as $type => &$value) {
		usort($value['lines'], 'line_sorter');
	}
	unset($value);

	$filter_settings = array(
		'archive' => $_REQUEST['archive'],
		'page' => $page
	);
	$filter_strings = array();
	if(isset($_REQUEST['lines'])) {
		$selected_lines = array_unique(explode(',', $_REQUEST['lines']));
		$parameters = array();
		foreach($selected_lines as $line) {
			$parameters[] = '?';
		}
		$parameters_string = implode(',', $parameters);
		$query = "SELECT name FROM line WHERE id IN ($parameters_string)";
		$line_names = db_query($query, $selected_lines);
		if(count($line_names) != count($selected_lines)) {
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

		$filter_settings['lines'] = $selected_lines;
		$filtered_archive = true;
	}
	if(isset($_REQUEST['from']) && trim($_REQUEST['from']) != '') {
		if(!preg_match('/^[0-9]+$/', $_REQUEST['from'])) {
			$datetime = DateTime::createFromFormat('d.m.Y H:i', $_REQUEST['from']);
			if(!$datetime) {
				// TODO
				die();
			}
			$_REQUEST['from'] = $datetime->format('U');
		}

		$filter_settings['from'] = $_REQUEST['from'];
		$filtered_archive = true;

		$filter_strings[] = 'Beginnzeitpunkt: ' . date('d.m.Y H:i', $_REQUEST['from']);
	}
	else if(isset($_REQUEST['from'])) {
		unset($_REQUEST['from']);
	}
	if(isset($_REQUEST['to']) && trim($_REQUEST['to']) != '') {
		if(!preg_match('/^[0-9]+$/', $_REQUEST['to'])) {
			$datetime = DateTime::createFromFormat('d.m.Y H:i', $_REQUEST['to']);
			if(!$datetime) {
				// TODO
				die();
			}
			$_REQUEST['to'] = $datetime->format('U');
		}

		$filter_settings['to'] = $_REQUEST['to'];
		$filtered_archive = true;

		$filter_strings[] = 'Endzeitpunkt: ' . date('d.m.Y H:i', $_REQUEST['to']);
	}
	else if(isset($_REQUEST['to'])) {
		unset($_REQUEST['to']);
	}
	if(isset($_REQUEST['text'])) {
		$filter_settings['text'] = $_REQUEST['text'];
		$filter_strings[] = 'Enthaltener Text: &quot;' . htmlentities($_REQUEST['text'], ENT_QUOTES, 'UTF-8') . '&quot;';
	}
	if(isset($_REQUEST['types'])) {
		$selected_types = array_unique(explode(',', $_REQUEST['types']));
		$parameters = array();
		foreach($selected_types as $type) {
			$parameters[] = '?';
		}
		$parameters_string = implode(',', $parameters);
		$query = "SELECT title FROM traffic_info_category WHERE id IN ($parameters_string) ORDER BY id ASC";
		$category_names = db_query($query, $selected_types);
		if(count($category_names) != count($selected_types)) {
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

		$filter_settings['types'] = $selected_types;
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

$categories = db_query('SELECT id, title FROM traffic_info_category ORDER BY id ASC');

$data = db_query('SELECT value FROM settings WHERE `key` = ?', array('last_update'));
if(count($data) == 0) {
	$last_update = 'nie';
	$last_update_css = 'unknown';
}
else {
	$update_time = new DateTime("@{$data[0]['value']}");
	$now = new DateTime();

	$interval = $now->diff($update_time);
	if($interval->days > 1) {
		$formatted_interval = $interval->format('%a Tagen, %H:%I:%S'); 
	}
	else if($interval->days > 0) {
		$formatted_interval = $interval->format('einem Tag, %H:%I:%S'); 
	}
	else {
		$formatted_interval = $interval->format('%H:%I:%S'); 
	}

	$last_update = date('d.m.Y H:i:s', $data[0]['value']) . " (vor $formatted_interval)";

	if(time()-$data[0]['value'] > $critical_period*60) {
		$last_update_css = 'critical';
	}
	else if(time()-$data[0]['value'] > $warning_period*60) {
		$last_update_css = 'warning';
	}
	else {
		$last_update_css = 'ok';
	}
}

$data = db_query('SELECT UNIX_TIMESTAMP(MAX(timestamp_created)) last_disruption FROM traffic_info');
if(count($data) == 0) {
	$last_disruption = 'nie';
}
else {
	$disruption_time = new DateTime("@{$data[0]['last_disruption']}");
	$now = new DateTime();

	$interval = $now->diff($disruption_time);
	if($interval->days > 1) {
		$formatted_interval = $interval->format('%a Tagen, %H:%I:%S'); 
	}
	else if($interval->days > 0) {
		$formatted_interval = $interval->format('einem Tag, %H:%I:%S'); 
	}
	else {
		$formatted_interval = $interval->format('%H:%I:%S'); 
	}

	$last_disruption = date('d.m.Y H:i:s', $data[0]['last_disruption']) . " (vor $formatted_interval)";
}

require_once("$template_dir/disruptions_html.php");

