#!/usr/bin/php
<?php
if(isset($_SERVER['REMOTE_ADDR']) || isset($_SERVER['HTTP_HOST'])) {
	echo "This script must be run via CLI.<br>\n";
	exit(0);
}
$webRoot = '/var/www/html/allscan/';
// Note: pwd starts in dir where script is located, not where called from
// Do below if might be called from somewhere else
$pwd = getcwd();
chdir($pwd);
echo "pwd = $pwd, webRoot = $webRoot\n";
if(!is_dir($webRoot)) {
	echo "webRoot dir not found\n";
	exit(1);
}
if($webRoot === $pwd) {
	echo "Can't run script from web root. Go to dev folder.\n";
	exit(1);
}

diffFiles();

//--- functions ---
function diffFiles() {
	global $webRoot;
	echo "Diff'ing development files vs. www dir...\n\n";
	$exclude = '-X _tools/excludeFiles.txt';
	$dirs = '. ' . $webRoot;
	$cmd = "diff -r $exclude $dirs";
	passthru($cmd);
	echo "\nDone\n";
}
