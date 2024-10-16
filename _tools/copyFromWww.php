#!/usr/bin/php
<?php
if(isset($_SERVER['REMOTE_ADDR']) || isset($_SERVER['HTTP_HOST'])) {
	echo "This script must be run via CLI.<br>\n";
	exit(0);
}
$webRoot = '/var/www/html/allscan/';
// Look in web folder and copy all files to local dir, with exception of the following dirs:
$excludes = ['test', 'old', '*.tmp', '*.bak', 'log.txt'];
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
	echo "Can't run script from web root folder. Go to dev folder.\n";
	exit(1);
}

copyFiles($excludes, false, false);

//--- functions ---
function copyFiles($excludes, $test=false, $toWww=true) {
	global $webRoot;
	echo "Copying development files ". ($toWww ? 'TO' : 'FROM') . " www dir...\n\n";
	$exclude = '';
	if(!empty($excludes)) {
		foreach($excludes as $dir)
			$exclude .= "--exclude='$dir' ";
	}
	$opts = $test ? '--list-only' : '';
	$dirs = $toWww ? '. ' . $webRoot : $webRoot . ' .';
	$cmd = "rsync -avC $exclude $opts $dirs";
	passthru($cmd);
	echo "\nDone\n";
}
