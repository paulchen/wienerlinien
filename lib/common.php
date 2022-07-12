<?php
$start_time = microtime(true);

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/../config.php');
require_once(dirname(__FILE__) . '/bom.php');
require_once('Mail/mime.php');
require_once('Mail.php');

if(!isset($long_running_queries)) {
	# avoid PHP notice
	$long_running_queries = false;
}

$db = new PDO(
	"mysql:dbname=$db_name;host=$db_host;charset=utf8",
	$long_running_queries ? $db_user_long : $db_user,
	$long_running_queries ? $db_pass_long : $db_pass,
	array(
		PDO::ATTR_TIMEOUT => 10,
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	)
);
$db_queries = array();

if(isset($use_transaction) && $use_transaction) {
	$db->beginTransaction();
}

$template_dir = dirname(__FILE__) . '/../templates/';

$memcached = new Memcached();
foreach($memcached_servers as $server) {
	$memcached->addServer($server['ip'], $server['port']);
}

$retry_download = true;

$correlation_id = uniqid();

function db_query($query, $parameters = array(), $ignore_errors = false) {
	global $db, $db_queries;

//	write_log($query);

	$query_start = microtime(true);
	try {
		if(!($stmt = $db->prepare($query))) {
			$error = $db->errorInfo();
			if(!$ignore_errors) {
				db_error($error[2], debug_backtrace(), $query, $parameters);
			}
		}
		$index = 0;
		foreach($parameters as $value) {
			$stmt->bindValue(++$index, $value);
		}
		if(!$stmt->execute()) {
			$error = $stmt->errorInfo();
			if(!$ignore_errors) {
				db_error($error[2], debug_backtrace(), $query, $parameters);
			}
		}
		if(preg_match('/^\s*SELECT/i', $query)) {
			$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		}
		else {
			$data = array();
		}
		if(!$stmt->closeCursor()) {
			$error = $stmt->errorInfo();
			if(!$ignore_errors) {
				db_error($error[2], debug_backtrace(), $query, $parameters);
			}
		}
	}
	catch (PDOException $e) {
		if(!$ignore_errors) {
			if(strpos($e->getMessage(), 'max_statement_time') !== false) {
				die();
			}
			db_error($e->getMessage(), $e->getTraceAsString(), $query, $parameters);
		}
	}

	$query_end = microtime(true);

	$db_queries[] = array('timestamp' => time(), 'query' => $query, 'parameters' => serialize($parameters), 'execution_time' => $query_end-$query_start);

	return $data;
}

/* TODO
function db_query($query, $parameters = array()) {
	$stmt = db_query_resultset($query, $parameters);
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	db_stmt_close($stmt);
	return $data;
}
 */

function db_stmt_close($stmt) {
	if(!$stmt->closeCursor()) {
		$error = $stmt->errorInfo();
		db_error($error[2], debug_backtrace(), $query, $parameters);
	}
}

function db_query_resultset($query, $parameters = array()) {
	global $db;

//	write_log($query);

	$query_start = microtime(true);
	if(!($stmt = $db->prepare($query))) {
		$error = $db->errorInfo();
		db_error($error[2], debug_backtrace(), $query, $parameters);
	}
	foreach($parameters as $key => $value) {
		$stmt->bindValue($key+1, $value);
	}
	if(!$stmt->execute()) {
		$error = $stmt->errorInfo();
		db_error($error[2], debug_backtrace(), $query, $parameters);
	}
	$query_end = microtime(true);

	if(!isset($db_queries)) {
		$db_queries = array();
	}
	$db_queries[] = array('timestamp' => time(), 'query' => $query, 'parameters' => serialize($parameters), 'execution_time' => $query_end-$query_start);

	return $stmt;
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

	log_query_stats();

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

	$mime = new Mail_Mime(array('text_charset' => 'UTF-8'));
	$mime->setTXTBody($message);
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	foreach($attachments as $attachment) {
		$mime->addAttachment($attachment, finfo_file($finfo, $attachment));
	}

	$mail = Mail::factory('smtp');
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
	$convert_function = function($filename, $data) {
		global $input_encoding;

		if(!isset($input_encoding)) {
			return json_decode(iconv('ISO-8859-15', 'UTF-8', $data));
		}
		if($input_encoding != 'UTF-8') {
			return json_decode(iconv($input_encoding, 'UTF-8', $data));
		}
		return json_decode($data);
	};

	return download($url, $prefix, 'json', $convert_function, 'application/json');
}

function download_csv($url, $prefix) {
	$convert_function = function($filename, $data) {
		$csv = new Csv();
		$csv->separator = ';';
		$csv->parse($filename);
		$csv->first_row_headers();

		return $csv->rows;
	};

	return download($url, $prefix, 'csv', $convert_function, 'text/csv');
}

function check_content_type($mime, $curl_info) {
	if($mime == '') {
		return true;
	}
	if(!isset($curl_info['content_type'])) {
		write_log('No Content-Type header found');
		return false;
	}

	// split application/json;charset=UTF-8
	$content_type = $curl_info['content_type'];
	$parts = explode(';', $content_type);
	$content_type = $parts[0];

	if($content_type == $mime) {
		return true;
	}

	write_log("Content-Type is $content_type, but $mime was expected");
	return false;
}

function download($url, $prefix, $extension, $convert_function, $mime = '') {
	global $cache_expiration, $retry_download, $download_failure_wait_times;

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
		$full_name = "$cache_dir$found_file";
		$data = $convert_function($filename, remove_bom(file_get_contents($full_name)));
		if($data) {
			write_log("Using cached file $found_file");

			return $data;
		}

		write_log("Cached file $found_file contains garbage and will be deleted now");

		unlink($full_name);
	}

	write_log("Fetching $url to $filename...");

	$attempts = 0;
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_TIMEOUT, 30);
	$retval = null;
	while($attempts < count($download_failure_wait_times)) {
		$data = curl_exec($curl);
		$info = curl_getinfo($curl);
		if($info['http_code'] == 200 && check_content_type($mime, $info)) {
			file_put_contents($filename, $data);
			$retval = $convert_function($filename, remove_bom($data));

			if($retval) {
				write_log("Fetching completed");
				break;
			}
			unlink($filename);
		}

		write_log('Fetching failed');
		write_log('Download status info: ' . dump_r($info));
		write_log("Data fetched: $data");

		if(!$retry_download) {
			write_log('Not retrying download');
			break;
		}

		$wait_time = $download_failure_wait_times[$attempts];
		$attempts++;

		write_log("Retrying download in $wait_time seconds (attempt $attempts)...");

		sleep($wait_time);
	}
	curl_close($curl);

	if(!$retval) {
		write_log('Fetching failed');
	}

	return $retval;
}

function write_log($message) {
	global $debug, $correlation_id, $db_queries;

	$logfile = dirname(__FILE__) . '/../log/log';
	$timestamp = date('Y-m-d H:i:s');

	$db_query_count = 0;
	if(isset($db_queries)) {
		$db_query_count = count($db_queries);
	}

	$output = "[$timestamp] - $correlation_id - $db_query_count database queries - $message\n";

	$file = fopen($logfile, 'a');
	fputs($file, $output);
	fclose($file);

	// db_query('INSERT INTO log (text) VALUES (?)', array($message), true);

	if($debug && php_sapi_name() === 'cli') {
		echo $output;
	}
}

function report_problem($message, $stacktrace) {
	write_log($message);

//	db_query('INSERT INTO data_problem (description) VALUES (?)', array($message));

	$params = array(
			'MESSAGE' => $message,
			'STACKTRACE' => dump_r($stacktrace),
			'REQUEST_URI' => (isset($_SERVER) && isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : 'none',
		);

//	send_mail('data_problem', 'Wiener Linien - Data problem', $params, false);
}

function check_outdated($current_ids, $table) {
	write_log("Searching for outdated entries in table '$table'...");

	$outdated_disruptions = array();
	$result = db_query("SELECT id FROM $table WHERE deleted = 0");
	foreach($result as $row) {
		if(!in_array($row['id'], $current_ids)) {
			write_log("Found outdated item with id {$row['id']}");
			db_query("UPDATE $table SET deleted = 1, timestamp_deleted = NOW() WHERE id = ?", array($row['id']));

			if(!in_array($row['id'], $outdated_disruptions)) {
				$outdated_disruptions[] = $row['id'];
			}
		}
	}

	write_log("Searched for outdated entries in table '$table'...");

	return $outdated_disruptions;
}

function log_query_stats() {
	global $db_queries, $start_time;

	$end_time = microtime(true);
	$total_time = round($end_time-$start_time, 2);
	if($total_time == 0) {
		$total_time = .01;
	}
	$queries = count($db_queries);
	$queries_per_sec = round($queries/$total_time, 2);
	write_log("$queries queries in $total_time seconds ($queries_per_sec queries/sec)");
}

function get_disruptions($filter = array(), &$pagination_data = array()) {
	$filter_part = '1=1';
	$filter_params = array();

	// $twitter_query = 'SELECT DISTINCT `group` FROM traffic_info WHERE id IN (SELECT id FROM traffic_info_twitter)';
	$page = 1;
	$table = 'traffic_info_group';
	$line_table = 'traffic_info_group_line';
	$group_time = 'i.start_time group_time';
	$group_by = '';
	if(isset($filter['id'])) {
		$filter_part .= ' AND i.id = ?';
		$filter_params[] = $filter['id'];
		$table = 'traffic_info';
		$line_table = 'traffic_info_line';
		$group_time = 'MAX(i.start_time) group_time';
		$group_by = 'GROUP BY i.id';
	}

	if(isset($filter['group'])) {
		$filter_part .= ' AND i.id = ?';
		$filter_params[] = $filter['group'];
	}

	/*
	if(isset($filter['twitter']) && $filter['twitter'] == '0') {
		$filter_part .= " AND i.group NOT IN ($twitter_query)";
	}
	else if(isset($filter['twitter']) && $filter['twitter'] == '1') {
		$filter_part .= " AND i.group IN ($twitter_query)";
	}
	 */

	if(isset($filter['deleted'])) {
		$filter_part .= ' AND i.deleted = ?';
		$filter_params[] = $filter['deleted'];
	}
	else if(!isset($filter['twitter']) && !isset($filter['id']) && !isset($filter['group']) && (!isset($filter['archive']) || $filter['archive'] != 1)) {
		$filter_part .= ' AND i.deleted = ?';
		$filter_params[] = 0;
	}

	$disruptions_per_page = 1000000;
	if(isset($filter['archive']) && $filter['archive'] == 1) {
		$filter_part .= ' AND (i.start_time < NOW() OR i.timestamp_deleted IS NULL)';
		$disruptions_per_page = 20;
		if(isset($filter['lines'])) {
			$parameters = array();
			foreach($filter['lines'] as $line) {
				$parameters[] = '?';
				$filter_params[] = $line;
			}
			$parameters_string = implode(',', $parameters);
			$filter_part .= " AND l2.id IN ($parameters_string)";
		}
		if(isset($filter['from'])) {
			$filter_part .= ' AND (i.timestamp_deleted > FROM_UNIXTIME(?) OR i.deleted = 0)';
			$filter_params[] = $filter['from'];
		}
		if(isset($filter['to'])) {
			$filter_part .= ' AND i.start_time < FROM_UNIXTIME(?)';
			$filter_params[] = $filter['to'];
		}
		if(isset($filter['types'])) {
			$parameters = array();
			foreach($filter['types'] as $type) {
				$parameters[] = '?';
				$filter_params[] = $type;
			}
			$parameters_string = implode(',', $parameters);
			$filter_part .= " AND c.id IN ($parameters_string)";
		}
		if(isset($filter['text'])) {
			$filter_part .= ' AND i.title LIKE ?';
			$filter_params[] = '%' . $filter['text'] . '%';
		}
	}
	else {
		$filter_part .= ' AND i.start_time < NOW()';
	}

	if(isset($filter['page'])) {
		$page = $filter['page'];
	}

	$query = "SELECT i.id `group`, $group_time
					FROM $table i
					LEFT JOIN $line_table til2 ON (i.id = til2.traffic_info)
					LEFT JOIN line l2 ON (til2.line = l2.id)
					JOIN traffic_info_category c ON (i.category = c.id)
				WHERE $filter_part
				$group_by";
/*
	print_r($query);
	print_r($filter_params);
	die();
 */
	$result = db_query("SELECT COUNT(*) disruptions FROM ($query) a", $filter_params);
	$disruption_count = $result[0]['disruptions'];

	if($disruption_count > 0) {
		$offset = ($page-1)*$disruptions_per_page;
		if(isset($filter['limit'])) {
			if($filter['limit'] == -1) {
				$disruptions_per_page = $disruption_count;
			}
			else {
				$disruptions_per_page = $filter['limit'];
			}
		}

		$groups = db_query("$query ORDER BY group_time DESC LIMIT $offset, $disruptions_per_page", $filter_params);
		$group_ids = array_map(function($a) { return $a['group']; }, $groups);
		$groups_filter = implode(',', array_fill(0, count($group_ids), '?'));
	}

	if($disruption_count > 0 && count($group_ids) > 0) {
		$disruptions = db_query("SELECT i.id id, i.title title, i.description description, UNIX_TIMESTAMP(COALESCE(i.start_time, i.timestamp_created)) start_time,
						UNIX_TIMESTAMP(i.end_time) end_time,
						UNIX_TIMESTAMP(i.resume_time) resume_time,
						COALESCE(c.short_name, c.title) category, c.id category_id, i.group `group`, i.deleted deleted,
						UNIX_TIMESTAMP(i.timestamp_deleted) timestamp_deleted
					FROM traffic_info i
						JOIN traffic_info_category c ON (i.category = c.id)
					WHERE i.group IN ($groups_filter)
					GROUP BY i.id, i.title, i.description, i.start_time, i.end_time, i.timestamp_created, i.group
					ORDER BY `group` ASC, start_time ASC", $group_ids);
	}
	else {
		$disruptions = array();
	}
	foreach($disruptions as $index => &$disruption) {
		$disruption['ids'] = array($disruption['id']);

		if(isset($previous_disruption) && $disruption['group'] && $disruption['group'] == $disruptions[$previous_disruption]['group']) {

			$disruptions[$previous_disruption]['ids'][] = $disruption['id'];

			unset($disruptions[$index]);
			continue;
		}

		$previous_disruption = $index;
	}
	unset($disruption);
	usort($disruptions, function($a, $b) {
		if($a['start_time'] < $b['start_time']) {
			return 1;
		}
		if($a['start_time'] > $b['start_time']) {
			return -1;
		}
		return 0;
	});

	if($disruption_count > $disruptions_per_page) {
		$pages = ceil($disruption_count/$disruptions_per_page);
		$pagination_data = array(
			'first' => 1,
			'previous' => max(1, $page-1),
			'current' => $page,
			'next' => min($pages, $page+1),
			'last' => $pages
		);
	}

	$ids = array();
	$placeholder_array = array();
	foreach($disruptions as $disruption) {
		foreach($disruption['ids'] as $id) {
			$ids[] = $id;
			$placeholder_array[] = '?';
		}
	}
	$placeholders = implode(', ', $placeholder_array);

	$lines = array();
	$stations = array();
	if(count($ids) > 0) {
		$data = db_query("SELECT til.traffic_info traffic_info, GROUP_CONCAT(DISTINCT l.name SEPARATOR ',') `lines`
			FROM traffic_info_line til
				JOIN line l ON (til.line = l.id)
			WHERE til.traffic_info IN ($placeholders)
			GROUP BY til.traffic_info", $ids);
		foreach($data as $row) {
			$lines[$row['traffic_info']] = explode(',', $row['lines']);
		}

		$data = db_query("SELECT tip.traffic_info traffic_info, GROUP_CONCAT(DISTINCT s.name SEPARATOR ',') `stations`
			FROM traffic_info_platform tip
			JOIN wl_platform p ON (tip.platform = p.id)
			JOIN station s ON (p.station = s.id)
			WHERE tip.traffic_info IN ($placeholders)
			GROUP BY tip.traffic_info", $ids);
		foreach($data as $row) {
			$stations[$row['traffic_info']] = explode(',', $row['stations']);
		}
	}

	foreach($disruptions as $index => &$disruption) {
		$disruption['lines'] = array();
		$disruption['stations'] = array();

		foreach($disruption['ids'] as $id) {
			if(isset($lines[$id])) {
				foreach($lines[$id] as $line) {
					$disruption['lines'][] = $line;
				}
			}
			if(isset($stations[$id])) {
				foreach($stations[$id] as $station) {
					$disruption['stations'][] = $station;
				}
			}
		}

		$disruption['lines'] = array_unique($disruption['lines']);
		usort($disruption['lines'], 'line_sorter');

		$disruption['stations'] = array_unique($disruption['stations']);
		sort($disruption['stations']);
	}
	unset($disruption);

	return $disruptions;
}

function get_disruptions_for_station($station) {
	$data_by_lines = db_query('SELECT l.name line, ti.title, ti.last_description description, ti.start_time, ti.end_time
                        FROM station s
                                JOIN wl_platform p ON (s.id = p.station AND p.deleted = 0)
                                JOIN line l ON (p.line = l.id AND l.deleted = 0)
                                JOIN traffic_info_line til ON (l.id = til.line)
                                JOIN traffic_info ti ON (til.traffic_info = ti.id AND ti.deleted = 0)
                        WHERE s.id = ?
                                AND s.deleted = 0
                                and ti.category = 2', array($station));
	$data_by_rbls = db_query('SELECT p.rbl rbl, ti.category, ti.title, ti.last_description description, ti.start_time, ti.end_time, tie.status
                        FROM station s
                                JOIN wl_platform p ON (s.id = p.station AND p.deleted = 0)
                                JOIN traffic_info_platform tip ON (p.id = tip.platform)
                                JOIN traffic_info ti ON (tip.traffic_info = ti.id AND ti.deleted = 0)
                                JOIN traffic_info_elevator tie ON (ti.id = tie.id)
                        WHERE s.id = ?
                                AND s.deleted = 0
                                AND ti.category IN (1, 3)
                        GROUP BY p.rbl, ti.category, ti.title, ti.last_description, ti.start_time, ti.end_time', array($station));

	// array transformation: https://stackoverflow.com/a/72379539
	return array(
		'lines' => array_combine(array_column($data_by_lines, 'line'), $data_by_lines),
		'rbls' => array_combine(array_column($data_by_rbls, 'rbl'), $data_by_rbls)
	);
}

function line_sorter($a, $b) {
	if(isset($a['name']) && isset($b['name'])) {
		$a = $a['name'];
		$b = $b['name'];
	}

	$parentheses_a = false;
	$parentheses_b = false;
	if(substr($a, 0, 1) == '(' && substr($a, strlen($a)-1, 1) == ')') {
		$a = substr($a, 1, strlen($a)-2);
		$parentheses_a = true;
	}
	if(substr($b, 0, 1) == '(' && substr($b, strlen($b)-1, 1) == ')') {
		$b = substr($b, 1, strlen($b)-2);
		$parentheses_b = true;
	}

	preg_match('/^([A-Z]*)([0-9]*)([A-Z]*)$/', $a, $matches_a);
	preg_match('/^([A-Z]*)([0-9]*)([A-Z]*)$/', $b, $matches_b);

	if(count($matches_a) == 0 && count($matches_b) > 0) {
		return -1;
	}

	if(count($matches_a) > 0 && count($matches_b) == 0) {
		return 1;
	}

	if(count($matches_a) == 0 && count($matches_b) == 0) {
		return strcmp($a, $b);
	}

	if($matches_a[1] == 'N' && $matches_b[1] != 'N') { // night buses at the very end
		return 1;
	}
	if($matches_a[1] != 'N' && $matches_b[1] == 'N') { // night buses at the very end
		return -1;
	}
	if($matches_a[1] == 'N' && $matches_b[1] == 'N' && $matches_a[2] == $matches_b[2]) { // N31 == N31
		return 0;
	}
	if($matches_a[1] == 'N' && $matches_b[1] == 'N' && $matches_a[2] < $matches_b[2]) { // N8 < N31
		return -1;
	}
	if($matches_a[1] == 'N' && $matches_b[1] == 'N' && $matches_a[2] > $matches_b[2]) { // N31 > N8
		return 1;
	}

	if($matches_a[1] != '' && $matches_b[1] == '') { // U1 < 1, U1 < 13A
		return -1;
	}
	if($matches_a[1] == '' && $matches_b[1] != '') { // 1 > U1, 13A > U1
		return 1;
	}

	if($matches_a[1] != '' && $matches_b[1] != '' && $matches_a[2] == '' && $matches_b[2] != '') { // D > U1
		return 1;
	}
	if($matches_a[1] != '' && $matches_b[1] != '' && $matches_a[2] != '' && $matches_b[2] == '') { // U1 < D
		return -1;
	}

	if($matches_a[1] != '' && $matches_b[1] != '' && $matches_a[1] < $matches_b[1]) { // D < O, S1 < U1, O < WLB
		return -1;
	}
	if($matches_a[1] != '' && $matches_b[1] != '' && $matches_a[1] > $matches_b[1]) { // O > D, U1 > S1, WLB > O
		return 1;
	}

	if($matches_a[3] == '' && $matches_b[3] != '') { // 71 < 13A
		return -1;
	}
	if($matches_a[3] != '' && $matches_b[3] == '') { // 13A > 71
		return 1;
	}

	if(intval($matches_a[2]) < intval($matches_b[2])) { // U1 < U3, S1 < S3
		return -1;
	}
	if(intval($matches_a[2]) > intval($matches_b[2])) { // U3 > U1, S3 > S1
		return 1;
	}

	if($matches_a[3] < $matches_b[3]) { // 99A < 99B
		return -1;
	}
	if($matches_a[3] > $matches_b[3]) { // 99B > 99A
		return 1;
	}

	if($parentheses_a && !$parentheses_b) {
		return 1;
	}
	if(!$parentheses_a && $parentheses_b) {
		return -1;
	}

	return 0;
}

function cache_get($key) {
	global $memcached, $memcached_prefix;

	return $memcached->get("${memcached_prefix}_$key");
}

function cache_set($key, $data, $expiration = 60) {
	global $memcached, $memcached_prefix;

	$memcached->set("${memcached_prefix}_$key", $data, $expiration);
}

function log_rbl_request($rbls) {
	db_query('INSERT INTO rbl_request () VALUES ()');
	$request_id = db_last_insert_id();

	foreach($rbls as $rbl) {
		db_query('INSERT INTO rbl_request_item (request_id, item) VALUES (?, ?)', array($request_id, $rbl));
	}
}

function fetch_rbls($rbls) {
	global $wl_api_key, $debug, $input_encoding, $cache_expiration;

	log_rbl_request($rbls);

	$retry_download = false;
	$url = 'http://www.wienerlinien.at/ogd_realtime/monitor?rbl=' . implode(',', $rbls) . "&sender=$wl_api_key";
	$cache_expiration = -1;
	$debug = false;
	$input_encoding = 'UTF-8';
	$data = download_json($url, 'rbl_' . implode('.', $rbls));

	$rbl_data = array();
	if($data && isset($data->data) && isset($data->data->monitors)) {
		foreach($data->data->monitors as $monitor) {
			$rbl = $monitor->locationStop->properties->attributes->rbl;
			$lines = $monitor->lines;
			if(!isset($rbl_data[$rbl])) {
				$rbl_data[$rbl] = array();
			}
			$rbl_data[$rbl][] = $lines;
		}
	}

	$result = array();
	foreach($rbl_data as $index => $value) {
		$result[$index] = process_rbl_data($value);
	}
	return $result;
}

function get_line_id($name) {
	global $line_id_list;

	if(!isset($line_id_list)) {
		$data = db_query('SELECT id, name FROM line');
		$line_id_list = array();
		foreach($data as $row) {
			$line_id_list[$row['name']] = $row['id'];
		}
	}

	if(!isset($line_id_list[$name])) {
		write_log("Line $name not found in line_id_list");
	}

	if(!isset($line_id_list[$name])) {
		return null;
	}
	return $line_id_list[$name];
}

function possible_destination($line_id, $towards) {
	$data = db_query('SELECT id FROM
			(SELECT s.id id, s.name name, damlev(s.name, ?) damlev, (LOCATE(?, s.name)>0) contained
				FROM wl_platform p
					JOIN station s ON (s.id = p.station)
				WHERE p.line = ?
					AND p.deleted = 0) a
			WHERE (contained = 1 OR damlev < CHAR_LENGTH(name)*.5)
			ORDER BY contained DESC, damlev ASC
			LIMIT 0, 1', array($towards, $towards, $line_id));
	if(count($data) == 0) {
		return null;
	}
	return $data[0]['id'];
}

function process_rbl_data($data) {
	$departures = array();
	foreach($data as $row) {
		foreach($row as $line) {
			if(!isset($line->departures) || !isset($line->departures->departure)) {
				continue;
			}
			foreach($line->departures->departure as $departure) {
				$line_name = (isset($departure->vehicle) && $departure->vehicle->towards && isset($departure->vehicle->name)) ? $departure->vehicle->name : $line->name;
				$towards = (isset($departure->vehicle) && $departure->vehicle->towards) ? chop($departure->vehicle->towards) : chop($line->towards);
				$line_id = get_line_id($line_name);

				if (isset($departure->vehicle) && isset($departure->vehicle->barrierFree)) {
					$barrier_free = $departure->vehicle->barrierFree;
				}
				else {
					$barrier_free = $line->barrierFree;
				}
				$folding_ramp = false;
				if (isset($departure->vehicle->foldingRamp)) {
					$folding_ramp = $departure->vehicle->foldingRamp;
				}
				$realtime_supported = false;
				if (isset($departure->vehicle) && isset($departure->vehicle->realtimeSupported)) {
					$realtime_supported = $departure->vehicle->realtimeSupported;
				}
				else if(isset($line->realtimeSupported)) {
					$realtime_supported = $line->realtimeSupported;
				}

				$countdown = '';
				if (isset($departure->departureTime->countdown)) {
					$countdown = $departure->departureTime->countdown;
				}

				$departures[] = array(
					'line' => $line_name,
					'line_id' => $line_id,
					'towards' => $towards,
					'towards_id' => possible_destination($line_id, $towards),
					'barrier_free' => $barrier_free,
					'folding_ramp' => $folding_ramp,
					'realtime_supported' => $realtime_supported,
					'time' => $countdown
				); 
			}
		}
	}

	usort($departures, function($a, $b) {
		if($a['time'] < $b['time']) {
			return -1;
		}
		if($a['time'] > $b['time']) {
			return 1;
		}
		return line_sorter($a['line'], $b['line']);
	});

	return $departures;
}

function get_item_time($row) {
	if($row['start_time'] != null) {
		return strtotime($row['start_time']);
	}
	return strtotime($row['timestamp_created']);
}

function get_station_data($id) {
	$data = db_query('SELECT station_id, name FROM station WHERE id = ?', array($id));
	if(count($data) != 1) {
		return null;
	}
	$station_id = $data[0]['station_id'];
	$station_name = $data[0]['name'];

	$platforms = db_query("SELECT p.rbl rbl, GROUP_CONCAT(DISTINCT p.platform ORDER BY platform ASC SEPARATOR '/') platform,
				GROUP_CONCAT(DISTINCT l.name ORDER BY wl_order ASC SEPARATOR ',') line_names,
				GROUP_CONCAT(DISTINCT l.id ORDER BY wl_order ASC SEPARATOR ',') line_ids
			FROM station s
				JOIN wl_platform p ON (s.id = p.station)
				JOIN line l ON (p.line = l.id)
				JOIN wl_line wl ON (l.id = wl.line)
			WHERE s.id = ?
				AND s.deleted = 0
				AND l.deleted = 0
				AND p.deleted = 0
			GROUP BY p.rbl
			ORDER BY wl_order ASC", array($id));
	if(count($platforms) < 1) {
		http_response_code(404);
		die('Not found');
	}
	foreach($platforms as &$platform) {
		$platform['line_names'] = explode(',', $platform['line_names']);
		$platform['line_ids'] = explode(',', $platform['line_ids']);
	}
	unset($platform);
	return array('name' => $station_name, 'platforms' => $platforms);
}

function add_static_cache_headers() {
	$data = db_query('SELECT value FROM settings WHERE `key` = ?', array('static_data_expiration'));
	if(count($data) != 1) {
		$seconds = 0;
	}
	else {
		$expiration = $data[0]['value'];
		$seconds = max(0, $expiration - time());
	}

	header("Cache-Control: public, max-age=$seconds");
}

