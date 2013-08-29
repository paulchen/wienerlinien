<?php
require_once(dirname(__FILE__) . '/../config.php');

$db = new PDO("mysql:dbname=$db_name;host=$db_host", $db_user, $db_pass);
db_query('SET NAMES UTF8');

$template_dir = dirname(__FILE__) . '/../templates/';

function db_query($query, $parameters = array()) {
	global $db, $db_queries;

	$query_start = microtime(true);
	if(!($stmt = $db->prepare($query))) {
		$error = $db->errorInfo();
		db_error($error[2], debug_backtrace(), $query, $parameters);
	}
	// see https://bugs.php.net/bug.php?id=40740 and https://bugs.php.net/bug.php?id=44639
	foreach($parameters as $key => $value) {
		$stmt->bindValue($key+1, $value, is_numeric($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
	}
	if(!$stmt->execute()) {
		$error = $stmt->errorInfo();
		db_error($error[2], debug_backtrace(), $query, $parameters);
	}
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if(!$stmt->closeCursor()) {
		$error = $stmt->errorInfo();
		db_error($error[2], debug_backtrace(), $query, $parameters);
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

	header('HTTP/1.1 500 Internal Server Error');
	echo "A database error has just occurred. Please don't freak out, the administrator has already been notified.\n$error";
	die();

	/* TODO
	$params = array(
			'ERROR' => $error,
			'STACKTRACE' => dump_r($stacktrace),
			'QUERY' => $query,
			'PARAMETERS' => dump_r($parameters),
			'REQUEST_URI' => (isset($_SERVER) && isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : 'none',
		);
	send_mail('db_error.php', 'Database error', $params, true);
	 */
}

function dump_r($variable) {
	ob_start();
	print_r($variable);
	$data = ob_get_contents();
	ob_end_clean();

	return $data;
}

function db_last_insert_id() {
	global $db;

	$data = db_query('SELECT lastval() id');
	return $data[0]['id'];
}

