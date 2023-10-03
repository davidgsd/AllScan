#!/usr/bin/php
<?php
if(isset($_SERVER['REMOTE_ADDR']) || isset($_SERVER['HTTP_HOST'])) {
	echo "This script must be run via CLI.<br>\n";
	exit(0);
}
$webRoot = '/var/www/html/allscan/';
// Copy files to webroot, with exception of the following dirs/files:
$excludes = array('.git', '.gitignore', 'README*', '_tools');

// Note: pwd starts in dir where script is located, not where called from
// Do below if might be called from somewhere else
$pwd = getcwd();
chdir($pwd);
echo "pwd = $pwd, webRoot = $webRoot\n";
if($webRoot === $pwd) {
	echo "Can't run script from web root folder. Go to dev folder.\n";
	exit(1);
}
copyFiles($excludes, false);

//--- functions ---
function copyFiles($excludes, $test=false, $toWww=true) {
	global $webRoot;
	$exclude = '';
	if(!empty($excludes)) {
		foreach($excludes as $pat)
			$exclude .= "--exclude='$pat' ";
	}
	$opts = $test ? '--list-only' : '';
	$dirs = $toWww ? '. ' . $webRoot : $webRoot . ' .';
	$cmd = "rsync -avvC $exclude $opts $dirs";
	passthru($cmd);
	echo "Done\n";
}
