<?php

// TODO check: only run as standalone script from command line

require_once(dirname(__FILE__) . '/../lib/common.php');
require_once(dirname(__FILE__) . '/../lib/twitteroauth/twitteroauth.php');

$input_encoding = 'UTF-8';

$disruptions_url = "http://www.wienerlinien.at/ogd_realtime/trafficInfoList?sender=$wl_api_key";
$cache_expiration = 290; // TODO configurable
$data = download_json($disruptions_url, 'disruptions');

$imported_disruptions = array();

if(isset($data) && $data && isset($data->data) && isset($data->data->trafficInfoCategoryGroups) && isset($data->data->trafficInfoCategories) && isset($data->data->trafficInfos)) {
	$lockfile = fopen($disruptions_lockfile, 'w');
	if(flock($lockfile, LOCK_EX + LOCK_NB)) {
		check_category_groups($data->data->trafficInfoCategoryGroups);
		check_categories($data->data->trafficInfoCategories);
		process_traffic_infos($data->data->trafficInfos);
		check_outdated($imported_disruptions, 'traffic_info');

		require_once(dirname(__FILE__) . '/merge_traffic_infos.php');

		notify_twitter();

		flock($lockfile, LOCK_UN);
		fclose($lockfile);
		unlink($disruptions_lockfile);

		db_query('INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?', array('last_update', time(), time()));
		touch(dirname(__FILE__) . '/../misc/last_update');
	}
	else {
		write_log('Import of disruptions data already running, aborting now.');
		fclose($lockfile);
	}
}

log_query_stats();

function process_traffic_infos($infos) {
	global $imported_disruptions;

	foreach($infos as $info) {
		$priority = isset($info->priority) ? $info->priority : null;
		$owner = isset($info->owner) ? $info->owner : null;
		$start_time = (isset($info->time) && isset($info->time->start)) ? strtotime($info->time->start) : null;
		$end_time = (isset($info->time) && isset($info->time->end)) ? strtotime($info->time->end) : null;
		$resume_time = (isset($info->time) && isset($info->time->resume)) ? strtotime($info->time->resume) : null;

		$data = db_query('SELECT id FROM traffic_info WHERE wl_id = ? AND deleted = 0', array($info->name));
		if(count($data) == 0) {
			db_query('INSERT INTO traffic_info (wl_id, category, priority, owner, title, description, start_time, end_time, resume_time) VALUES (?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), FROM_UNIXTIME(?))', array($info->name, $info->refTrafficInfoCategoryId, $priority, $owner, $info->title, $info->description, $start_time, $end_time, $resume_time));
			$id = db_last_insert_id();

			write_log("Added disruption $id");
		}
		else {
			$id = $data[0]['id'];
			db_query('UPDATE traffic_info SET category = ?, priority = ?, owner = ?, title = ?, description = ?, start_time = FROM_UNIXTIME(?), end_time = FROM_UNIXTIME(?), resume_time = FROM_UNIXTIME(?), deleted = 0, timestamp_deleted = NULL WHERE id = ?', array($info->refTrafficInfoCategoryId, $priority, $owner, $info->title, $info->description, $start_time, $end_time, $resume_time, $id));

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
					report_problem("Unknown RBL number $rbl for disruption $id. Disruption data:\n\n" . var_dump($info), debug_backtrace());
				}
			}

			write_log("Updated platform data for disruption $id");
		}

		$feed_line_ids = array();
		if(isset($info->relatedLines) && count($info->relatedLines) > 0) {
			$placeholders = array();
			foreach($info->relatedLines as $line) {
				$placeholders[] = '?';
			}
			$placeholder_string = implode(', ', $placeholders);
			$data = db_query("SELECT id FROM line WHERE name IN ($placeholder_string) AND deleted = 0", $info->relatedLines);
			foreach($data as $row) {
				$feed_line_ids[] = $row['id'];
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
	foreach($groups as $group) {
		$data = db_query('SELECT id FROM traffic_info_category_group WHERE id = ?', array($group->id));
		if(count($data) == 0) {
			db_query('INSERT INTO traffic_info_category_group (id, name) VALUES (?, ?)', array($group->id, $group->name));
			write_log("Added traffic info category group {$group->id} ({$group->name})");
		}
	}
}

function check_categories($categories) {
	foreach($categories as $category) {
		$data = db_query('SELECT id FROM traffic_info_category WHERE id = ?', array($category->id));
		if(count($data) == 0) {
			db_query('INSERT INTO traffic_info_category (id, `group`, name, title) VALUES (?, ?, ?, ?)', array($category->id, $category->refTrafficInfoCategoryGroupId, $category->name, $category->title));
			write_log("Added traffic info category {$category->id} ({$category->title})");
		}
	}
}

function notify_twitter() {
	global $twitter, $twitter_consumer_key, $twitter_consumer_secret, $twitter_oauth_token, $twitter_oauth_token_secret, $twitter_hashtag;

	if(!$twitter) {
		return;
	}

	$disruptions = array_reverse(get_disruptions(array('twitter' => 0, 'deleted' => 0, 'limit' => -1)));
	if(count($disruptions) > 0) {
		write_log("Sending notifications about " . count($disruptions) . " disruptions to twitter.");
	}

	$connection = new TwitterOAuth($twitter_consumer_key, $twitter_consumer_secret, $twitter_oauth_token, $twitter_oauth_token_secret);
	$connection->get('account/verify_credentials');

	$ids = array();
	foreach($disruptions as $disruption) {
		$data = db_query('SELECT id FROM traffic_info WHERE `group` = ? AND id IN (SELECT id FROM traffic_info_twitter)', array($disruption['group']));
		if(count($data) > 0) {
			write_log("Skipping sending notification for disruption(s) " . implode(', ', $disruption['ids']) . " as a notification has already been sent for at least one item in the same group.");
			$ids = array_merge($ids, $disruption['ids']);
			continue;
		}

		write_log("Sending notification for disruption(s) " . implode(', ', $disruption['ids']));

		$link = "https://rueckgr.at/wienerlinien/disruptions/?id=" . $disruption['id'];

		$disruption_text = '';
		if(count($disruption['lines']) > 0) {
			$disruption_text .= implode('/', $disruption['lines']) . ': ';
		}
		$disruption_text .= '[' . $disruption['category'] . '] ';

		$title = str_replace("\n", " ", $disruption['title']);
		foreach($disruption['lines'] as $line) {
			$title = str_replace("$line ", '', $title);
		}
		$disruption_text .= $title;

		if(mb_strlen($disruption_text, 'UTF-8') > 110) {
			$disruption_text = mb_substr($disruption_text, 0, 109, 'UTF-8') . '…';
		}
		$disruption_text .= " $link";
		if(isset($twitter_hashtag) && trim($twitter_hashtag) != '') {
			$disruption_text .= " $twitter_hashtag";
		}

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
		write_log("Sending notifications completed.");

		$placeholders = array();
		foreach($ids as $id) {
			$placeholders[] = '(?)';
		}
		$placeholder_string = implode(',', $placeholders);
		db_query("INSERT INTO traffic_info_twitter (id) VALUES $placeholder_string", $ids);
	}
}

