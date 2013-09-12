<!DOCTYPE html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
	<title>Wiener Linien -- Liniendetails</title>
	<link rel="stylesheet" type="text/css" href="../css/map.css" />
	<script type="text/javascript" src="../js/jquery.min.js"></script>
	<script type="text/javascript" src="../js/date.js"></script>
	<script type="text/javascript" src="../js/rbl.js"></script>
	<script type="text/javascript">
	<!--
	var rbls=<?php echo json_encode($rbls) ?>;
	// -->
	</script>
</head>
<body>
	<h1>Linie <?php echo htmlentities($line_name, ENT_QUOTES, 'UTF-8') ?> &ndash; Details</h1>
	<?php foreach($routes as $index => $route): ?>
		<?php if(count($routes) > 1): ?>
			<div>Richtung <?php echo $index+1 ?>:</div>
		<?php endif; ?>
		<div class="route_details">
			<?php foreach($route as $station): ?>
				<?php if($station['first']): ?>
					<div class="route route_first"></div>
				<?php elseif($station['last']): ?>
					<div class="route route_last"></div>
				<?php else: ?>
					<div class="route route_station"></div>
				<?php endif; ?>
				<?php echo $station['name'] ?><br />
				<?php if(!$station['last']): ?>
					<div class="route"></div><br />
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
</body>
</html>


