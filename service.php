<?php
set_time_limit(0);
header("Content-Type: text/html; charset=UTF-8"); 
include_once('lib/WS.php');
$ws = new WS('192.168.119.86', 4000);