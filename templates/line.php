<!DOCTYPE html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<title>Wiener Linien -- Liniendetails</title>
	<link rel="stylesheet" type="text/css" href="../css/main.css" />
</head>
<body class="line">
	<h1>Linie <?php echo htmlentities($line_name, ENT_QUOTES, 'UTF-8') ?> &ndash; Details</h1>
	<?php if(count($stations) == 0): ?>
		Zu dieser Linie gibt es keine Detailinformationen.
	<?php endif; ?>
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
				<a href="station.htm?id=<?php echo $station['id'] ?>"><?php echo htmlentities($station['name'], ENT_QUOTES, 'UTF-8') ?></a><br />
				<?php if(!$station['last']): ?>
					<div class="route"></div><br />
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
</body>
</html>


