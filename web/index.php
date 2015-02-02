<?php
require_once(dirname(__FILE__) . '/../lib/common.php');

$twitter_usernames = array();
foreach($twitter as $item) {
	$username = $item['twitter_username'];
	if(!in_array($username, $twitter_usernames)) {
		$twitter_usernames[] = $item['twitter_username'];
	}
}

require_once(dirname(__FILE__) . '/../templates/index.php');

