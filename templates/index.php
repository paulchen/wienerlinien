<?php
function show_lines($type) {
	global $lines;

	echo "<h2>" . htmlentities($lines[$type]['name'], ENT_QUOTES, 'UTF-8') . "</h2>";

	foreach($lines[$type]['lines'] as $line) {
		echo "<input type='checkbox' name='checkbox_line_{$line['id']}' id='checkbox_line_{$line['id']}' onclick='toggle({$line['id']});'>&nbsp;<label for='checkbox_line_{$line['id']}'>{$line['name']}</label><br />";
	}
}
?>
<!DOCTYPE html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
	<title>Wiener Linien</title>
	<link rel="stylesheet" type="text/css" href="css/main.css" />
	<script type="text/javascript" src="http://maps.googleapis.com/maps/api/js?key=AIzaSyAj4Id5jnWqbSeAm0YcSoep75ujK2h8T70&sensor=false"></script>
	<script type="text/javascript" src="js/main.js"></script>
	<script type="text/javascript" src="js/jquery.min.js"></script>
	<script type="text/javascript">
	<!--
	var line_data = new Array();

	function show(id) {
		$.each(line_data[id], function(index, value) {
			var lat1 = value[0][0];
			var lon1 = value[0][1];
			var lat2 = value[1][0];
			var lon2 = value[1][1];

			var coordinates = [
				new google.maps.LatLng(lat1, lon1),
				new google.maps.LatLng(lat2, lon2)
			];
			var segment = new google.maps.Polyline({
				path: coordinates,
				strokeColor: '#FF0000',
				strokeOpacity: 1.0,
				strokeWeight: 2
			});

			segment.setMap(googleMap);
		});
	}

	function toggle(id) {
		if(!(id in line_data)) {
			$.ajax({
				url: 'json.php?line='+id,
				dataType: 'json',
				success: function(data, text, xhr) {
					line_data[id] = data;
					show(id);
				}
			});
		}
	}
	// -->
	</script>
</head>
<body onload="initialize();">
	<div id="options_pane" style="width: 20%; height: 100%; position: absolute; overflow: scroll;">
		<?php show_lines(4); ?>
		<?php show_lines(1); ?>
		<?php show_lines(2); ?>
	</div>
	<div id="map_canvas" style="position: absolute; width:80%; left: 20%; height:100%"></div>
</body>
</html>

