<!DOCTYPE html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
	<title>Wiener Linien -- Stationsdetails</title>
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
	<?php if(!isset($previous_line) || $previous_line != $platform['line_id']): ?><h2><?php echo htmlentities($platform['line_name'], ENT_QUOTES, 'UTF-8') ?></h2><?php endif; ?>
	<div id="rbl_<?php echo $platform['rbl'] ?>"></div>
	<?php $previous_line = $platform['line_id']; endforeach; ?>
</body>
</html>

