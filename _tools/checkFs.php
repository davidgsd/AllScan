#!/usr/bin/php
<?php
// Check file system for free space, excessively large log files
$cwd = getcwd();
if($cwd !== __DIR__)
	chdir(__DIR__);
require_once('../include/common.php');
$msg = [];
// Get args. Drop first element off of argv and argc (command text)
$argc--;
array_shift($argv);
$showMsgs = true;
// Check if called from cron
if(isset($argv[0]) && $argv[0] === 'cron') {
	$showMsgs = false;
	$argc--;
	array_shift($argv);
}
// Below will attempt to delete any files > 50MB in size in /var/log/ or /var/log/asterisk/
checkDiskSpace($msg);
if($showMsgs)
	echo implode(NL, $msg) . NL;
if($cwd !== __DIR__)
	chdir($cwd);
exit();
