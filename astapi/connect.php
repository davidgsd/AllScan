<?php
//include('session.inc');
require_once('../include/common.php');
require_once('AMI.php');

// if($_SESSION['sm61loggedin'] !== true) {
	// die("Please login to use connect/disconnect functions.\n");
// }

// Filter and validate user input
$remotenode = trim(strip_tags($_POST['remotenode']));
$perm = trim(strip_tags($_POST['perm']));
$button = trim(strip_tags($_POST['button']));
$localnode = trim(strip_tags($_POST['localnode']));

if(!preg_match("/^\d+$/", $localnode))
    die("Invalid local node number\n");

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

switch($button) {
	case 'connect':
		if($perm == 'on') {
			$ilink = 13;
			echo "Permanently Connect $localnode to $remotenode...";
		} else {
			$ilink = 3;
			echo "Connect $localnode to $remotenode...";
		}
		break;
	case 'monitor':
		if($perm == 'on') {
			$ilink = 12;
			echo "Permanently Monitor $remotenode from $localnode...";
		} else {
			$ilink = 2;
			echo "Monitor $remotenode from $localnode...";
		}
		break;
	case 'localmonitor':
		if($perm == 'on') {
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
