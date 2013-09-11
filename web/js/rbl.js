$(document).ready(function() {
	update_rbls();
});

function update_rbls() {
	$.ajax({
		url: 'rbls.php?ids=' + rbls.join(','),
		dataType: 'json',
		success: function(data, text, xhr) {
			$.each(rbls, function(index, rbl) {

				if(rbl in data) {
					var content = '<table class="rbl_info">';

					$.each(data[rbl], function(index2, row) {
						var line = row['name'];
						var towards = row['towards'];

						if(row['departures']['departure'].length > 0) {
							$.each(row['departures']['departure'], function(index3, departure) {
								var time = departure['departureTime']['countdown'];
								if(time == undefined) {
									time = '&ndash;';
								}
								content += '<tr><td>' + line + '</td><td>' + towards + '</td><td>' + time + '</td></tr>';
							});
						}
						else {
							content += '<tr><td>' + line + '</td><td>' + towards + '</td><td>&ndash;</td></tr>';
						}
					});

					content += '</table>';

					$('.rbl_' + rbl).html(content);
				}
			});
		},
		complete: function(xhr, text) {
			window.setTimeout('update_rbls();', 30000);
		}
	});
}

