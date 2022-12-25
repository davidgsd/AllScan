#!/usr/bin/php
<?php
// AllScan Install/Update script v1.0
//
// Execute this script by running "sudo ./AllScanInstallUpdate.php" from any directory. The script will then determine
// the location of the web root folder on your system, cd to that folder, check if you have AllScan installed and install
// it if not, or if already installed will check the version and update the install if a newer version is available.
//
// NOTE: Updating can result in modified files being overwritten. This script will make a backup copy of the allscan
// folder to ./allscan-old/ You may then need to copy any cfgs you added/modified back into the allscan folder.
//
// This should be run from CLI only (SSH), not over the web
if(isset($_SERVER['REMOTE_ADDR']) || isset($_SERVER['HTTP_HOST'])) {
	echo "This script must be run from the Command Line Interface only.<br>\n";
	exit(0);
}

// Determine web server folder
$dirs = ['/var/www/html', '/srv/http'];
foreach($dirs as $d) {
	if(is_dir($d)) {
		$webdir = $d;
		break;
	}
}
msg("Web Server Folder: " . (isset($webdir) ? $webdir : "Not Found."));

// Determine web server group name
$name = ['www-data', 'http'];
foreach($name as $n) {
	if(`grep "^$n:" /etc/group` != '') {
		$group = $n;
		break;
	}
}
msg("Web Group Name: " . (isset($group) ? $group : "Not Found."));

if(!isset($webdir) || !isset($group))
	exit();

// cd to web root folder
$cwd = getcwd();
if($cwd !== $webdir) {
	msg("Changing dir from $cwd to $webdir");
	chdir($webdir);
	$cwd = getcwd();
	if($cwd !== $webdir)
		errExit("cd failed.");
}

// Destination dir in web root folder:
$asdir = 'allscan';

// Check if dir exists. If so, see if an update is needed. If not, install AllScan
if(is_dir($asdir)) {
	msg("$asdir dir exists. Checking if update needed...");
	$fname = 'common.php';
	$s = `grep '^\$AllScanVersion' $asdir/include/$fname`;
	if($s != '') {
		if(preg_match('/"v([0-9\.]{3,4})"/', $s, $m) == 1)
			$ver = $m[1];
	}
	if(empty($ver))
		$ver = 'Unknown';
	msg("Allscan current version: $ver");
	msg("Checking github version...");
	if(file_exists($fname)) {
		unlink($fname);
		if(file_exists($fname))
			errExit("$fname already exists in cwd, delete failed.");
	}
	$url = 'https://raw.githubusercontent.com/davidgsd/AllScan/main/include/' . $fname;
	`wget "$url"`;
	if(!file_exists($fname))
		errExit("wget $fname from github failed.");

	$s = `grep '^\$AllScanVersion' $fname`;
	unlink($fname);
	if($s != '') {
		if(preg_match('/"v([0-9\.]{3,4})"/', $s, $m) == 1)
			$gver = $m[1];
	}
	if(empty($gver))
		$gver = 'Unknown';
	msg("Allscan github version: $gver");
	if($gver <= $ver) {
		msg("AllScan is up-to-date.");
		exit();
	}
	msg("AllScan is out-of-date.");
	$s = readline("Ready to Update AllScan. Enter 'y' to confirm, any other key to exit: ");
	if($s !== 'y')
		exit();

	$bak = $asdir . '-old';
	msg("Backing up existing folder to $bak/...");
	`rm -rf $bak`;
	`cp -a allscan $bak`;
	if(!is_dir($bak))
		errExit("Backup failed.");

	msg("Verifying $asdir dir has 0775 permissions and $group group");
	if(!chmod($asdir, 0775))
		errExit('chmod failed');

} else {
	msg("$asdir dir not found.");
	$s = readline("Ready to Install AllScan. Enter 'y' to confirm, any other key to exit: ");
	if($s !== 'y')
		exit();

	msg("Creating $asdir dir with 0775 permissions and $group group");
	if(!mkdir($asdir, 0775))
		errExit('mkdir failed');
}

if(!chgrp($asdir, $group))
	errExit('chgrp failed');

$fname = 'main.zip';
$url = 'https://github.com/davidgsd/AllScan/archive/refs/heads/' . $fname;
`wget $url`;
if(!file_exists($fname))
	errExit("Retrieve $fname from github failed.");

`unzip main.zip; rm main.zip`;
$s = 'AllScan-main/*';
`cp -rf $s $asdir/; rm -rf AllScan-main`;

msg("Install/Update Complete.");

// Show URLs where AllScan can be accessed and other notes
$ip = exec("wget -t 1 -T 3 -q -O- http://checkip.dyndns.org:8245 | cut -d':' -f2 | cut -d' ' -f2 | cut -d'<' -f1");
$lanip = exec("ifconfig | grep inet | head -1 | awk '{print $2}'");
if($lanip === '127.0.0.1')
	$lanip = exec("ifconfig | grep inet | tail -1 | awk '{print $2}'");

msg("AllScan can be accessed at:\n\thttp://$lanip/$asdir/ on the local network, or\n"
	."\thttp://$ip/$asdir/ remotely if your router has a port forwarded to this node.");

msg("Be sure to bookmark the above URL(s) in your browser, and if you have just done an update do a CTRL-F5 in the browser (or long-press the reload button in mobile browsers) to reload all CSS and JavaScript files.");

exit();

// ---------------------------------------------------
function msg($s) {
	echo $s . PHP_EOL;
}

function errExit($s) {
	msg('ERROR: ' . $s);
	msg('Check directory permissions and that this script was run as sudo/root.');
	exit();
}
