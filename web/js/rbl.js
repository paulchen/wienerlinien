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
						var line = row['line'];
						var towards = row['towards'];
						var time = row['time'];

						content += '<tr><td>' + line + '</td><td>' + towards + '</td><td>' + time + '</td></tr>';
					});

					content += '</table>';

					$('#rbl_' + rbl).html(content);
				}
			});
		},
		complete: function(xhr, text) {
			window.setTimeout('update_rbls();', 30000);
		}
	});
}

