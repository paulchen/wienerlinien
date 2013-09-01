<?php
function show_lines($lines) {
	foreach($lines as $line_type) {
		echo "<h2>" . htmlentities($line_type['name'], ENT_QUOTES, 'UTF-8') . "</h2>";
		echo "<a href='javascript:show_group(" . $line_type['id'] . ")'>Alle</a> / ";
		echo "<a href='javascript:hide_group(" . $line_type['id'] . ")'>Keine</a> / ";
		echo "<a href='javascript:invert_group(" . $line_type['id'] . ")'>Auswahl umkehren</a>";
		echo "<br />";

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
	<script type="text/javascript" src="//maps.googleapis.com/maps/api/js?key=AIzaSyAj4Id5jnWqbSeAm0YcSoep75ujK2h8T70&amp;sensor=false"></script>
	<script type="text/javascript" src="js/jquery.min.js"></script>
	<script type="text/javascript" src="js/main.js"></script>
	<script type="text/javascript">
	<!--

	var groups = <?php echo json_encode($groups); ?>;

	// -->
	</script>
</head>
<body onload="initialize();">
	<div id="options_pane" style="width: 20%; height: 100%; position: absolute; overflow: scroll;">
		<?php show_lines($lines); ?>
		<hr />
		Seiteninhaber: <a href="mailto:paulchen@rueckgr.at">Paul Staroch</a><br />
		Datenquelle: Stadt Wien - <a href="http://data.wien.gv.at/">data.wien.gv.at</a>
	</div>
	<div id="map_canvas" style="position: absolute; width:80%; left: 20%; height:100%"></div>
</body>
</html>

