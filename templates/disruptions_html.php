<!DOCTYPE html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="initial-scale=.4, user-scalable=yes" />
	<title>Wiener Linien -- <?php if(isset($_REQUEST['id'])): ?>Störungsdetails<?php else: ?>Störungen<?php endif; ?></title>
	<link rel="alternate" type="application/rss+xml" title="Wiener Linien -- <?php if(isset($_REQUEST['archive']) && $_REQUEST['archive'] == 1): ?>Alle<?php else: ?>Aktuelle<? endif; ?> Störungen -- RSS-Feed"  href="rss.xml" />
	<?php if((isset($_REQUEST['archive']) && $_REQUEST['archive'] == 1) || !isset($_REQUEST['id'])): ?>
		<script type="text/javascript" src="../js/jquery.min.js"></script>
	<?php endif; ?>
	<?php if(isset($_REQUEST['archive']) && $_REQUEST['archive'] == 1): ?>
		<script type="text/javascript" src="../js/jquery-ui.js"></script>
		<script type="text/javascript" src="../js/jquery-ui-timepicker-addon.js"></script>
		<link rel="stylesheet" type="text/css" href="../css/jquery-ui.css" />
		<script type="text/javascript">
		<!--
		$(document).ready(function() {
			var options = { dateFormat: 'dd.mm.yy', timeFormat: 'HH:mm', currentText: 'Jetzt', closeText: 'Fertig' };
			$('#from').datetimepicker(options);
			$('#to').datetimepicker(options);
		});

		function show_filter() {
			$('#filter_link').hide();
			$('#filter_fieldset').show('fast');
		}
		// -->
		</script>
	<?php endif; ?>
	<?php if(!isset($_REQUEST['id'])): ?>
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
	<link rel="stylesheet" type="text/css" href="../css/main.css" />
</head>
<body class="centered_page">
	<div class="main_pane">
	<h1>
		<?php if(isset($_REQUEST['id'])): ?>
			Störungsdetails
		<?php else: ?>
			<?php if(isset($_REQUEST['archive']) && $_REQUEST['archive'] == 1): ?>
				<?php if(!$filtered_archive): ?>
					Alle
				<?php endif; ?>
			<?php else: ?>
				Aktuelle
			<? endif; ?>
			Störungen
		<?php endif; ?>
	</h1>
	<div>
		<?php if($filtered_archive): ?>
			<div style="padding-bottom: 1em;">
				<b>Aktueller Filter</b>:
				<ul>
					<?php foreach($filter_strings as $filter): ?>
						<li><?php echo $filter; ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<?php if(isset($_REQUEST['id']) || !isset($_REQUEST['archive']) || $_REQUEST['archive'] != 1 || $filtered_archive): ?>
			<a href="?archive=1">Alle Störungen</a>
		<?php endif; ?>
		<?php if(isset($_REQUEST['id']) || (isset($_REQUEST['archive']) && $_REQUEST['archive'] == 1)): ?>
			<a href="?">Aktuelle Störungen</a>
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
	<?php if(isset($_REQUEST['archive']) && $_REQUEST['archive'] == 1): ?>
		<div>
			<a href="javascript:show_filter();" id="filter_link">Filter</a>
			<fieldset id="filter_fieldset"><legend>Filter</legend>
				<form method="get" action=".">
					<input type="hidden" name="archive" value="1" />
					<div style="padding-bottom: 1em;">
						<b>Linien:</b><br />
						<!-- TODO all/none/invert links -->
						<?php foreach($lines as $line_type): ?>
							<i><?php echo $line_type['name']; ?>:</i><br />
							<?php foreach($line_type['lines'] as $line): ?>
								<span><input type="checkbox" name="lines[]" value="<?php echo $line['id'] ?>" id="line_<?php echo $line['id'] ?>" <?php if(in_array($line['id'], $selected_lines)): ?>checked="checked"<?php endif; ?>> <label for="line_<?php echo $line['id'] ?>"><?php echo htmlentities($line['name'], ENT_QUOTES, 'UTF-8') ?></label></span>
							<?php endforeach; ?>
							<br />
						<?php endforeach; ?>
					</div>
					<div style="padding-bottom: 1em;">
						<b>Zeitraum:</b><br />
						Von: <input type="text" name="from" id="from" value="<?php if(isset($_REQUEST['from'])) echo date('d.m.Y H:i', $_REQUEST['from']) ?>" /><br />
						Bis: <input type="text" name="to" id="to" value="<?php if(isset($_REQUEST['to'])) echo date('d.m.Y H:i', $_REQUEST['to']) ?>" /><br />
					</div>
					<div style="padding-bottom: 1em;">
						<b>Kategorien:</b><br />
						<?php foreach($categories as $category): ?>
						<input type="checkbox" name="types[]" value="<?php echo $category['id'] ?>" id="category_<?php echo $category['id'] ?>" <?php if(in_array($category['id'], $selected_types)): ?>checked="checked"<?php endif; ?> /> <label for="category_<?php echo $category['id'] ?>"><?php echo htmlentities($category['title'], ENT_QUOTES, 'UTF-8') ?></label>
						<?php endforeach; ?>
					</div>
					<input type="submit" value="Filter anwenden" />
				</form>
			</fieldset>
			<hr />
		</div>
	<?php endif; ?>
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
	<?php if(count($disruptions) == 0): ?>
		<div>
			<?php if(isset($_REQUEST['archive']) && $_REQUEST['archive'] == 1): ?>
				Es wurde keine Störung gefunden, die den gewählten Kriterien entspricht.
			<?php else: ?>
				Derzeit gibt es keine Störungen im Netz der Wiener Linien. Gute Fahrt!
			<?php endif; ?>
			<hr />
		</div>
	<?php endif; ?>
	<?php if(isset($_REQUEST['id'])): ?>
		<a href="?">Alle aktuellen Störungen</a><hr />
	<?php endif; ?>
	<?php require('footer.php'); ?>
	</div>
</body>
</html>

