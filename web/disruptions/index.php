<?php
require_once(dirname(__FILE__) . '/../../lib/common.php');

$disruptions = db_query("SELECT i.title title, i.description description, COALESCE(i.start_time, e.start_time, i.timestamp_created) time,
				GROUP_CONCAT(DISTINCT l.name ORDER BY l.name ASC SEPARATOR ',') `lines`,
				GROUP_CONCAT(DISTINCT s.name ORDER BY s.name ASC SEPARATOR ',') `stations`
			FROM traffic_info i
				LEFT JOIN traffic_info_elevator e ON (i.id = e.id)
				LEFT JOIN traffic_info_line til ON (i.id = til.traffic_info)
				LEFT JOIN line l ON (til.line = l.id)
				LEFT JOIN traffic_info_platform tip ON (i.id = tip.traffic_info)
				LEFT JOIN wl_platform p ON (tip.platform = p.id)
				LEFT JOIN station s ON (p.station = s.id)
			WHERE i.deleted = 0
			GROUP BY i.id, title, description, time
			ORDER BY time ASC");
foreach($disruptions as &$disruption) {
	if($disruption['lines'] == '') {
		$disruption['lines'] = array();
	}
	else {
		$disruption['lines'] = explode(',', $disruption['lines']);
	}
	if($disruption['stations'] == '') {
		$disruption['stations'] = array();
	}
	else {
		$disruption['stations'] = explode(',', $disruption['stations']);
	}
}

require_once(dirname(__FILE__) . '/../../templates/disruptions.php');

