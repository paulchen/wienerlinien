<!DOCTYPE html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
	<title>Wiener Linien -- <?php if(isset($_REQUEST['id'])): ?>Störungsdetails<?php else: ?>Aktuelle Störungen<?php endif; ?></title>
	<link rel="alternate" type="application/rss+xml" title="Wiener Linien -- Aktuelle Störungen -- RSS-Feed"  href="rss.xml" />
</head>
<body>
	<h1><?php if(isset($_REQUEST['id'])): ?>Störungsdetails<?php else: ?>Aktuelle Störungen<?php endif; ?></h1>
	<?php foreach($disruptions as $disruption): ?>
		<h2><a href="?id=<?php echo $disruption['id'] ?>">[<?php echo htmlentities($disruption['category'], ENT_QUOTES, 'UTF-8') ?>] <?php echo htmlentities($disruption['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
		<?php if($disruption['deleted']): ?>
			<div style="color: red; font-weight: bold; margin-bottom: 1em;">Diese Störung ist bereits zu Ende.</div>
		<?php endif; ?>
		<?php require('disruption_description.php'); ?>
		<hr />
	<?php endforeach; ?>
	<?php if(isset($_REQUEST['id'])): ?>
		<a href="?">Alle Störungen</a><hr />
	<?php endif; ?>
	<?php require('footer.php'); ?>
</body>
</html>

