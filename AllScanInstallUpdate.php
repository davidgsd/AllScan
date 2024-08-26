#!/usr/bin/php
<?php
$AllScanInstallerUpdaterVersion = "v1.21";
define('NL', "\n");
// Execute this script by running "sudo ./AllScanInstallUpdate.php" from any directory. The script will then determine
// the location of the web root folder on your system, cd to that folder, check if you have AllScan installed and install
// it if not, or if already installed will check the version and update the install if a newer version is available.
//
// NOTE: Updating can result in modified files being overwritten. This script will make a backup copy of the allscan
// folder to allscan.bak.[ver#]/ You may then need to copy any files you added/modified back into the allscan folder.
//
msg("AllScan Installer/Updater Version: $AllScanInstallerUpdaterVersion");

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
$name = ['www-data', 'http', 'apache'];
foreach($name as $n) {
	if(`grep "^$n:" /etc/group`) {
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
// Enable mkdir(..., 0775) to work properly
umask(0002);

// Check if dir exists. If so, see if an update is needed. If not, install AllScan
$dlfiles = true;
if(is_dir($asdir)) {
	msg("$asdir dir exists. Checking if update needed...");
	$fname = 'common.php';
	if(($s = `grep '^\$AllScanVersion' $asdir/include/$fname`)) {
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
	if(!execCmd("wget -q '$url'") || !file_exists($fname))
		errExit("wget $fname failed.");

	if(($s = `grep '^\$AllScanVersion' $fname`)) {
		if(preg_match('/"v([0-9\.]{3,4})"/', $s, $m) == 1)
			$gver = $m[1];
	}
	unlink($fname);
	if(empty($gver))
		$gver = 'Unknown';
	msg("Allscan github version: $gver");

	if($gver <= $ver) {
		msg("AllScan is up-to-date.");
		$dlfiles = false;
	} else {
		msg("AllScan is out-of-date.");

		$s = readline("Ready to Update AllScan. Enter 'y' to confirm, any other key to exit: ");
		if($s !== 'y')
			exit();

		$bak = "$asdir.bak.$ver";
		msg("Moving $asdir/ to $bak/...");
		if(is_dir($bak))
			execCmd("rm -rf $bak");
		execCmd("mv $asdir $bak");
	}
} else {
	msg("$asdir dir not found.");
	$s = readline("Ready to Install AllScan. Enter 'y' to confirm, any other key to exit: ");
	if($s !== 'y')
		exit();
}

if($dlfiles) {
	$fname = 'main.zip';
	$url = 'https://github.com/davidgsd/AllScan/archive/refs/heads/' . $fname;
	$zdir = 'AllScan-main';
	if(file_exists($fname))
		unlink($fname);
	if(is_dir($zdir))
		exec("rm -rf $zdir");
	if(!execCmd("wget -q '$url'") || !file_exists($fname))
		errExit("Retrieve $fname from github failed. Try executing \"wget '$url'\" and check error messages.");
	if(!execCmd("unzip -q $fname"))
		errExit('Unzip failed. Check that you have unzip installed. Try "sudo apt-get install unzip" to install');
	unlink($fname);
	if(!rename($zdir, $asdir))
		msg("ERROR: mv($zdir, $asdir) failed");
	// Copy any user .ini files from old version backup folder
	if(isset($bak) && is_dir($bak)) {
		msg("Checking for .ini files in $bak/...");
		execCmd("cp $bak/*.ini $asdir/");
		execCmd("chmod 664 $asdir/*.ini");
		execCmd("chgrp $group $asdir/*.ini");
	}
}

msg("Verifying $asdir dir has 0775 permissions and $group group");
if(!chmod($asdir, 0775))
	msg("ERROR: chmod($asdir, 0775) failed");

if(!chgrp($asdir, $group))
	msg("ERROR: chgrp($asdir, $group) failed");

checkDbDir();

checkSmDir();

msg("PHP Version: " . phpversion());

// Confirm necessary php extensions are installed
msg("Checking OS packages and PHP extensions...");
if(!class_exists('SQLite3')) {
	msg("NOTE: Required SQLite3 Class not found." . NL
		."Try running the update commands below to update your OS and php-sqlite3 package." . NL
		."You may also need to enable the pdo_sqlite and sqlite3 extensions in php.ini.");
}

msg("Ready to run OS update/upgrade commands." . NL
	."If you have recently updated and upgraded your system you do not need to do this now." . NL
	."NOTE: THE UPDATE/UPGRADE PROCESS CAN POTENTIALLY CAUSE OLDER SOFTWARE PACKAGES TO STOP WORKING." . NL
	."DO NOT PROCEED WITH THIS STEP IF YOU ARE NOT SURE OR IF YOU ARE NOT AN AUTHORIZED SERVER ADMIN." . NL
	."Otherwise it is recommended to ensure your system is up-to-date.");
$s = readline("Enter 'y' to proceed, any other key to skip this step: ");
if($s === 'y') {
	if(is_executable('/usr/bin/apt-get')) {
		execCmd("apt-get -y update");
		execCmd("apt-get -y upgrade");
		execCmd("apt-get install -y php-sqlite3 php-curl");
	} else if(is_executable('/usr/bin/yum')) {
		execCmd("yum -y update");
		execCmd("yum -y upgrade");
	} else if(is_executable('/usr/bin/pacman')) {
		execCmd("pacman -Syu");
		execCmd("pacman -S php-sqlite");
	}

	msg("Restarting web server...");
	if(is_executable('/usr/bin/apachectl') || is_executable('/usr/sbin/apachectl'))
		$cmd = "apachectl restart 2> /dev/null";
	else
		$cmd = "systemctl restart lighttpd.service 2> /dev/null";
	if(!execCmd($cmd))
		msg("Restart webserver or restart node now");
}

// if ASL3, make sure astdb.txt is available
if(is_file('/etc/systemd/system/asl3-update-astdb.service')) {
	execCmd("systemctl enable asl3-update-astdb.service 2> /dev/null");
	execCmd("systemctl enable asl3-update-astdb.timer 2> /dev/null");
	execCmd("systemctl start asl3-update-astdb.timer 2> /dev/null");
	// Make a readable copy of allmon3.ini (Allmon3 updates can reset the file permissions)
	$fname = '/etc/allmon3/allmon3.ini';
	if(file_exists($fname)) {
		$fname2 = '/etc/asterisk/allmon.ini.php';
		execCmd("cp $fname $fname2");
		execCmd("chmod 660 $fname2");
		execCmd("chgrp $group $fname2");
	}
}

msg("Install/Update Complete.");

// Show URLs where AllScan can be accessed and other notes
$ip = exec("wget -t 1 -T 3 -q -O- http://checkip.dyndns.org:8245 | cut -d':' -f2 | cut -d' ' -f2 | cut -d'<' -f1");
$lanip = exec('hostname --all-ip-addresses');
if(!filter_var($lanip, FILTER_VALIDATE_IP)) {
	$lanip = exec("ifconfig | grep inet | head -1 | awk '{print $2}'");
	if($lanip === '127.0.0.1')
		$lanip = exec("ifconfig | grep inet | tail -1 | awk '{print $2}'");
}

msg("AllScan can be accessed at:\n\thttp://$lanip/$asdir/ on the local network, or\n"
	."\thttp://$ip/$asdir/ remotely if your router has a port forwarded to this node.");

msg("Be sure to bookmark the above URL(s) in your browser, and if you have just done an update do a CTRL-F5 in the browser (or long-press the reload button in mobile browsers) to reload all CSS and JavaScript files.");

exit();

// ---------------------------------------------------
// Execute command, show the command, show the output and return val
function execCmd($cmd) {
	msg("Executing cmd: $cmd");
	$ret = system($cmd);
	$s = ($ret === false) ? 'ERROR' : 'OK';
	msg("Return Code: $s");
	return ($ret !== false);
}

function checkDbDir() {
	global $group, $ver;
	// Confirm /etc/allscan dir exists and is writable by web server
	$asdbdir = '/etc/allscan';
	if(!is_dir($asdbdir)) {
		msg("Creating $asdbdir dir with 0775 permissions and $group group");
		if(!mkdir($asdbdir, 0775))
			errExit('mkdir failed');
	}
	msg("Verifying $asdbdir dir has 0775 permissions and $group group");
	if(!chmod($asdbdir, 0775))
		msg("ERROR: chmod($asdbdir, 0775) failed");
	if(!chgrp($asdbdir, $group))
		msg("ERROR: chgrp($asdbdir, $group) failed");

	// Backup DB file
	$dbfile = $asdbdir . '/allscan.db';
	if(file_exists("$asdbdir.db")) {
		if(!$ver)
			$ver = 'bak';
		$bakfile = "$dbfile.$ver";
		copy($dbfile, $bakfile);
	}
}

function checkSmDir() {
	global $group;
	// Verify supermon folder favorites.ini and favorites.ini.bak are writeable by web server
	$smdir = 'supermon';
	if(is_dir($smdir)) {
		$favsini = 'favorites.ini';
		$favsbak = $favsini . '.bak';
		msg("Confirming supermon $favsini and $favsbak are writeable by web server");
		chdir($smdir);
		execCmd("touch $favsini $favsbak");
		execCmd("chmod 664 $favsini $favsbak");
		execCmd("chmod 775 .");
		execCmd("chgrp $group $favsini $favsbak .");
		chdir('..');
	}
}

function msg($s) {
	echo $s . NL;
}

function errExit($s) {
	msg('ERROR: ' . $s);
	msg('Check directory permissions and that this script was run as sudo/root.');
	exit();
}
