$(document).ready(function() {
	update_rbls();
});

function update_countdown(seconds) {
	if(seconds == 0) {
		update_rbls();
	}
	else {
		// TODO show updating image
		$('#next_update').html('Nächste Aktualisierung in ' + seconds + ' Sekunden');
		window.setTimeout('update_countdown(' + (seconds-1) + ');', 1000);
	}

}

function update_rbls() {
	$('#next_update').html('Aktualisierung läuft...');

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

						content += '<tr><td>' + line + '</td><td>' + towards + '</td><td>' + time + '</td></tr>';
					});

					content += '</table>';

					$('#rbl_' + rbl).html(content);
				}

				// TODO update last_update
			});
		},
		complete: function(xhr, text) {
			update_countdown(30);
		}
	});
}

