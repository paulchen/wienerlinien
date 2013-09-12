<!DOCTYPE html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
	<title>Wiener Linien -- Stationsdetails</title>
	<script type="text/javascript" src="../js/jquery.min.js"></script>
	<script type="text/javascript" src="../js/rbl.js"></script>
	<script type="text/javascript">
	<!--
	var rbls=<?php echo json_encode($rbls) ?>;
	// -->
	</script>
</head>
<body>
	<h1><?php echo htmlentities($station_name, ENT_QUOTES, 'UTF-8') ?></h1>
	<?php foreach($platforms as $platform): ?>
	<?php if(!isset($previous_lines) || $previous_lines != $platform['line_ids']): ?><h2><?php echo htmlentities(implode(', ', $platform['line_names']), ENT_QUOTES, 'UTF-8') ?></h2><?php endif; ?>
	<div>Bahnsteig <?php echo htmlentities($platform['platform'], ENT_QUOTES, 'UTF-8') ?>:</div>
	<div class="rbl_<?php echo $platform['rbl'] ?>"></div>
	<?php $previous_lines = $platform['line_ids']; endforeach; ?>
</body>
</html>

