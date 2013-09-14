<!DOCTYPE html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
	<title>Wiener Linien -- <?php if(isset($_REQUEST['id'])): ?>Störungsdetails<?php else: ?>Störungen<?php endif; ?></title>
	<link rel="alternate" type="application/rss+xml" title="Wiener Linien -- <?php if(isset($_REQUEST['archive']) && $_REQUEST['archive'] == 1): ?>Alle<?php else: ?>Aktuelle<? endif; ?> Störungen -- RSS-Feed"  href="rss.xml" />
	<link rel="stylesheet" type="text/css" href="../css/main.css" />
	<style type="text/css">
	a.show_link, a.hide_link { display: none; }
	div.description { padding-top: 1em; }
	</style>
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
	// ->
	</script>
</head>
<body>
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
	</div>
	<?php foreach($disruptions as $disruption): ?>
		<h2><a href="?id=<?php echo $disruption['id'] ?>"><?php if(count($disruption['lines']) > 0): echo implode('/', $disruption['lines']) . ': '; endif; echo '[' . htmlentities($disruption['category'], ENT_QUOTES, 'UTF-8') ?>] <?php echo htmlentities($disruption['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
		<a href="javascript:show(<?php echo $disruption['id'] ?>);" class="show_link" id="show_link_<?php echo $disruption['id'] ?>">Anzeigen</a><a href="javascript:hide(<?php echo $disruption['id'] ?>);" class="hide_link" id="hide_link_<?php echo $disruption['id'] ?>">Verbergen</a>
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
</body>
</html>

