$(document).ready(function() {
	$('#last_update').html('Letzte Aktivierung: nie');
	update_countdown(0);
});

function update_countdown(seconds) {
	if(seconds == 0) {
		$('#next_update span').html('Aktualisierung läuft...');
		$('#next_update img').show();
		update_rbls();
	}
	else {
		$('#next_update span').html('Nächste Aktualisierung in ' + seconds + ' Sekunden');
		$('#next_update img').hide();
		window.setTimeout('update_countdown(' + (seconds-1) + ');', 1000);
	}

}

function update_rbls() {
	$.ajax({
		url: 'rbls.php?ids=' + rbls.join(','),
		dataType: 'json',
		success: function(data, text, xhr) {
			$.each(rbls, function(index, rbl) {
				if(rbl in data) {
					var content = '<table class="rbl_info">';

					$.each(data[rbl], function(index2, row) {
						var line = row['line'];
						var towards = row['towards'];
						var time = row['time'];
						var line_link = 'line.htm?id=' + row['line_id'];

						if(line == null && time == null) {
							content += '<tr><td></td><td>' + towards + '</td><td></td></tr>';
						}
						else {
							content += '<tr><td><a href="' + line_link + '">' + line + '</a></td><td>' + towards + '</td><td>' + time + '</td></tr>';
						}
					});

					content += '</table>';

					$('#rbl_' + rbl).html(content);
				}

				$('#last_update').html('Letzte Aktualisierung: ' + (new Date()).toString('dd.MM.yyyy HH:mm:ss'));
			});
		},
		complete: function(xhr, text) {
			update_countdown(30);
		}
	});
}

