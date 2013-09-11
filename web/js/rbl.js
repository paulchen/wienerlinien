$(document).ready(function() {
	update_rbls();
});

function update_rbls() {
	$.ajax({
		url: 'rbls.php?ids=' + rbls.join(','),
		dataType: 'json',
		success: function(data, text, xhr) {
			// TODO
		},
		complete: function(xhr, text) {
			window.setTimeout('update_rbls();', 30000);
		}
	});
}

