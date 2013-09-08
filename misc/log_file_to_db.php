<?php
require_once(dirname(__FILE__) . '/../lib/common.php');

$parameters = array();
$placeholders = array();

db_query('TRUNCATE TABLE log');

$file = fopen(dirname(__FILE__) . '/../log/log','r');
$lines = 0;
while(!feof($file)) {
	$line = fgets($file);
	if(!preg_match('/^\[/', $line)) {
		continue;
	}
	$pos = mb_strpos($line, '- ', 0, 'UTF-8');
	if($pos === false) {
		continue;
	}
	$date_part = mb_substr($line, 1, $pos-3, 'UTF-8');
	$message = mb_substr($line, $pos+2, mb_strlen($line, 'UTF-8'), 'UTF-8');

	$parameters[] = $date_part;
	$parameters[] = $message;
	$placeholders[] = '(?, ?)';
	$lines++;

	if(count($placeholders) == 1000) {
		echo "$lines log lines imported.\n";
		write_data();
	}

	if(count($db_queries) % 1000 == 0) {
	}
}
fclose($file);
echo "$lines log lines imported.\n";
write_data();

function write_data() {
	global $parameters, $placeholders;

	if(count($placeholders) == 0) {
		return;
	}

	$placeholder_string = implode(',', $placeholders);

	db_query("INSERT INTO log (timestamp, text) VALUES $placeholder_string", $parameters);

	$parameters = array();
	$placeholders = array();
}

