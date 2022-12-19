<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
require_once('../include/common.php');
require_once('AMI.php');
// TBI: Authenticate user

// Filter and validate user input
$remotenode = trim(strip_tags($_POST['remotenode']));
$button = trim(strip_tags($_POST['button']));
$localnode = trim(strip_tags($_POST['localnode']));
$perm = (trim(strip_tags($_POST['perm'])) === 'true');
$autodisc = (trim(strip_tags($_POST['autodisc'])) === 'true');

if(!preg_match("/^\d+$/", $localnode) || !$localnode)
    die("Invalid local node number\n");

if(!preg_match("/^\d+$/", $remotenode) || !$remotenode)
    die("Invalid remote node number\n");

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

switch($button) {
	case 'connect':
		if($autodisc) {
			echo "Disconnect all nodes...";
			$resp = $ami->command($fp, "rpt cmd $localnode ilink 6 0");
			echo $resp . BR;
			usleep(500000);
		}
		if($perm) {
			$ilink = 13;
			echo "Permanently Connect $localnode to $remotenode...";
		} else {
			$ilink = 3;
			echo "Connect $localnode to $remotenode...";
		}
		break;
	case 'monitor':
		if($perm) {
			$ilink = 12;
			echo "Permanently Monitor $remotenode from $localnode...";
		} else {
			$ilink = 2;
			echo "Monitor $remotenode from $localnode...";
		}
		break;
	case 'localmonitor':
		if($perm) {
			$ilink = 18;
			echo "Permanently Local Monitor $remotenode from $localnode...";
		} else {
			$ilink = 8;
			echo "Local Monitor $remotenode from $localnode...";
		}
		break;
	case 'disconnect':
		$ilink = 11;
		echo "Disconnect $remotenode from $localnode...";
		break;
}

$resp = $ami->command($fp, "rpt cmd $localnode ilink $ilink $remotenode");

echo $resp;
