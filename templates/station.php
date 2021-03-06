<!DOCTYPE html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<title>Wiener Linien -- Stationsdetails</title>
	<link rel="stylesheet" type="text/css" href="../css/main.css" />
	<script type="text/javascript" src="../js/jquery.min.js"></script>
	<script type="text/javascript" src="../js/date.js"></script>
	<script type="text/javascript" src="../js/rbl.js"></script>
	<script type="text/javascript">
	<!--
	var rbls=<?php echo json_encode($rbls) ?>;
	// -->
	</script>
</head>
<body class="station">
	<h1><?php echo htmlentities($station_name, ENT_QUOTES, 'UTF-8') ?> &ndash; Nächste Abfahrten</h1>
	<div id="last_update"></div>
	<div id="next_update">
		<span></span>
		<img src="../css/ajax-loader.gif" alt="" />
		<a href="javascript:force_refresh();"><img src="../css/refresh.png" alt="Aktualisieren" /></a>
	</div>
	<?php foreach($platforms as $platform): ?>
	<?php if(!isset($previous_lines) || $previous_lines != $platform['line_ids']): ?><h2><?php echo htmlentities(implode(', ', $platform['line_names']), ENT_QUOTES, 'UTF-8') ?></h2><?php endif; ?>
	<div>Bahnsteig <?php echo htmlentities($platform['platform'], ENT_QUOTES, 'UTF-8') ?>:</div>
	<div class="rbl" id="rbl_<?php echo $platform['rbl'] ?>">Derzeit sind für diesen Bahnsteig keine Abfahrtsinformationen verfügbar.</div>
	<?php $previous_lines = $platform['line_ids']; endforeach; ?>
</body>
</html>

