<?php
require_once('../include/apiInit.php');
require_once('AMI.php');

if(!writeOk())
	die("Insufficient user permission to execute commands\n");

// Filter and validate input data
$button = trim(strip_tags($_POST['button']));
$localnode = trim(strip_tags($_POST['localnode']));
$cmd = trim(strip_tags($_POST['cmd']));

if(!$localnode || !preg_match("/^\d+$/", $localnode))
	die("Invalid local node number\n");

// Load allmon.ini
$cfg = readAllmonCfg();
if($cfg === false)
	die("allmon.ini not found");

// Open socket to Asterisk Manager
$ami = new AMI();
$fp = $ami->connect($cfg[$localnode]['host']);
if($fp === false)
	die("Could not connect\n");

if($ami->login($fp, $cfg[$localnode]['user'], $cfg[$localnode]['passwd']) === false)
	die("Could not login\n");

switch($button) {
	case 'dtmf':
		if(!preg_match("/^[\d*#,]+$/", $cmd))
			die("Invalid command value\n");
		echo "Executing cmd \"$cmd\" on $localnode...";
		$ctxt = "rpt fun $localnode $cmd";
		break;
	case 'restart':
		echo "Restarting Asterisk...";
		$ctxt = "restart now";
		break;
	default:
		die("Invalid command\n");
}

$resp = $ami->command($fp, $ctxt);
fclose($fp);

echo $resp ? $resp : "OK\n";
