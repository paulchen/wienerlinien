<?php
$start_time = microtime(true);

require_once(dirname(__FILE__) . '/../config.php');
require_once('Mail/mime.php');
require_once('Mail.php');

$db = new PDO("mysql:dbname=$db_name;host=$db_host", $db_user, $db_pass);
db_query('SET NAMES UTF8');

$template_dir = dirname(__FILE__) . '/../templates/';

$memcached = new Memcached();
foreach($memcached_servers as $server) {
	$memcached->addServer($server['ip'], $server['port']);
}

$retry_download = true;

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

	$download = download($url, $prefix, 'json');
	if($download == null) {
		return null;
	}

	if(!isset($input_encoding)) {
		return json_decode(iconv('ISO-8859-15', 'UTF-8', $download));
	}
	if($input_encoding != 'UTF-8') {
		return json_decode(iconv($input_encoding, 'UTF-8', $download));
	}
	return json_decode($download);
}

function download_csv($url, $prefix) {
	$csv_file = download($url, $prefix, 'csv', true);
	if($csv_file == null) {
		return null;
	}

	$csv = new Csv();
	$csv->separator = ';';
	$csv->parse($csv_file);
	$csv->first_row_headers();

	return $csv->rows;
}

function download($url, $prefix, $extension, $return_filename = false) {
	global $cache_expiration, $retry_download, $download_failure_wait_time;

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

	$attempts = 0;
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	while($attempts < 3) {
		$data = curl_exec($curl);
		$info = curl_getinfo($curl);
		if($info['http_code'] == 200 || !$retry_download) {
			break;
		}
		write_log("Fetching failed, retrying in $download_failure_wait_time seconds...");
		sleep($download_failure_wait_time);
		$attempts++;
	}
	curl_close($curl);

	if($info['http_code'] == 200) {
		file_put_contents($filename, $data);

		write_log("Fetching completed");

		if($return_filename) {
			return $filename;
		}
		return $data;
	}

	write_log('Fetching failed');

	return null;
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

function get_disruptions($filter = array(), &$pagination_data = array()) {
	$filter_part = '1=1';
	$filter_params = array();

	$page = 1;
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
	else if(!isset($filter['twitter']) && !isset($filter['id']) && !isset($filter['group']) && (!isset($filter['archive']) || $filter['archive'] == 0)) {
		$filter_part .= ' AND i.deleted = ?';
		$filter_params[] = 0;
	}
	
	if(isset($filter['archive']) && $filter['archive'] == 1) {
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
	}

	if(isset($filter['page'])) {
		$page = $filter['page'];
	}

	$disruptions = db_query("SELECT i.id id, i.title title, i.description description, UNIX_TIMESTAMP(COALESCE(i.start_time, i.timestamp_created)) start_time,
					UNIX_TIMESTAMP(i.end_time) end_time,
					COALESCE(c.short_name, c.title) category, c.id category_id, i.group `group`, i.deleted deleted,
					GROUP_CONCAT(DISTINCT l.name ORDER BY l.name ASC SEPARATOR ',') `lines`,
					GROUP_CONCAT(DISTINCT s.name ORDER BY s.name ASC SEPARATOR ',') `stations`
				FROM traffic_info i
					LEFT JOIN traffic_info_line til ON (i.id = til.traffic_info)
					LEFT JOIN line l ON (til.line = l.id)
					LEFT JOIN traffic_info_line til2 ON (i.id = til2.traffic_info)
					LEFT JOIN line l2 ON (til2.line = l2.id)
					LEFT JOIN traffic_info_platform tip ON (i.id = tip.traffic_info)
					LEFT JOIN wl_platform p ON (tip.platform = p.id)
					LEFT JOIN station s ON (p.station = s.id)
					JOIN traffic_info_category c ON (i.category = c.id)
				WHERE $filter_part
				GROUP BY i.id, i.title, i.description, i.start_time, i.end_time, i.timestamp_created, c.title, i.group
				ORDER BY `group` ASC, start_time ASC", $filter_params);

	foreach($disruptions as $index => &$disruption) {
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
		if($a['deleted'] < $b['deleted']) {
			return -1;
		}
		if($a['deleted'] > $b['deleted']) {
			return 1;
		}
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

	$disruption_count = count($disruptions);
	$disruptions_per_page = 20;
	$offset = ($page-1)*$disruptions_per_page;
	if(isset($filter['limit'])) {
		if($filter['limit'] == -1) {
			$disruptions_per_page = $disruption_count;
		}
		else {
			$disruptions_per_page = $filter['limit'];
		}
	}
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

	for($a=0; $a<$offset; $a++) {
		array_shift($disruptions);
	}
	while(count($disruptions) > $disruptions_per_page) {
		array_pop($disruptions);
	}

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

function fetch_rbls($rbls) {
	global $wl_api_key, $cache_expiration, $debug, $input_encoding, $semaphore_id, $retry_download, $rbl_lock_wait_time, $rbl_cache_time;

	$retry_download = false;

	/* semaphore for the synchronization of the access to the memcache key
	 * 'rbl_currently_fetched' (see below)
	 */
	$sem = sem_get($semaphore_id, 1, 0600);
	if(!$sem) {
		// TODO error
		die();
	}

	/* The purpose of this loop is to avoid that several concurrent instances of the application
	 * fetch data for the same RBL numbers. This is done using the memcache key 'rbl_currently_fetched'.
	 * This key stores, an array of all RBL numbers which are currently being fetched by any instance
	 * of the application. To synchronize the access to this key, a semaphore ($sem) is used.
	 * Data fetched by one instance is stored using memcache so other instances can use it.
	 *
	 * The loop performs the following steps:
	 * 1) All data which is available from memcache is fetched.
	 * 2) If all data is available, the loop will be terminated.
	 * 3) All RBL numbers currently processed by other instances are identified (from the memcache
	 *    key 'rbl_currently_fetched').
	 * 4) The RBL numbers required by this instance which are not processed by other instances are
	 *    identified and stored in the memcache key 'rbl_currently_fetched'.
	 * 4) Data for all required RBL numbers currently not processed by any other instance is obtained
	 *    from the API.
	 * 5) The data that has been fetched is stored using memcache to make it available to other instances.
	 *    In case no data could have been obtained for an RBL number, the value 'unavailable' is stored
	 *    to ensure no other instance will again try to fetch the data for this number.
	 * 6) The RBL numbers that have just been processed are removed from the memcache key
	 *    'rbl_currently_fetched'.
	 */

	$missing_ids = $rbls; // all RBL number no data has yet been obtained
	$start_time = time(); // timestamp to determine whether the loop shall be terminated due to too high processing time
	$result = array(); // here all data is stored that will be returned to the client

	while(time()-$start_time < $rbl_lock_wait_time) {
		// try to fetch data from memcache for all RBL numbers for which no data has yet been obtained
		foreach($missing_ids as $key => $rbl) {
			$data = cache_get("rbl_$rbl");
			if($data) {
				unset($missing_ids[$key]);
				if($data != 'unavailable') {
					$result[$rbl] = $data;
				}
			}
		}

		// do we already have data for all RBL numbers?
		if(count($missing_ids) == 0) {
			break;
		}

		// acquire exclusive access to the memcache key 'rbl_currently_fetched'
		if(!sem_acquire($sem)) {
			// TODO error
			die();
		}

		// check again whether some data has been added to memcache
		foreach($missing_ids as $key => $rbl) {
			$data = cache_get("rbl_$rbl");
			if($data) {
				unset($missing_ids[$key]);
				$result[$rbl] = $data;
			}
		}

		// $not_fetched_ids contains all missing RBL numbers that are not currently being processed by
		// any other instance of the application; these are the RBL numbers that will be processed by
		// this instance
		$not_fetched_ids = $missing_ids;
		if(count($not_fetched_ids) > 0) {
			// fetch all RBL numbers that are currently being processed by other instances
			$fetched_ids = cache_get("rbl_currently_fetched");
			if(!$fetched_ids || !is_array($fetched_ids)) {
				$fetched_ids = array();
			}

			// the key 'rbl_currently_fetched' is an array using the RBL number as
			// key and the UNIX timestamp when the number was added to the array as
			// value
			foreach($fetched_ids as $id => $timestamp) {
				// if the number has been added more than 60 seconds ago, we can
				// assume that something bad happened to the instance that
				// processed it
				if(time()-$timestamp > 60) {
					unset($fetched_ids[$id]);
					continue;
				}

				// remove the number from the list of numbers processed by this instance
				if(($pos = array_search($id, $not_fetched_ids)) !== false) {
					unset($not_fetched_ids[$pos]);
				}
			}

			// add the RBL numbers processed by this instance...
			foreach($not_fetched_ids as $id) {
				$fetched_ids[$id] = time();
			}

			// .. and store the array using memcache
			cache_set("rbl_currently_fetched", $fetched_ids, $rbl_cache_time);
		}

		// release exclusive access to the memcache key 'rbl_currently_fetched'
		if(!sem_release($sem)) {
			// TODO error
			die();
		}

		if(count($not_fetched_ids) == 0) {
			// if there is no RBL number we need that is not currently being processed by another
			// instance of the application, we'll sleep for 500ms and hope that the data will then
			// be available
			usleep(500000);
		}
		else {
			// now, fetch the data
			$url = 'http://www.wienerlinien.at/ogd_realtime/monitor?rbl=' . implode(',', $not_fetched_ids) . "&sender=$wl_api_key";
			$cache_expiration = -1; // TODO hmmm
			$debug = false;
			$input_encoding = 'UTF-8';
			$data = download_json($url, 'rbl_' . implode('.', $not_fetched_ids));

			$rbl_data = array();
			foreach($data->data->monitors as $monitor) {
				$rbl = $monitor->locationStop->properties->attributes->rbl;
				$lines = $monitor->lines;
				if(!isset($rbl_data[$rbl])) {
					$rbl_data[$rbl] = array();
				}
				$rbl_data[$rbl][] = $lines;
			}

			// process the data and make it available to other instances of the application
			// using memcache
			foreach($rbl_data as $index => $value) {
				$result[$index] = process_rbl_data($value);
				cache_set("rbl_$index", $result[$index], 60);
			}

			// mark an RBL numbers as unavailable in case no data could be fetched for it
			foreach($not_fetched_ids as $id) {
				if(!isset($rbl_data[$id])) {
					cache_set("rbl_$id", 'unavailable', 60);
				}
			}

			// acquire exclusive access to the memcache key 'rbl_currently_fetched'
			if(!sem_acquire($sem)) {
				// TODO error
				die();
			}

			// obtain the list of all RBL numbers currently being processed by any
			// of the instances of the application
			$fetched_ids = cache_get("rbl_currently_fetched");
			if(!$fetched_ids || !is_array($fetched_ids)) {
				$fetched_ids = array();
			}

			// remove the RBL numbers that have just been processed from both
			// the list of RBL numbers currently being processed by any instance
			// of the program and the list of RBL numbers that have yet to be
			// processed by this instance in any of the next iterations of the
			// loop
			foreach($not_fetched_ids as $id) {
				unset($fetched_ids[$id]);
				unset($missing_ids[array_search($id, $missing_ids)]);
			}

			// save the list of all RBL numbers currently being processed by any
			// of the instances of the application back to memcache
			cache_set("rbl_currently_fetched", $fetched_ids, 3600); // TODO magic number

			// release exclusive access to the memcache key 'rbl_currently_fetched'
			if(!sem_release($sem)) {
				// TODO error
				die();
			}
		}
	}

	// we're done here
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
			foreach($line->departures->departure as $departure) {
				$line_name = (isset($departure->vehicle) && $departure->vehicle->towards) ? $departure->vehicle->name : $line->name;
				$towards = (isset($departure->vehicle) && $departure->vehicle->towards) ? $departure->vehicle->towards : $line->towards;
				$line_id = get_line_id($line_name);

				$departures[] = array(
					'line' => $line_name,
					'line_id' => $line_id,
					'towards' => $towards,
					'towards_id' => possible_destination($line_id, $towards),
					'barrier_free' => (isset($departure->vehicle) && $departure->vehicle->barrierFree) ? $departure->vehicle->barrierFree : $line->barrierFree,
					'time' => $departure->departureTime->countdown
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
