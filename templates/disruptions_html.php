<!DOCTYPE html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
	<title>Wiener Linien -- <?php if(isset($_REQUEST['id'])): ?>Störungsdetails<?php else: ?>Störungen<?php endif; ?></title>
	<link rel="alternate" type="application/rss+xml" title="Wiener Linien -- <?php if(isset($_REQUEST['archive']) && $_REQUEST['archive'] == 1): ?>Alle<?php else: ?>Aktuelle<? endif; ?> Störungen -- RSS-Feed"  href="rss.xml" />
	<link rel="stylesheet" type="text/css" href="../css/main.css" />
	<?php if(!isset($_REQUEST['id'])): ?>
		<script type="text/javascript" src="../js/jquery.min.js"></script>
		<script type="text/javascript">
		<!--
		$(document).ready(function() {
			$('.description').hide();
			$('.show_link').show();
		});

		function show(id) {
			$('#show_link_' + id).hide();
			$('#hide_link_' + id).show();
			$('#description_' + id).show('fast');
		}

		function hide(id) {
			$('#show_link_' + id).show();
			$('#hide_link_' + id).hide();
			$('#description_' + id).hide('fast');
		}
		// -->
		</script>
	<?php endif; ?>
</head>
<body class="centered_page">
	<div class="main_pane">
	<h1>
		<?php if(isset($_REQUEST['id'])): ?>
			Störungsdetails
		<?php else: ?>
			<?php if(isset($_REQUEST['archive']) && $_REQUEST['archive'] == 1): ?>
				Alle
			<?php else: ?>
				Aktuelle
			<? endif; ?>
			Störungen
		<?php endif; ?>
	</h1>
	<div>
		<?php if(isset($_REQUEST['id']) || (isset($_REQUEST['archive']) && $_REQUEST['archive'] == 1)): ?>
			<a href="?">Aktuelle Störungen</a>
		<?php endif; ?>
		<?php if(isset($_REQUEST['id']) || !isset($_REQUEST['archive']) || $_REQUEST['archive'] != 1): ?>
			<a href="?archive=1">Alle Störungen</a>
		<?php endif; ?>
		<a href="..">Übersicht</a>
		<?php if(isset($pagination_data)): ?>
			<br /><br />
			Aktuelle Seite: <?php echo $page ?>.
			<?php foreach($pagination_data as $item): ?>
				<a href="<?php echo $item['url'] ?>"><?php echo htmlentities($item['name'], ENT_QUOTES, 'UTF-8') ?></a>
			<?php endforeach; ?>
		<?php endif; ?>
		<hr />
	</div>
	<?php foreach($disruptions as $disruption): ?>
		<h2><a href="?id=<?php echo $disruption['id'] ?>"><?php if(count($disruption['lines']) > 0): echo implode('/', $disruption['lines']) . ': '; endif; echo '[' . htmlentities($disruption['category'], ENT_QUOTES, 'UTF-8') ?>] <?php echo htmlentities($disruption['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
		<?php if(!isset($_REQUEST['id'])): ?>
			<a href="javascript:show(<?php echo $disruption['id'] ?>);" class="show_link" id="show_link_<?php echo $disruption['id'] ?>">Anzeigen</a>
			<a href="javascript:hide(<?php echo $disruption['id'] ?>);" class="hide_link" id="hide_link_<?php echo $disruption['id'] ?>">Verbergen</a>
		<?php endif; ?>
		<div class="description" id="description_<?php echo $disruption['id'] ?>">
			<?php if($disruption['deleted']): ?>
				<div style="color: red; font-weight: bold; margin-bottom: 1em;">Diese Störung ist bereits zu Ende.</div>
			<?php endif; ?>
			<?php require('disruption_description.php'); ?>
		</div>
		<hr />
	<?php endforeach; ?>
	<?php if(isset($_REQUEST['id'])): ?>
		<a href="?">Alle aktuellen Störungen</a><hr />
	<?php endif; ?>
	<?php require('footer.php'); ?>
	</div>
</body>
</html>

