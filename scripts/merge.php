<?php
require_once('../lib/common.php');

// TODO check for existing groups whether additional items exist

$comparison_fields = array('category', 'priority', 'owner', 'title', 'description');

function calculate_hash($row, $fields) {
	$string = '';
	foreach($fields as $field) {
		$string .= $row[$field];
	}
	return md5($string);
}

// TODO deleted == 0
$data = db_query('SELECT id, category, priority, owner, title, description, `group`
		FROM traffic_info
		WHERE NOT `group` IS NULL
		ORDER BY `group` ASC');
$kill_groups = array();
foreach($data as $row) {
	$hash = calculate_hash($row, $comparison_fields);
	if(isset($previous_group) && $previous_group == $row['group'] && $previous_hash != $hash) {
		if(!in_array($row['group'], $kill_groups)) {
			$kill_groups[] = $row['group'];
		}
	}

	$previous_group = $row['group'];
	$previous_hash = $hash;
}
if(count($kill_groups) > 0) {
	$placeholders = array();
	foreach($kill_groups as $group) {
		$placeholders[] = '?';
	}
	$placeholder_string = implode(',',$placeholders);
	db_query("UPDATE traffic_info SET `group` = NULL WHERE `group` IN ($placeholder_string)", $kill_groups);
}

// TODO deleted == 0
$data = db_query('SELECT id, timestamp_created, category, priority, owner, title, description, start_time, end_time, resume_time
		FROM traffic_info
		WHERE `group` IS NULL
		ORDER BY start_time ASC');
//		WHERE id IN (40, 69, 142, 167, 238, 307, 378, 562)	
$groups = array();
foreach($data as &$row) {
	$hash = calculate_hash($row, $comparison_fields);
	if(!isset($groups[$hash])) {
		$groups[$hash] = array();
	}
	$groups[$hash][] = $row;
}
$groups_modified = true;
while($groups_modified) {
//	print_r($groups);
//	if(count($groups) > 10) {
//		die();
//	}
//	echo count($groups) . "\n";
	$groups_modified = false;
	foreach($groups as $index => &$group) {
		if(count($group) == 1) {
			unset($groups[$index]);
			$groups_modified = true;
			continue 2;
		}
		foreach($group as $index2 => $item) {
			if($index2 > 0 && strtotime($item['start_time'])-strtotime($group[$index2-1]['start_time']) > 1800) { // TODO magic number
//				print_r($group);
//				die();

				$new_group = array();
				for($a=$index2; $a<count($group); $a++) {
//					print_r($group[$a]);
					$new_group[] = $group[$a];
				}
				while(count($group) > $index2) {
					array_pop($group);
				}
				$groups[] = $new_group;

//				print_r($new_group);
//				print_r($group);
//				die();

				$groups_modified = true;
				continue 3;
			}
		}
	}
}
// print_r($groups);

$data = db_query('SELECT COALESCE(MAX(`group`), 0) max_group FROM traffic_info');
$group_id = $data[0]['max_group'];

foreach($groups as $group) {
	$group_id++;
	$parameters = array_map(function($a) { return $a['id']; }, $group);
	array_unshift($parameters, $group_id);
	$placeholders = array();
	foreach($parameters as $parameter) {
		$placeholders[] = '?';
	}
	array_pop($placeholders);
	$placeholder_string = implode(',', $placeholders);
	db_query("UPDATE traffic_info SET `group` = ? WHERE id IN ($placeholder_string)", $parameters);
}

