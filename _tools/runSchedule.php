#!/usr/bin/php
<?php
// Run scheduled node connects/disconnects defined on the AllScan main page ("Schedule" button).
// Call once per minute from cron. Enable with the Scheduled Connects cfg on the Cfgs page (admin), or
// manually: * * * * * /var/www/html/allscan/_tools/runSchedule.php >> /etc/allscan/schedule.log 2>&1
// Options: --dry-run  show what would run now without sending any commands
$dryRun = in_array('--dry-run', $argv);
// asInit() expects web server vars. Emulate them for CLI use
$asRoot = realpath(__DIR__ . '/..');
$_SERVER['DOCUMENT_ROOT'] = dirname($asRoot);
$_SERVER['SCRIPT_NAME'] = '/' . basename($asRoot) . '/_tools/' . basename(__FILE__);
chdir(__DIR__);
require_once('../include/common.php');
require_once('../include/scheduleUtils.php');
require_once('../astapi/AMI.php');

$msg = [];
asInit($msg);
$asdir = basename($asRoot);
$db = dbInit();
checkTables($db, $msg);
$cfgModel = new CfgModel($db);

schedSetTimezone();
$now = time();
// Heartbeat is written even when disabled so that enabling the cfg can detect that the runner is
// already being called from another crontab
if(!$dryRun)
	schedWrite(SCHED_HEARTBEAT, date('c'));

// Do nothing else if the Scheduled Connects cfg is off
if(!schedEnabled())
	exit(0);

$due = [];
foreach(schedLoad() as $e) {
	if(schedDue($e, $now))
		$due[] = $e;
}
if(!count($due))
	exit(0);

$stamp = date('Y-m-d H:i:s');
if($dryRun) {
	foreach($due as $e)
		echo "$stamp DRY RUN: " . schedDescribe($e) . "\n";
	exit(0);
}

function fail($stamp, $s) {
	schedActivity("ERROR: $s");
	exit("$stamp $s\n");
}
if(!getAmiCfg($msg) || !$amicfg->host)
	fail($stamp, 'AMI credentials not found');
$ami = new AMI();
$fp = $ami->connect($amicfg->host, $amicfg->port);
if($fp === false)
	fail($stamp, 'Could not connect to Asterisk Manager');
if($ami->login($fp, $amicfg->user, $amicfg->pass) === false)
	fail($stamp, 'Could not login to Asterisk Manager');
foreach($due as $e) {
	$res = schedDescribe($e) . ': ' . schedExecute($ami, $fp, $amicfg->node, $e);
	echo "$stamp $res\n";
	schedActivity($res);
}
fclose($fp);
