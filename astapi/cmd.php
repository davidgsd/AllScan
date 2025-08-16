<?php
require_once('../include/apiInit.php');
require_once('AMI.php');

if(!writeOk())
	exit("Insufficient user permission to execute commands\n");

// Filter and validate input data
$fields = ['button', 'localnode', 'cmd'];
foreach($fields as $f)
	$$f = isset($_POST[$f]) ? trim(strip_tags($_POST[$f])) : '';

if(!$localnode || !preg_match("/^\d+$/", $localnode))
	exit("Invalid local node number\n");

chdir('..');

// Load allmon.ini
$cfg = readNodeCfg();
if($cfg === false)
	exit("allmon.ini not found\n");

$arr = parseAllmonCfg($cfg[$localnode]);
if($arr === null)
	exit("No valid AMI Cfgs\n");
list($host, $port) = $arr;

// Open socket to Asterisk Manager
$ami = new AMI();
$fp = $ami->connect($host, $port);
if($fp === false)
	exit("Could not connect\n");

$amiuser = $cfg[$localnode]['user'];
$pass = $cfg[$localnode]['passwd'];
if($ami->login($fp, $amiuser, $pass) === false)
	exit("Could not login\n");

switch($button) {
	case 'dtmf':
		if(!preg_match("/^[\d*#,A-Da-d]+$/", $cmd))
			exit("Invalid command value\n");
		echo "Executing cmd \"$cmd\" on $localnode...";
		$ctxt = "rpt fun $localnode $cmd";
		break;
	case 'restart':
		echo "Restarting Asterisk...";
		$ctxt = ($ami->aslver >= 3) ? "core restart now" : "restart now";
		break;
	default:
		exit("Invalid command\n");
}
$resp = $ami->command($fp, $ctxt);
fclose($fp);
echo $resp;
