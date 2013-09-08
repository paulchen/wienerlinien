<!DOCTYPE html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
	<title>Wiener Linien -- Aktuelle Störungen</title>
</head>
<body>
	<h1>Aktuelle Störungen</h1>
	<?php foreach($disruptions as $disruption): ?>
		<h2><a href="?id=<?php echo $disruption['id'] ?>">[<?php echo htmlentities($disruption['category'], ENT_QUOTES, 'UTF-8') ?>] <?php echo htmlentities($disruption['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
		<?php require('disruption_description.php'); ?>
		<hr />
	<?php endforeach; ?>
	<?php if(isset($_REQUEST['id'])): ?>
		<a href="?">Alle Störungen</a><hr />
	<?php endif; ?>
	<a href="mailto:paulchen@rueckgr.at">Paul Staroch</a><br />
	<!-- TODO datenquelle -->
</body>
</html>

