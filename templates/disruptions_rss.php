<?php echo '<?xml version="1.0" encoding="UTF-8" ?>'; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<!-- TODO update time for whole feed, times for items -->
<channel>
  <title>Wiener Linien -- Aktuelle Störungen</title>
  <link><?php echo $link ?></link>
  <atom:link href="<?php echo "{$link}rss.php" ?>" rel="self" type="application/rss+xml" />
  <description>Wiener Linien -- Aktuelle Störungen</description>
  <?php foreach($disruptions as $disruption): ?>
    <item>
      <title><![CDATA[<?php if(count($disruption['lines']) > 0): echo implode('/', $disruption['lines']) . ': '; endif; echo htmlspecialchars('[' . $disruption['category'] . '] ' . str_replace("\n", " ", $disruption['title']), ENT_QUOTES, 'UTF-8'); ?>]]></title>
      <link><?php echo "$link?id={$disruption['id']}" ?></link>
      <guid><?php echo "$link?id={$disruption['id']}" ?></guid>
      <description><![CDATA[<?php require('disruption_description.php') ?>]]></description>
    </item>
  <?php endforeach; ?>
</channel>

</rss>
