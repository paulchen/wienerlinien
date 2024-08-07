<?php

// TODO check: only run as standalone script from command line

$use_transaction = true;
$long_running_queries = true;

require_once(dirname(__FILE__) . '/../lib/common.php');
require_once(dirname(__FILE__) . '/../lib/twitteroauth/twitteroauth.php');

$input_encoding = 'UTF-8';

$disruptions_url = "https://www.wienerlinien.at/ogd_realtime/trafficInfoList?sender=$wl_api_key";
$cache_expiration = $cache_expiration_disruptions;
$data = download_json($disruptions_url, 'disruptions');

$imported_disruptions = array();

$release_lock = false;
if(isset($data) && $data && isset($data->data) && isset($data->data->trafficInfoCategoryGroups) && isset($data->data->trafficInfoCategories) && isset($data->data->trafficInfos)) {
	$lockfile = fopen($disruptions_lockfile, 'w');
	if(flock($lockfile, LOCK_EX + LOCK_NB)) {
		check_category_groups($data->data->trafficInfoCategoryGroups);
		check_categories($data->data->trafficInfoCategories);
		process_traffic_infos($data->data->trafficInfos);
		$outdated_disruptions = check_outdated($imported_disruptions, 'traffic_info');

		require_once(dirname(__FILE__) . '/merge_traffic_infos.php');

		// notify_twitter();

		$release_lock = true;

		db_query('INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?', array('last_update', time(), time()));
		touch(dirname(__FILE__) . '/../misc/last_update');
	}
	else {
		write_log('Import of disruptions data already running, aborting now.');
		fclose($lockfile);
	}
}

log_query_stats();
$db->commit();

if($release_lock) {
	flock($lockfile, LOCK_UN);
	fclose($lockfile);
	unlink($disruptions_lockfile);
}

function process_traffic_infos($infos) {
	global $imported_disruptions;
	
	$now = date('H:i');

	foreach($infos as $info) {
		if(preg_match('/stau in zufahrt/i', $info->title)) {
			continue;
		}

		$priority = isset($info->priority) ? $info->priority : null;
		$owner = isset($info->owner) ? $info->owner : null;
		$start_time = (isset($info->time) && isset($info->time->start)) ? strtotime($info->time->start) : null;
		$end_time = (isset($info->time) && isset($info->time->end)) ? strtotime($info->time->end) : null;
		$resume_time = (isset($info->time) && isset($info->time->resume)) ? strtotime($info->time->resume) : null;

		$data = db_query('SELECT id, description, last_description, UNIX_TIMESTAMP(COALESCE(start_time, timestamp_created)) timestamp_created FROM traffic_info WHERE wl_id = ? AND deleted = 0', array($info->name));
		if(count($data) == 0) {
			db_query('INSERT INTO traffic_info (wl_id, category, priority, owner, title, description, last_description, start_time, end_time, resume_time) VALUES (?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), FROM_UNIXTIME(?))', array($info->name, $info->refTrafficInfoCategoryId, $priority, $owner, $info->title, $info->description, $info->description, $start_time, $end_time, $resume_time));
			$id = db_last_insert_id();

			write_log("Added disruption $id");
		}
		else {
			$id = $data[0]['id'];
			$last_description = $data[0]['last_description'];
			$old_full_description = $data[0]['description'];
			$new_description = $info->description;
			if($last_description == $new_description) {
				$full_description = $old_full_description;
			}
			else {
				$full_description = $old_full_description;
				if(mb_strpos($old_full_description, 'Ursprüngliche Meldung', 0, 'UTF-8') === false) {
					$timestamp = date('H:i', $data[0]['timestamp_created']);
					$full_description = "Ursprüngliche Meldung ($timestamp): $full_description";
				}
				$full_description .= "\n\nUpdate ($now): $new_description";
			}

			db_query('UPDATE traffic_info SET category = ?, priority = ?, owner = ?, title = ?, description = ?, last_description = ?, start_time = FROM_UNIXTIME(?), end_time = FROM_UNIXTIME(?), resume_time = FROM_UNIXTIME(?), deleted = 0, timestamp_deleted = NULL WHERE id = ?', array($info->refTrafficInfoCategoryId, $priority, $owner, $info->title, $full_description, $info->description, $start_time, $end_time, $resume_time, $id));

			write_log("Updated disruption $id");
		}

		$imported_disruptions[] = $id;

		if(isset($info->attributes)) {
			$reason = isset($info->attributes->reason) ? $info->attributes->reason : null;
			$location = isset($info->attributes->location) ? $info->attributes->location : null;
			$station = isset($info->attributes->station) ? $info->attributes->station : null;
			$status = isset($info->attributes->status) ? $info->attributes->status : null;
			$start_time = isset($info->attributes->ausVon) ? strtotime($info->attributes->ausVon) : null;
			$end_time = isset($info->attributes->ausBis) ? strtotime($info->attributes->ausBis) : null;
			$towards = isset($info->attributes->towards) ? $info->attributes->towards : null;

			db_query('INSERT INTO traffic_info_elevator (id, reason, location, station, status, start_time, end_time, towards) VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), ?) ON DUPLICATE KEY UPDATE reason = ?, location = ?, station = ?, status = ?, start_time = FROM_UNIXTIME(?), end_time = FROM_UNIXTIME(?), towards = ?', array($id, $reason, $location, $station, $status, $start_time, $end_time, $towards, $reason, $location, $station, $status, $start_time, $end_time, $towards));

			write_log("Updated elevator data for disruption $id");
		}

		$data_lines = db_query('SELECT line FROM traffic_info_line WHERE traffic_info = ?', array($id));
		$db_line_ids = array();
		foreach($data_lines as $row) {
			$db_line_ids[] = $row['line'];
		}
		$data_platforms = db_query('SELECT DISTINCT(p.rbl) rbl FROM traffic_info_platform tip JOIN wl_platform p ON (tip.platform = p.id) WHERE tip.traffic_info = ?', array($id));
		$db_platform_ids = array();
		foreach($data_platforms as $row) {
			$db_platform_ids[] = $row['rbl'];
		}

		$feed_platform_ids = isset($info->relatedStops) ? $info->relatedStops : array();
		$difference = false;
		foreach($feed_platform_ids as $feed_id) {
			if(!in_array($feed_id, $db_platform_ids)) {
				$difference = true;
			}
		}
		if(!$difference) {
			foreach($db_platform_ids as $db_id) {
				if(!in_array($db_id, $feed_platform_ids)) {
					$difference = true;
				}
			}
		}
		if($difference) {
			db_query('DELETE from traffic_info_platform WHERE traffic_info = ?', array($id));

			if(isset($info->relatedStops) && count($info->relatedStops) > 0) {	
				$placeholders = array();
				$parameters = array();
				$missing_rbls = array();
				foreach($info->relatedStops as $stop) {
					$placeholders[] = '?';
					$parameters[] = $stop;
					$missing_rbls[$stop] = true;
				}
				$placeholder_string = implode(', ', $placeholders);
				$data = db_query("SELECT id, rbl FROM wl_platform WHERE rbl IN ($placeholder_string) AND deleted = 0", $parameters);
				$placeholders = array();
				$parameters = array();
				foreach($data as $row) {
					$placeholders[] = '(?, ?)';
					$parameters[] = $id;
					$parameters[] = $row['id'];

					if(isset($missing_rbls[$row['rbl']])) {
						unset($missing_rbls[$row['rbl']]);
					}
				}
				if(count($parameters) > 0) {
					$placeholder_string = implode(', ', $placeholders);
					db_query("INSERT INTO traffic_info_platform (traffic_info, platform) VALUES $placeholder_string", $parameters);
				}
				foreach($missing_rbls as $rbl => $value) {
					report_problem("Unknown RBL number $rbl for disruption $id. Disruption data:\n\n" . dump_r($info), debug_backtrace());
				}
			}

			write_log("Updated platform data for disruption $id");
		}

		$data = db_query("SELECT id, name FROM line WHERE deleted = 0");
		$line_mapping = array();
		foreach($data as $row) {
			$line_mapping[$row['name']] = $row['id'];
		}

		$feed_line_ids = array();
		if(isset($info->relatedLines)) {
			foreach($info->relatedLines as $relatedLine) {
				if(isset($line_mapping[$relatedLine])) {
					$feed_line_ids[] = $line_mapping[$relatedLine];
				}
			}
		}

		$difference = false;
		foreach($feed_line_ids as $feed_id) {
			if(!in_array($feed_id, $db_line_ids)) {
				$difference = true;
			}
		}
		if(!$difference) {
			foreach($db_line_ids as $db_id) {
				if(!in_array($db_id, $feed_line_ids)) {
					$difference = true;
				}
			}
		}
		if($difference) {
			db_query('DELETE from traffic_info_line WHERE traffic_info = ?', array($id));

			if(count($feed_line_ids) > 0) {	
				$placeholders = array();
				$parameters = array();
				foreach($feed_line_ids as $line_id) {
					$placeholders[] = '(?, ?)';
					$parameters[] = $id;
					$parameters[] = $line_id;
				}
				$placeholder_string = implode(', ', $placeholders);
				db_query("INSERT INTO traffic_info_line (traffic_info, line) VALUES $placeholder_string", $parameters);
			}

			write_log("Updated line data for disruption $id");
		}
	}
}

function check_category_groups($groups) {
	$db_groups = array_map(function($a) { return $a['id']; }, db_query('SELECT id FROM traffic_info_category_group'));
	foreach($groups as $group) {
		if(!in_array($group->id, $db_groups)) {
			db_query('INSERT INTO traffic_info_category_group (id, name) VALUES (?, ?)', array($group->id, $group->name));
			write_log("Added traffic info category group {$group->id} ({$group->name})");
		}
	}
}

function check_categories($categories) {
	$db_categories = array_map(function($a) { return $a['id']; }, db_query('SELECT id FROM traffic_info_category'));
	foreach($categories as $category) {
		if(!in_array($category->id, $db_categories)) {
			db_query('INSERT INTO traffic_info_category (id, `group`, name, title) VALUES (?, ?, ?, ?)', array($category->id, $category->refTrafficInfoCategoryGroupId, $category->name, $category->title));
			write_log("Added traffic info category {$category->id} ({$category->title})");
		}
	}
}

function notify_twitter() {
	global $twitter, $twitter_hashtag, $home_url;

	$disruptions = array_reverse(get_disruptions(array('twitter' => 0, 'deleted' => 0, 'limit' => -1)));
	if(count($disruptions) > 0) {
		write_log("Sending notifications about " . count($disruptions) . " disruptions to twitter.");
	}

	$connections = array();
	foreach($disruptions as $disruption) {
		$disruption_group = $disruption['category_id'];
		
		if(isset($connections[$disruption_group])) {
			continue;
		}

		if(!isset($twitter[$disruption_group])) {
			continue;
		}
		$connection = new TwitterOAuth($twitter[$disruption_group]['twitter_consumer_key'], $twitter[$disruption_group]['twitter_consumer_secret'], $twitter[$disruption_group]['twitter_oauth_token'], $twitter[$disruption_group]['twitter_oauth_token_secret']);
		$connection->get('account/verify_credentials');

		$connections[$disruption_group] = $connection;
	}

	$ids = array();
	foreach($disruptions as $disruption) {
		if(!isset($connections[$disruption['category_id']])) {
			continue;
		}

		$data = db_query('SELECT id FROM traffic_info WHERE `group` = ? AND id IN (SELECT id FROM traffic_info_twitter)', array($disruption['group']));
		if(count($data) > 0) {
			write_log("Skipping sending notification for disruption(s) " . implode(', ', $disruption['ids']) . " as a notification has already been sent for at least one item in the same group.");
			$ids = array_merge($ids, $disruption['ids']);
			continue;
		}

		write_log("Sending notification for disruption(s) " . implode(', ', $disruption['ids']));

		$link = $home_url . "disruptions/?id=" . $disruption['id'];

		$disruption_text = '';
		if(count($disruption['lines']) > 0) {
			$disruption_text .= implode('/', $disruption['lines']) . ': ';
		}
		$disruption_text .= '[' . $disruption['category'] . '] ';

		$title = str_replace("\n", " ", $disruption['title']);
//		foreach($disruption['lines'] as $line) {
//			$title = str_replace("$line ", '', $title);
//		}
		$disruption_text .= $title;

		if(mb_strlen($disruption_text, 'UTF-8') > 250) {
			$disruption_text = mb_substr($disruption_text, 0, 249, 'UTF-8') . '…';
		}
		$disruption_text .= " $link";
		if(isset($twitter_hashtag) && trim($twitter_hashtag) != '') {
			$disruption_text .= " $twitter_hashtag";
		}

		$connection = $connections[$disruption['category_id']];
		$connection->post('statuses/update', array('status' => $disruption_text));

		if ($connection->http_code == 200) {
			write_log("Sending notification succeeded.");
			$ids = array_merge($ids, $disruption['ids']);
		}
		else {
			write_log("Unable to send notification");
		}
	}

	if(count($ids) > 0) {
		$ids = array_unique($ids);

		write_log("Sending notifications completed.");

		$placeholders = array();
		foreach($ids as $id) {
			$placeholders[] = '(?)';
		}
		$placeholder_string = implode(',', $placeholders);
		db_query("INSERT INTO traffic_info_twitter (id) VALUES $placeholder_string", $ids);
	}
}

