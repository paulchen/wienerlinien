<?php if($disruption['timestamp_deleted'] && mb_strpos($disruption['description'], 'ngliche Meldung', 0, 'UTF-8') === false): ?>
<b>Urspr&uuml;ngliche Meldung</b> (<?php echo date('H:i', $disruption['start_time']) ?>): 
<?php endif; ?>
<?php echo $disruption['description'] ?>
<?php if($disruption['timestamp_deleted']): ?>
<br /><br />
<b>Update</b> (<?php echo date('H:i', $disruption['timestamp_deleted']) ?>): Die Störung ist zu Ende.
<?php endif; ?>
<br /><br />
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
<?php if($disruption['start_time']): ?>
	<b>Von</b>: <?php echo date('d.m.Y H:i', $disruption['start_time']) ?><br />
<?php endif; ?>
<?php if($disruption['end_time']): ?>
	<b>Bis</b>: <?php echo date('d.m.Y H:i', $disruption['end_time']) ?><br />
<?php endif; ?>
<?php if($disruption['resume_time']): ?>
	<b>Verkehrsaufnahme</b>: <?php echo date('d.m.Y H:i', $disruption['resume_time']) ?><br />
<?php endif; ?>

