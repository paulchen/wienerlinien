<!DOCTYPE html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="initial-scale=.4, user-scalable=yes" />
	<title>Wiener Linien -- Aktuelle Störungen</title>
	<link rel="stylesheet" type="text/css" href="css/main.css" />
</head>
<body class="centered_page">
	<div class="main_pane">
		<h1>Wiener Linien</h1>
		<div>Projekte auf Basis der von der Stadt Wien und den Wiener Linien zur Verfügung gestellten Daten.</div>
		<div>
			<ul>
				<li><a href="disruptions/">Aktuelle Störungen</a></li>
				<li><a href="disruptions/rss.php">Aktuelle Störungen -- RSS-Feed</a></li>
				<?php if(isset($twitter_usernames) && count($twitter_usernames) != ''): ?>
					<li>Störungen auf Twitter: 
					<?php foreach($twitter_usernames as $twitter_username): ?>
						<a href="https://twitter.com/<?php echo rawurlencode($twitter_username) ?>">@<?php echo htmlentities($twitter_username, ENT_QUOTES, 'UTF-8') ?></a>
					<?php endforeach; ?>
					</li>
				<?php endif; ?>
				<li><a href="disruptions/?archive=1">Störungsarchiv</a></li>
				<li><a href="map">Karte</a></li>
				<li><a href="departure-monitor">Abfahrsmonitor</a></li>
			</ul>
		</div>
		<hr />
		<?php require('footer.php'); ?>
	</div>
</body>
</html>

