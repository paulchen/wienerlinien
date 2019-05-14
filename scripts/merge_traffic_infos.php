<?php
/* In this file, several entries in the database table 'traffic_info' are organized into groups
 * in case they describe the same event; two items are assumed to describe the same item if
 * 1) a set of fields is equal and
 * 2) the difference of their start times does not exceed a certain limit.
 *
 * The equality of the fields of two items is determined by calculating a hash sum from the
 * values of these fields of the two items.
 */


/* list of fields that must be equal for a set of items to be organized into a group */
$comparison_fields = array('category', 'priority', 'owner', 'title', 'description', 'deleted');

/* maximum difference of the start times of two items in the same group (in seconds) */
$time_difference = 1800;

$modified_groups = array();

/* generate a hash from the array values of $row denoted by the keys given by the array $fields */
function calculate_hash($row, $fields) {
	$string = '';
	foreach($fields as $field) {
		$string .= $row[$field];
	}
	return md5($string);
}

write_log("merge step 1");

/* STEP 1: fetch data about existing groups; iterate each group and compare their hashes;
 * if the hashes differ, add the group to $kill_groups */
$data = db_query_resultset('SELECT id, category, COALESCE(priority, 0) priority, owner, title, description, `group`, deleted
		FROM traffic_info
		WHERE NOT `group` IS NULL
			AND (deleted = 0 OR timestamp_deleted > DATE_SUB(NOW(), INTERVAL 100 DAY))
		ORDER BY `group` ASC');
$kill_groups = array();
while($row = $data->fetch(PDO::FETCH_ASSOC)) {
	$hash = calculate_hash($row, $comparison_fields);
	if(isset($previous_group) && $previous_group == $row['group'] && $previous_hash != $hash) {
		if(!in_array($row['group'], $kill_groups)) {
			$kill_groups[] = $row['group'];
		}
	}

	$previous_group = $row['group'];
	$previous_hash = $hash;
}
db_stmt_close($data);

/* eliminate all groups named in $kill_groups */
if(count($kill_groups) > 0) {
	write_log('Killing group(s): ' . implode(', ', $kill_groups));

	$placeholders = array();
	foreach($kill_groups as $group) {
		$placeholders[] = '?';
	}
	$placeholder_string = implode(',',$placeholders);
	db_query("UPDATE traffic_info SET `group` = NULL WHERE `group` IN ($placeholder_string)", $kill_groups);
}

write_log("merge step 2");

/* create the array $existing_hashes, containing the hashes of all existing groups as keys;
 * the values of this array are in turn arrays, having the group ID as key and the timestamp
 * of one item of the group as value
 */
$data = db_query_resultset('SELECT id, category, priority, owner, title, description, `group`, start_time, deleted
		FROM traffic_info
		WHERE NOT `group` IS NULL
			AND (deleted = 0 OR timestamp_deleted > DATE_SUB(NOW(), INTERVAL 100 DAY))
		ORDER BY `group` ASC');
$existing_hashes = array();
while($row = $data->fetch(PDO::FETCH_ASSOC)) {
	$hash = calculate_hash($row, $comparison_fields);
	if(!isset($existing_hashes[$hash])) {
		$existing_hashes[$hash] = array();
	}
	$existing_hashes[$hash][$row['group']] = $row['start_time'];
}
db_stmt_close($data);

write_log("merge step 3");

/* Process all items that currently do not belong to any group */
$data = db_query_resultset('SELECT id, timestamp_created, category, priority, owner, title, description, start_time, end_time, resume_time, deleted
		FROM traffic_info
		WHERE `group` IS NULL
			AND (deleted = 0 OR timestamp_deleted > DATE_SUB(NOW(), INTERVAL 100 DAY))
		ORDER BY start_time ASC');
$groups = array();
$add_to_existing_groups = array();
while($row = $data->fetch(PDO::FETCH_ASSOC)) {
	$hash = calculate_hash($row, $comparison_fields);
	if(isset($existing_hashes[$hash])) {
		/* if the hash of this item is already known, it may be added to an existing group */
		foreach($existing_hashes[$hash] as $group_id => $timestamp) {
			/* however, it will only be added to an existing group if its start time
			 * corresponds to the start times of the other items in the group
			 */
			$item_time = get_item_time($row);
			if(abs(strtotime($timestamp)-get_item_time($row)) < $time_difference) {
				if(!isset($add_to_existing_groups[$group_id])) {
					$add_to_existing_groups[$group_id] = array();
				}
				$add_to_existing_groups[$group_id][] = $row['id'];

				continue 2;
			}
		}
	}
	/* all items that are not added to existing groups are instead organized into new groups */
	if(!isset($groups[$hash])) {
		$groups[$hash] = array();
	}
	$groups[$hash][] = $row;
}
db_stmt_close($data);
unset($row); // this is necessary as $row is used as a reference in the above foreach loop

write_log("merge step 4");

/* now, add items to existing groups */
foreach($add_to_existing_groups as $group_id => $group) {
	write_log("Adding items to group $group_id: " . implode(', ', $group));

	if(!in_array($group_id, $modified_groups)) {
		$modified_groups[] = $group_id;
	}

	$placeholders = array();
	foreach($group as $item) {
		$placeholders[] = '?';
	}
	$placeholder_string = implode(',', $placeholders);
	array_unshift($group, $group_id);
	db_query("UPDATE traffic_info SET `group` = ? WHERE id IN ($placeholder_string)", $group);
}

write_log("merge step 5");

/* Now, process the array $groups:
 * 1) delete empty groups
 * 2) split groups if there is a gap in the item's start times
 *
 * As the array $groups is modified inside the foreach loop, the foreach loop is restarted
 * using an outside while loop whenever the array is modified; once the foreach loop completes
 * without the array being modified, the outside loop terminates.
 */
$groups_modified = true;
while($groups_modified) {
	$groups_modified = false;
	foreach($groups as $index => &$group) {
		foreach($group as $index2 => $item) {
			if($index2 > 0 && get_item_time($item)-get_item_time($group[$index2-1]) > $time_difference) {
				/* split the group by moving all items from $index2 to the end into a new group
				 * and deleting them from the current group */

				$new_group = array();
				for($a=$index2; $a<count($group); $a++) {
					$new_group[] = $group[$a];
				}
				while(count($group) > $index2) {
					array_pop($group);
				}
				$groups[] = $new_group;

				$groups_modified = true;
				continue 3;
			}
		}
	}
}
unset($group); // this is necessary to avoid problems regarding the above foreach loop where &$group is used

write_log("merge step 6");

/* now, store the new groups in the database */
$data = db_query('SELECT COALESCE(MAX(`group`), 0) max_group FROM traffic_info');
$group_id = $data[0]['max_group'];

foreach($groups as $group) {
	$group_id++;
	$parameters = array_map(function($a) { return $a['id']; }, $group);
	write_log("Creating group $group_id from items: " . implode(', ', $parameters));

	array_unshift($parameters, $group_id);
	$placeholders = array();
	foreach($parameters as $parameter) {
		$placeholders[] = '?';
	}
	array_pop($placeholders);
	$placeholder_string = implode(',', $placeholders);
	db_query("UPDATE traffic_info SET `group` = ? WHERE id IN ($placeholder_string)", $parameters);
}

write_log("merge step 7");

/* remove groups from traffic_info_group that don't exist anymore */
db_query('DELETE FROM traffic_info_group WHERE id NOT IN (SELECT `group` FROM traffic_info)');

/* populate table traffic_info_group */
function fill_group_line_table($line_params, $group, $force = false) {
	global $db;

	if($group) {
		if(trim($group['lines']) == '') {
			return $line_params;
		}

		$lines = explode(',', $group['lines']);
		foreach($lines as $line) {
			$line_params[] = $group['group'];
			$line_params[] = $line;
		}
	}

	if(count($line_params) < 1000 && !$force) {
		return $line_params;
	}
	if(count($line_params) == 0) {
		return $line_params;
	}

	$query = 'INSERT INTO traffic_info_group_line (traffic_info, line) VALUES ';
	$params = array();
	$first = true;
	for($a=0; $a<count($line_params)/2; $a++) {
		if(!$first) {
			$query .= ',';
		}
		$first = false;
		$query .= '(?,?)';
	}
	db_query($query, $line_params);

	return array();
}

$imported_disruptions = array_merge($imported_disruptions, $outdated_disruptions);
if(count($imported_disruptions) > 0) {
	$query = 'SELECT `group` FROM traffic_info WHERE id IN (';
	$first = true;
	foreach($imported_disruptions as $disruption) {
		if(!$first) {
			$query .= ',';
		}
		$first = false;
		$query .= '?';
	}
	$query .= ')';
	$data = db_query($query, $imported_disruptions);
	foreach($data as $row) {
		if(!in_array($row['group'], $modified_groups)) {
			$modified_groups[] = $row['group'];
		}
	}
}

db_query('DELETE FROM traffic_info_group_line WHERE traffic_info NOT IN (SELECT `group` FROM traffic_info)');
db_query('DELETE FROM traffic_info_group WHERE id NOT IN (SELECT `group` FROM traffic_info)');

$placeholders = implode(',', array_fill(0, count($modified_groups), '?'));
$data = db_query("SELECT `group`, category, priority, owner, title, description, deleted, MAX(COALESCE(start_time, timestamp_created)) start_time, MAX(end_time) end_time, MAX(resume_time) resume_time, MAX(timestamp_deleted) timestamp_deleted, GROUP_CONCAT(DISTINCT til.line SEPARATOR ',') AS `lines` FROM traffic_info ti JOIN traffic_info_line til ON (ti.id = til.traffic_info) WHERE `group` IN (SELECT id FROM traffic_info_group) AND `group` IN ($placeholders) GROUP BY `group`, category, priority, owner, title, description, deleted", $modified_groups);
$line_params = array();

$data2 = db_query("SELECT id, category, priority, owner, title, description, deleted, start_time, end_time, resume_time, timestamp_deleted FROM traffic_info_group WHERE id IN ($placeholders)", $modified_groups);
$data3 = array();
foreach($data2 as $row) {
	$data3[$row['id']] = $row;
}

$data4 = db_query("SELECT traffic_info, line FROM traffic_info_group_line WHERE traffic_info IN ($placeholders)", $modified_groups);
$data5 = array();
foreach($data4 as $row) {
	if(!isset($data5[$row['traffic_info']])) {
		$data5[$row['traffic_info']] = array();
	}
	$data5[$row['traffic_info']][] = $row['line'];
}

foreach($data as $group) {
	$stored_group = $data3[$group['group']];
	$equal = true;
	foreach(array('category', 'priority', 'owner', 'title', 'description', 'deleted', 'start_time', 'end_time', 'resume_time', 'timestamp_deleted') as $key) {
		if($group[$key] != $stored_group[$key]) {
			write_log("$key: {$group[$key]} - {$stored_group[$key]}");
			$equal = false;
			break;
		}
	}

	if(!$equal) {
		$query = 'UPDATE traffic_info_group SET category = ?, priority = ?, owner = ?, title = ?, description = ?, deleted = ?, start_time = ?, end_time = ?, resume_time = ?, timestamp_deleted = ? WHERE id = ?';
		db_query($query, array($group['category'], $group['priority'], $group['owner'], $group['title'], $group['description'], $group['deleted'], $group['start_time'], $group['end_time'], $group['resume_time'], $group['timestamp_deleted'], $group['group']));
	}

	$lines = $data5[$group['group']];
	sort($lines);

	$stored_lines = explode(',', $group['lines']);
	sort($stored_lines);

	if($lines == $stored_lines) {
		continue;
	}
	write_log("Updating lines of group {$group['group']}");

	db_query('DELETE FROM traffic_info_group_line WHERE traffic_info = ?', array($group['group']));

	$line_params = fill_group_line_table($line_params, $group);
}

$data = db_query("SELECT `group`, category, priority, owner, title, description, deleted, MAX(COALESCE(start_time, timestamp_created)) start_time, MAX(end_time) end_time, MAX(resume_time) resume_time, MAX(timestamp_deleted) timestamp_deleted, GROUP_CONCAT(DISTINCT til.line SEPARATOR ',') AS `lines` FROM traffic_info ti JOIN traffic_info_line til ON (ti.id = til.traffic_info) WHERE `group` NOT IN (SELECT id FROM traffic_info_group) AND `group` IN ($placeholders) GROUP BY `group`, category, priority, owner, title, description, deleted", $modified_groups);
foreach($data as $group) {
	$query = 'INSERT INTO traffic_info_group (category, priority, owner, title, description, deleted, start_time, end_time, resume_time, timestamp_deleted, id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
	db_query($query, array($group['category'], $group['priority'], $group['owner'], $group['title'], $group['description'], $group['deleted'], $group['start_time'], $group['end_time'], $group['resume_time'], $group['timestamp_deleted'], $group['group']));

	$line_params = fill_group_line_table($line_params, $group);
}

fill_group_line_table($line_params, null, true);

