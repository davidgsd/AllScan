<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
require_once('../include/common.php');
require_once('AMI.php');
// TBI: Authenticate user

// Filter and validate user input
$localnode = trim(strip_tags($_POST['localnode']));

// Read configuration file
$allmonini = '../' . API . 'allmon.ini';
if(!file_exists($allmonini))
    die(API . "allmon.ini not found\n");

$config = parse_ini_file($allmonini, true);

// Open socket to Asterisk Manager
$ami = new AMI();
$fp = $ami->connect($config[$localnode]['host']);
if($fp === false)
	die("Could not connect\n");

if($ami->login($fp, $config[$localnode]['user'], $config[$localnode]['passwd']) === false)
	die("Could not login\n");

echo "Restarting Asterisk...";

$resp = $ami->command($fp, "restart now");

echo $resp ? $resp : "OK\n";
