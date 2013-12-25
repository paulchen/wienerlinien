<?php
chdir(dirname(__FILE__));
require_once('lib/common.php');
db_query('SELECT * FROM line WHERE id IN (?, ?, ?)', array(0 => 2, 1 => 3, 120 => 4));

