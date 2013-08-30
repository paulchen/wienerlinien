<?php
function show_lines($lines) {
	foreach($lines as $line_type) {
		echo "<h2>" . htmlentities($line_type['name'], ENT_QUOTES, 'UTF-8') . "</h2>";

		foreach($line_type['lines'] as $line) {
			echo "<input type='checkbox' name='checkbox_line_{$line['id']}' id='checkbox_line_{$line['id']}' onclick='toggle({$line['id']});'>&nbsp;<label for='checkbox_line_{$line['id']}'>{$line['name']}</label><br />";
		}
	}
}
?>
<!DOCTYPE html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
	<title>Wiener Linien</title>
	<link rel="stylesheet" type="text/css" href="css/main.css" />
	<script type="text/javascript" src="//maps.googleapis.com/maps/api/js?key=AIzaSyAj4Id5jnWqbSeAm0YcSoep75ujK2h8T70&sensor=false"></script>
	<script type="text/javascript" src="js/jquery.min.js"></script>
	<script type="text/javascript" src="js/main.js"></script>
</head>
<body onload="initialize();">
	<div id="options_pane" style="width: 20%; height: 100%; position: absolute; overflow: scroll;">
		<?php show_lines($lines); ?>
	</div>
	<div id="map_canvas" style="position: absolute; width:80%; left: 20%; height:100%"></div>
</body>
</html>

