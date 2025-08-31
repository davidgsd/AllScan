<?php
require_once('../include/apiInit.php');
require_once('AMI.php');

if(!modifyOk())
	exit("Insufficient user permission to execute commands\n");

// Filter and validate user input
$fields = ['remotenode', 'button', 'localnode', 'perm', 'autodisc'];
foreach($fields as $f)
	$$f = isset($_POST[$f]) ? trim(strip_tags($_POST[$f])) : '';
$perm = ($perm === 'true');
$autodisc = ($autodisc === 'true');

if(!preg_match("/^\d+$/", $localnode) || !$localnode)
	exit("Invalid local node number\n");

if(!preg_match("/^\d+$/", $remotenode) || (!$remotenode && $button !== 'disconnect'))
	exit("Invalid remote node number\n");

chdir('..');

$msg = [];
if(!getAmiCfg($msg))
	exit('AMI credentials not found');

if($localnode != $amicfg->node)
	exit("Node $localnode not in AMI Cfgs");

// Open socket to Asterisk Manager
$ami = new AMI();
$fp = $ami->connect($amicfg->host, $amicfg->port);
if($fp === false)
	exit("Could not connect\n");

if($ami->login($fp, $amicfg->user, $amicfg->pass) === false)
	exit("Could not login\n");

switch($button) {
	case 'connect':
		if($autodisc) {
			echo "Disconnect all nodes from $localnode...";
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
		if($remotenode === '0') {
			$ilink = 6;
			echo "Disconnect all nodes from $localnode...";
		} else {
			$ilink = 11;
			echo "Disconnect $remotenode from $localnode...";
		}
		break;
}
$resp = $ami->command($fp, "rpt cmd $localnode ilink $ilink $remotenode");
fclose($fp);
echo $resp;
