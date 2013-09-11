<?php
$start_time = microtime(true);

require_once(dirname(__FILE__) . '/../config.php');
require_once('Mail/mime.php');
require_once('Mail.php');

$db = new PDO("mysql:dbname=$db_name;host=$db_host", $db_user, $db_pass);
db_query('SET NAMES UTF8');

$template_dir = dirname(__FILE__) . '/../templates/';

$memcached = new Memcached();
$memcached_servers = array(array('ip' => '127.0.0.1', 'port' => '11211'));
foreach($memcached_servers as $server) {
	$memcached->addServer($server['ip'], $server['port']);
}

function db_query($query, $parameters = array(), $ignore_errors = false) {
	global $db, $db_queries;

	$query_start = microtime(true);
	if(!($stmt = $db->prepare($query))) {
		$error = $db->errorInfo();
		if(!$ignore_errors) {
			db_error($error[2], debug_backtrace(), $query, $parameters);
		}
	}
	// see https://bugs.php.net/bug.php?id=40740 and https://bugs.php.net/bug.php?id=44639
	foreach($parameters as $key => $value) {
		$stmt->bindValue($key+1, $value, is_numeric($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
	}
	if(!$stmt->execute()) {
		$error = $stmt->errorInfo();
		if(!$ignore_errors) {
			db_error($error[2], debug_backtrace(), $query, $parameters);
		}
	}
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if(!$stmt->closeCursor()) {
		$error = $stmt->errorInfo();
		if(!$ignore_errors) {
			db_error($error[2], debug_backtrace(), $query, $parameters);
		}
	}
	$query_end = microtime(true);

	if(!isset($db_queries)) {
		$db_queries = array();
	}
	$db_queries[] = array('timestamp' => time(), 'query' => $query, 'parameters' => serialize($parameters), 'execution_time' => $query_end-$query_start);

	return $data;
}

function db_error($error, $stacktrace, $query, $parameters) {
	global $report_email, $email_from;

	@header('HTTP/1.1 500 Internal Server Error');
	echo "A database error has just occurred. Please don't freak out, the administrator has already been notified.\n";

	$params = array(
			'ERROR' => $error,
			'STACKTRACE' => dump_r($stacktrace),
			'QUERY' => $query,
			'PARAMETERS' => dump_r($parameters),
			'REQUEST_URI' => (isset($_SERVER) && isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : 'none',
		);
	write_log("A database error has occurred: \n\nRequest URI: {$params['REQUEST_URI']}\n\nQuery: {$params['QUERY']}\n\nError message: $error\n\nParameters:\n{$params['PARAMETERS']}\n\nStack trace:\n{$params['QUERY']}\n\n");

	send_mail('db_error', 'Wiener Linien - Database error', $params, true);
}

function dump_r($variable) {
	ob_start();
	print_r($variable);
	$data = ob_get_contents();
	ob_end_clean();

	return $data;
}

function send_mail($template, $subject, $parameters = array(), $fatal = false, $attachments = array()) {
	global $email_from, $report_email, $template_dir;

	if(strpos($template, '..') !== false) {
		die();
	}

	$message = file_get_contents("$template_dir/mails/$template.php");

	$patterns = array();
	$replacements = array();
	foreach($parameters as $key => $value) {
		$patterns[] = "[$key]";
		$replacements[] = $value;
	}
	$message = str_replace($patterns, $replacements, $message);

	$headers = array(
			'From' => $email_from,
			'To' => $report_email,
			'Subject' => $subject,
		);

	$mime = &new Mail_Mime(array('text_charset' => 'UTF-8'));
	$mime->setTXTBody($message);
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	foreach($attachments as $attachment) {
		$mime->addAttachment($attachment, finfo_file($finfo, $attachment));
	}

	$mail =& Mail::factory('smtp');
	$mail->send($report_email, $mime->headers($headers), $mime->get());

	if($fatal) {
		// TODO HTTP error code/message
		die();
	}
}

function db_last_insert_id() {
	global $db;

	return $db->lastInsertId();
//	$data = db_query('SELECT lastval() id');
//	return $data[0]['id'];
}

function download_json($url, $prefix) {
	global $input_encoding;

	if(!isset($input_encoding)) {
		return json_decode(iconv('ISO-8859-15', 'UTF-8', download($url, $prefix, 'json')));
	}
	if($input_encoding != 'UTF-8') {
		return json_decode(iconv($input_encoding, 'UTF-8', download($url, $prefix, 'json')));
	}
	return json_decode(download($url, $prefix, 'json'));
}

function download_csv($url, $prefix) {
	$csv_file = download($url, $prefix, 'csv', true);
	$csv = new Csv();
	$csv->separator = ';';
	$csv->parse($csv_file);
	$csv->first_row_headers();

	return $csv->rows;
}

function download($url, $prefix, $extension, $return_filename = false) {
	global $cache_expiration;

	$cache_dir = dirname(__FILE__) . '/../cache/';
	$timestamp = date('YmdHis');
	$filename = "$cache_dir${prefix}_$timestamp.$extension";

	$dir = opendir($cache_dir);
	$found_file = null;
	$found_timestamp = 0;
	while(($file = readdir($dir)) !== false) {
		if(mb_substr($file, 0, mb_strlen($prefix, 'UTF-8')+1, 'UTF-8') == "${prefix}_") {
			$timestamp = mb_substr($file, mb_strlen($prefix, 'UTF-8')+1, mb_strlen($file, 'UTF-8')-mb_strlen($prefix, 'UTF-8')-2-mb_strlen($extension, 'UTF-8'));
			$date = DateTime::createFromFormat('YmdHis', $timestamp);
			if(!$date) {
				continue;
			}
			$time = $date->getTimestamp();

			if(time() - $time < $cache_expiration && $found_timestamp < $time) {
				$found_file = $file;
				$found_timestamp = $time;

				$filename =  "$cache_dir$file";
			}
		}
	}
	closedir($dir);

	if($found_file != null) {
		write_log("Using cached file $found_file");

		if($return_filename) {
			return $filename;
		}
		return file_get_contents("$cache_dir$found_file");
	}

	write_log("Fetching $url to $filename...");

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$data = curl_exec($curl);
	curl_close($curl);
	file_put_contents($filename, $data);

	write_log("Fetching completed");

	if($return_filename) {
		return $filename;
	}
	return $data;
}

function write_log($message) {
	global $debug;

	$logfile = dirname(__FILE__) . '/../log/log';
	$timestamp = date('Y-m-d H:i:s');

	$file = fopen($logfile, 'a');
	fputs($file, "[$timestamp] - $message\n");
	fclose($file);

	db_query('INSERT INTO log (text) VALUES (?)', array($message), true);

	if($debug) {
		echo "[$timestamp] - $message\n";
	}
}

function check_outdated($current_ids, $table) {
	write_log("Searching for outdated entries in table '$table'...");

	$result = db_query("SELECT id FROM $table WHERE deleted = 0");
	foreach($result as $row) {
		if(!in_array($row['id'], $current_ids)) {
			write_log("Found outdated item with id {$row['id']}");
			db_query("UPDATE $table SET deleted = 1, timestamp_deleted = NOW() WHERE id = ?", array($row['id']));
		}
	}
}

function log_query_stats() {
	global $db_queries, $start_time;

	$end_time = microtime(true);
	$total_time = round($end_time-$start_time, 2);
	$queries = count($db_queries);
	$queries_per_sec = $queries/$total_time;
	write_log("$queries queries in $total_time seconds ($queries_per_sec queries/sec)");
}

function get_disruptions($filter = array()) {
	$filter_part = '1=1';
	$filter_params = array();

	if(count($filter) == 0) {
		$filter_part = 'i.deleted = 0';
	}
	else {
		if(isset($filter['id'])) {
			$filter_part .= ' AND i.id = ?';
			$filter_params[] = $filter['id'];
		}

		if(isset($filter['group'])) {
			$filter_part .= ' AND i.group = ?';
			$filter_params[] = $filter['group'];
		}

		if(isset($filter['twitter']) && $filter['twitter'] == '0') {
			$filter_part .= ' AND i.id NOT IN (SELECT id FROM traffic_info_twitter)';
		}
		else if(isset($filter['twitter']) && $filter['twitter'] == '1') {
			$filter_part .= ' AND i.id IN (SELECT id FROM traffic_info_twitter)';
		}

		if(isset($filter['deleted'])) {
			$filter_part .= ' AND i.deleted = ?';
			$filter_params[] = $filter['deleted'];
		}
	}

	$disruptions = db_query("SELECT i.id id, i.title title, i.description description, UNIX_TIMESTAMP(COALESCE(e.start_time, i.start_time, i.timestamp_created)) start_time,
					UNIX_TIMESTAMP(COALESCE(e.end_time, i.end_time)) end_time,
					COALESCE(c.short_name, c.title) category, i.group `group`, i.deleted deleted,
					GROUP_CONCAT(DISTINCT l.name ORDER BY l.name ASC SEPARATOR ',') `lines`,
					GROUP_CONCAT(DISTINCT s.name ORDER BY s.name ASC SEPARATOR ',') `stations`
				FROM traffic_info i
					LEFT JOIN traffic_info_elevator e ON (i.id = e.id)
					LEFT JOIN traffic_info_line til ON (i.id = til.traffic_info)
					LEFT JOIN line l ON (til.line = l.id)
					LEFT JOIN traffic_info_platform tip ON (i.id = tip.traffic_info)
					LEFT JOIN wl_platform p ON (tip.platform = p.id)
					LEFT JOIN station s ON (p.station = s.id)
					JOIN traffic_info_category c ON (i.category = c.id)
				WHERE $filter_part
				GROUP BY i.id, i.title, i.description, i.start_time, i.end_time, i.timestamp_created, c.title, i.group
				ORDER BY `group` ASC, start_time ASC", $filter_params);

	foreach($disruptions as $index => &$disruption) {
		if($disruption['start_time'] > time()) {
			unset($disruptions[$index]);
			continue;
		}

		$disruption['ids'] = array($disruption['id']);
		if($disruption['lines'] == '') {
			$disruption['lines'] = array();
		}
		else {
			$disruption['lines'] = explode(',', $disruption['lines']);
			usort($disruption['lines'], 'line_sorter');
		}

		if($disruption['stations'] == '') {
			$disruption['stations'] = array();
		}
		else {
			$disruption['stations'] = explode(',', $disruption['stations']);
		}

		if(isset($previous_disruption) && $disruption['group'] && $disruption['group'] == $disruptions[$previous_disruption]['group']) {
			$disruptions[$previous_disruption]['stations'] = array_unique(array_merge($disruptions[$previous_disruption]['stations'], $disruption['stations']));
			sort($disruptions[$previous_disruption]['stations']);

			$disruptions[$previous_disruption]['lines'] = array_unique(array_merge($disruptions[$previous_disruption]['lines'], $disruption['lines']));
			usort($disruptions[$previous_disruption]['lines'], 'line_sorter');

			$disruptions[$previous_disruption]['ids'][] = $disruption['id'];

			unset($disruptions[$index]);
			continue;
		}

		$previous_disruption = $index;
	}

	usort($disruptions, function($a, $b) {
		if($a['start_time'] < $b['start_time']) {
			return 1;
		}
		if($a['start_time'] > $b['start_time']) {
			return -1;
		}
		if($a['end_time'] < $b['end_time']) {
			return 1;
		}
		if($a['end_time'] > $b['end_time']) {
			return -1;
		}
		return 0;
	});
	return $disruptions;
}

function line_sorter($a, $b) {
	if(isset($a['name']) && isset($b['name'])) {
		preg_match('/^([A-Z]*)([0-9]*)([A-Z]*)$/', $a['name'], $matches_a);
		preg_match('/^([A-Z]*)([0-9]*)([A-Z]*)$/', $b['name'], $matches_b);
	}
	else {
		preg_match('/^([A-Z]*)([0-9]*)([A-Z]*)$/', $a, $matches_a);
		preg_match('/^([A-Z]*)([0-9]*)([A-Z]*)$/', $b, $matches_b);
	}

	if($matches_a[1] != '' && $matches_b[1] == '') {
		return -1;
	}
	if($matches_a[1] != '' && $matches_b[1] != '' && $matches_a[1] < $matches_b[1]) {
		return -1;
	}
	if($matches_a[1] != '' && $matches_b[1] != '' && $matches_a[1] > $matches_b[1]) {
		return 1;
	}

	if(intval($matches_a[2]) < intval($matches_b[2])) {
		return -1;
	}
	if(intval($matches_a[2]) > intval($matches_b[2])) {
		return 1;
	}

	if($matches_a[3] < $matches_b[3]) {
		return -1;
	}
	if($matches_a[3] > $matches_b[3]) {
		return 1;
	}

	return 0;
}

function cache_get($key) {
	global $memcached;

	// TODO -> configuration file
	$memcached_prefix = "wienerlinien_dev";

	return $memcached->get("${memcached_prefix}_$key");
}

function cache_set($key, $data, $expiration = 60) {
	global $memcached;

	// TODO -> configuration file
	$memcached_prefix = "wienerlinien_dev";

	$memcached->set("${memcached_prefix}_$key", $data, $expiration);
}

function fetch_rbls($rbls) {
	global $wl_api_key, $cache_expiration, $debug;

	$result = array();
	$missing_ids = array();
	foreach($rbls as $rbl) {
		// TODO necessary?
		db_query('INSERT INTO active_rbl (rbl) VALUES (?) ON DUPLICATE KEY UPDATE `timestamp` = NOW()', array($rbl));
		$data = cache_get("rbl_$rbl");
		if(!$data) {
			$missing_ids[] = $rbl;
		}
		else {
			$result[$rbl] = $data;
		}
	}

	if(count($missing_ids) > 0) {
		$url = 'http://www.wienerlinien.at/ogd_realtime/monitor?rbl=' . implode(',', $missing_ids) . "&sender=$wl_api_key";
		$cache_expiration = -1; // TODO hmmm
		$debug = false;
		$data = download_json($url, 'rbl_' . implode('.', $missing_ids));

		foreach($data->data->monitors as $monitor) {
			$rbl = $monitor->locationStop->properties->attributes->rbl;
			$lines = $monitor->lines;

			$result[$rbl] = $lines;
			cache_set("rbl_$rbl", $lines, 60);
		}
	}

	return $result;
}

