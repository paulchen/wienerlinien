$(document).ready(function() {
	$('#last_update').html('Letzte Aktualisierung: nie');
	update_countdown(0);
});

var timeout;

function update_countdown(seconds) {
	if(seconds == 0) {
		$('#next_update span').html('Aktualisierung läuft...');
		$('#next_update > img').show();
		$('#next_update > a').hide();
		update_rbls();
	}
	else {
		$('#next_update span').html('Nächste Aktualisierung in ' + seconds + ' Sekunden');
		$('#next_update > img').hide();
		$('#next_update > a').show();
		timeout = window.setTimeout('update_countdown(' + (seconds-1) + ');', 1000);
	}

}

function force_refresh() {
	window.clearTimeout(timeout);
	update_countdown(0);
}

function process_result(data) {
	$.each(rbls, function(index, rbl) {
		if(rbl in data) {
			var content = '<table class="rbl_info">';

			$.each(data[rbl], function(index2, row) {
				var line = row['line'];
				var towards = row['towards'];
				var time = row['time'];
				var line_link = 'line.htm?id=' + row['line_id'];
				var barrier_free = '<span style="visibility: hidden;">&#x267f;</span>';
				if(row['folding_ramp']) {
					barrier_free = '&#x2581;';
				}
				else if(row['barrier_free']) {
					barrier_free = '&#x267f;';
				}

				content += '<tr>';
				if(line == null) {
					content += '<td></td>';
				}
				else if(row['line_id'] == null) {
					content += '<td>' + line + '</td>';
				}
				else {
					content += '<td><a href="' + line_link + '">' + line + '</a></td>';
				}
				if(row['towards_id'] == null) {
					content += '<td>' + towards + '</td>';
				}
				else {
					content += '<td><a href="station.htm?id=' + row['towards_id'] + '">' + towards + '</a></td>';
				}
				if(time == null) {
					content += '<td></td><td></td>';
				}
				else {
					content += '<td>' +  time + '</td><td>' + barrier_free + '</td>';
				}
				content += '</tr>';
			});

			content += '</table>';
		}
		else {
			content = 'Derzeit sind für diesen Bahnsteig keine Abfahrtsinformationen verfügbar.';
		}

		$('#rbl_' + rbl).html(content);
	});
	$('#last_update').html('Letzte Aktualisierung: ' + (new Date()).toString('dd.MM.yyyy HH:mm:ss'));
}

function update_rbls() {
	if(rbls.length == 0) {
		process_result([]);
		update_countdown(30);
		return;
	}
	$.ajax({
		url: 'rbls.php?ids=' + rbls.join(','),
		dataType: 'json',
		success: function(data, text, xhr) {
			process_result(data);
		},
		complete: function(xhr, text) {
			update_countdown(30);
		}
	});
}

