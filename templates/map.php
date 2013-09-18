<?php
function show_lines($lines) {
	foreach($lines as $line_type) {
		echo "<h2>" . htmlentities($line_type['name'], ENT_QUOTES, 'UTF-8') . "</h2>";
		echo "<a href='javascript:show_group(" . $line_type['id'] . ")'>Alle</a> / ";
		echo "<a href='javascript:hide_group(" . $line_type['id'] . ")'>Keine</a> / ";
		echo "<a href='javascript:invert_group(" . $line_type['id'] . ")'>Auswahl umkehren</a>";
		echo "<br /><br />";

		foreach($line_type['lines'] as $line) {
			echo "<input type='checkbox' name='checkbox_line_{$line['id']}' id='checkbox_line_{$line['id']}' onclick='toggle({$line['id']});'>&nbsp;<label for='checkbox_line_{$line['id']}'>{$line['name']}</label><br />";
		}
	}
}
?>
<!DOCTYPE html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<title>Wiener Linien</title>
	<link rel="stylesheet" type="text/css" href="../css/main.css" />
	<script type="text/javascript" src="//maps.googleapis.com/maps/api/js?key=AIzaSyAj4Id5jnWqbSeAm0YcSoep75ujK2h8T70&amp;sensor=false"></script>
	<script type="text/javascript" src="../js/jquery.min.js"></script>
	<script type="text/javascript" src="../js/jquery.fancybox.js"></script>
	<script type="text/javascript" src="../js/purl.js"></script>
	<link rel="stylesheet" type="text/css" href="../css/jquery.fancybox.css" media="screen" />

	<script type="text/javascript" src="../js/map.js"></script>
	<script type="text/javascript">
	<!--

	var groups = <?php echo json_encode($groups); ?>;

<?php if(isset($_REQUEST['lines'])): ?>
$(document).ready(function() {
		<?php if(count(explode(',', $_REQUEST['lines'])) == 1): ?>
			var preselected_lines = new Array();
			preselected_lines.push(<?php echo $_REQUEST['lines'] ?>);
		<?php else: ?>
			var preselected_lines = new Array(<?php echo $_REQUEST['lines'] ?>);
		<?php endif; ?>
		$.each(preselected_lines, function(index, value) {
			$('#checkbox_line_' + value).prop('checked', true);
		});
		show(preselected_lines);
	});
<?php endif; ?>
	// -->
	</script>
</head>
<body onload="initialize();" class="map">
	<div style="width: 20%; height: 100%; position: absolute; overflow-y: scroll; overflow-x: hidden;">
		<div id="options_pane">
			<h1>Kartenansicht</h1>
			<a href="..">Übersicht</a>
			<br /><br />
			<b>Aktuelle Ansicht</b> (<a href="#" id="current_permalink">Permalink</a>):
			<ul>
				<li>Koordinaten: <span id="current_latlon"></span></li>
				<li>Zoomstufe: <span id="current_zoom"></span></li>
				<li>Linie(n): <span id="current_lines"></li>
			</ul>
			<?php show_lines($lines); ?>
			<hr />
			<?php require('footer.php'); ?>
		</div>
	</div>
	<div id="map_canvas" style="position: absolute; width:80%; left: 20%; height:100%"></div>
</body>
</html>

