<!DOCTYPE html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
	<title>Wiener Linien -- Aktuelle Störungen</title>
</head>
<body>
	<h1>Aktuelle Störungen</h1>
	<?php foreach($disruptions as $disruption): ?>
		<h2><?php echo htmlentities($disruption['title'], ENT_QUOTES, 'UTF-8') ?></h2>
		<?php echo htmlentities($disruption['description'], ENT_QUOTES, 'UTF-8') ?><br /><br />
		<?php if(count($disruption['lines']) > 0): ?>
			<?php if(count($disruption['lines']) > 1): ?>
				<b>Betroffene Linien</b>:<br />
			<?php else: ?>
				<b>Betroffene Linie</b>:<br />
			<?php endif; ?>
			<ul>
				<?php foreach($disruption['lines'] as $line): ?>
					<li><?php echo $line ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if(count($disruption['stations']) > 0): ?>
			<?php if(count($disruption['stations']) > 1): ?>
				<b>Betroffene Stationen</b>:<br />
			<?php else: ?>
				<b>Betroffene Station</b>:<br />
			<?php endif; ?>
			<ul>
				<?php foreach($disruption['stations'] as $station): ?>
					<li><?php echo $station ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<hr />
	<?php endforeach; ?>
	<a href="mailto:paulchen@rueckgr.at">Paul Staroch</a><br />
	<!-- TODO datenquelle -->
</body>
</html>

