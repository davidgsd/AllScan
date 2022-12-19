<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
require_once('../include/common.php');
require_once('AMI.php');
// TBI: Authenticate user

// Filter and validate user input
$localnode = trim(strip_tags($_POST['localnode']));

// Load allmon.ini
$cfg = readAllmonCfg();
if($cfg === false) {
	die("allmon.ini not found.");
	exit();
}

// Open socket to Asterisk Manager
$ami = new AMI();
$fp = $ami->connect($cfg[$localnode]['host']);
if($fp === false)
	die("Could not connect\n");

if($ami->login($fp, $cfg[$localnode]['user'], $cfg[$localnode]['passwd']) === false)
	die("Could not login\n");

echo "Restarting Asterisk...";

$resp = $ami->command($fp, "restart now");

echo $resp ? $resp : "OK\n";
