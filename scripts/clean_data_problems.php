<?php

// TODO check: only run as standalone script from command line

$long_running_queries = true;

require_once(dirname(__FILE__) . '/../lib/common.php');

db_query('delete from data_problem where timestamp < now() - interval 30 day');

