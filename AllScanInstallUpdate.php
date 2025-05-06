#!/usr/bin/php
<?php
$AllScanInstallerUpdaterVersion = "1.27";
define('NL', "\n");
// Execute this script by running "sudo ./AllScanInstallUpdate.php" from any directory. We'll then determine
// the location of the web root folder, cd to that folder, check if you have AllScan installed and install
// it if not, or will check the version and update the install if a newer version is available.
//
// Updating can result in modified files being overwritten. This script will backup the allscan
// folder to allscan.bak.[ver#]/ You may then need to copy any added/modified files back into the folder.
//
// This should be run from CLI only (SSH), not over the web
if(isset($_SERVER['REMOTE_ADDR']) || isset($_SERVER['HTTP_HOST'])) {
	echo "This script must be run from the Command Line Interface only.<br>\n";
	exit(0);
}

// Check if this file is the most recent version
checkInstallerVersion();

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
clearstatcache();
// Check if dir exists. If so, see if an update is needed. If not, install AllScan
$dlfiles = true;
$ver = 'Unknown';
if(is_dir($asdir)) {
	msg("$asdir dir exists. Checking if update needed...");
	if(checkUpdate($ver)) {
		msg("AllScan is out-of-date.");
		$s = readline("Ready to Update AllScan. Enter 'y' to confirm, any other key to exit: ");
		if($s !== 'y')
			exit();
		$bak = "$asdir.bak.$ver";
		msg("Moving $asdir/ to $bak/...");
		if(is_dir($bak))
			execCmd("rm -rf $bak");
		execCmd("mv $asdir $bak");
	} else {
		msg("AllScan is up-to-date.");
		$dlfiles = false;
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
		errExit("Retrieve $fname from github failed. Try executing \"wget '$url'\" and check error messages. Also confirm that your node supports https and that its system time/RTC is set correctly.");
	if(!execCmd("unzip -q $fname"))
		errExit('Unzip failed. Check that you have unzip installed. Try "sudo apt-get install unzip" to install');
	unlink($fname);
	if(!rename($zdir, $asdir))
		msg("ERROR: mv($zdir, $asdir) failed");
	// Copy any user .ini files from old version backup folder
	if(isset($bak) && is_dir($bak)) {
		msg("Checking for .ini files in $bak/...");
		execCmd("cp -n $bak/*.ini $asdir/");
	}
}

msg("Verifying $asdir dir has $group group writable permissions");
if((fileperms($asdir) & 0777) != 0775)
	execCmd("chmod 775 $asdir");
if(getGroupName($asdir) !== $group)
	execCmd("chgrp $group $asdir");
$inis = glob("$asdir/*{.ini,.ini.bak}", GLOB_BRACE);
if(!empty($inis)) {
	foreach($inis as $f) {
		if((fileperms($f) & 0777) != 0664)
			execCmd("chmod 664 $f");
		if(getGroupName($f) !== $group)
			execCmd("chgrp $group $f");
	}
}

checkDbDir();

checkSmDir();

msg("PHP Version: " . phpversion());
$astver = trim(shell_exec('asterisk -V') ?? 'Unknown');
msg("Asterisk Version: " . $astver);

// Confirm necessary php extensions are installed
msg("Checking OS packages and PHP extensions...");
if(!class_exists('SQLite3')) {
	msg("Required SQLite3 Class not found." . NL
		."Try running the update commands below to update your OS and php-sqlite3 package." . NL
		."You may also need to enable the pdo_sqlite and sqlite3 extensions in php.ini.");
}

msg("Would you like to check for OS/package updates?" . NL
	."If you recently updated your system or if everything is working great you do not need to do this now. " . NL
	."The update process can cause some software packages to stop working or require config file updates." . NL
	."DO NOT PROCEED WITH THIS STEP IF YOU'RE NOT SURE OR IF YOU'RE NOT AN AUTHORIZED SERVER ADMIN." . NL
	."Otherwise it is recommended to ensure your system is up-to-date.");
$s = readline("Enter 'y' to proceed, any other key to skip this step: ");
if($s === 'y') {
	if(is_executable('/usr/bin/apt-get')) {
		passthruCmd("apt-get -y update");
		passthruCmd("apt-get upgrade");
	} elseif(is_executable('/usr/bin/yum')) {
		passthruCmd("yum -y update");
		passthruCmd("yum upgrade");
	} elseif(is_executable('/usr/bin/pacman')) {
		passthruCmd("pacman -Syu");
		passthruCmd("pacman php-sqlite");
	}
	restartWebServer();
}

// if ASL3, make sure astdb.txt is available, and sqlite3 and avahi-daemon are set up
if(is_executable('/usr/bin/apt')) {
	if(!is_file('/etc/systemd/system/asl3-update-astdb.service')) {
		passthruCmd("sudo apt install -y asl3-update-nodelist 2> /dev/null");
	}
	$fc = exec("find /var/lib/php -name sqlite3 -type f -printf '.'| wc -c");
	if(!$fc) {
		passthruCmd("sudo apt install -y php-sqlite3 2> /dev/null");
		restartWebServer();
	}
	if(!is_executable('/usr/bin/asl-tts')) {
		passthruCmd("sudo apt install -y asl3-tts 2> /dev/null");
	}
	if(!is_executable('/usr/sbin/avahi-daemon')) {
		passthruCmd("sudo apt install -y avahi-daemon 2> /dev/null");
	}
	sleep(1);
	if(is_file('/etc/systemd/system/asl3-update-astdb.service')) {
		if(exec('systemctl is-enabled asl3-update-astdb.service') !== "enabled")
			passthruCmd("systemctl enable asl3-update-astdb.service 2> /dev/null");
		if(exec('systemctl is-enabled asl3-update-astdb.timer') !== "enabled") {
			passthruCmd("systemctl enable asl3-update-astdb.timer 2> /dev/null");
			passthruCmd("systemctl start asl3-update-astdb.timer 2> /dev/null");
		}
		// Make a readable copy of allmon3.ini (Allmon3 updates can reset the file permissions)
		$fname = '/etc/allmon3/allmon3.ini';
		if(file_exists($fname)) {
			$fname2 = '/etc/asterisk/allmon.ini.php';
			if(exec("diff $fname $fname2"))
				execCmd("cp $fname $fname2");
			if((fileperms($fname2) & 0777) != 0660)
				execCmd("chmod 660 $fname2");
			if(getGroupName($fname2) !== $group)
				execCmd("chgrp $group $fname2");
		}
	}
}

// Confirm SQLite3 is in enabled in php.ini
$fn = exec('sudo find /etc/php -name php.ini |grep -v cli');
if($fn) {
	msg("php.ini location: $fn");
	$lcgood = exec("grep sqlite /etc/php/8.2/apache2/php.ini | grep '^extension=' | wc -l");
	$lcbad = exec("grep sqlite /etc/php/8.2/apache2/php.ini | grep '^;extension=' | wc -l");
	if($lcgood >= 2)
		msg("php.ini appears to have SQLite3 enabled");
	elseif($lcbad < 2) {
		msg("\nWARNING: SQLite3 extension does not appear to be enabled in php.ini.\n"
			."You may need to manually edit the file and uncomment (remove leading ';') "
			."the lines that say 'extension=pdo_sqlite' and 'extension=sqlite3'.");
		$s = readline("Hit any key to confirm");
	} else {
		msg("Backing up php.ini -> $fn.bak");
		execCmd("cp $fn $fn.bak");
		msg("Enabling SQLite3 extension in php.ini");
		execCmd("sed -i 's/;extension=pdo_sqlite/extension=pdo_sqlite/g' $fn");
		execCmd("sed -i 's/;extension=sqlite3/extension=sqlite3/g' $fn");
		restartWebServer();
	}
} else {
	msg("php.ini not found in /etc/php/");
}

// Check DTMF command script and audio files
$d0 = "$webdir/$asdir/_tools/agi-bin";
$d1 = "/usr/share/asterisk/agi-bin";
$ls0 = trim(shell_exec("ls $d0 2>/dev/null"));
if(!$ls0) {
	msg("Error reading $d0/.");
} else {
	if(!file_exists($d1)) {
		msg("$d1/ not found.");
	} else {
		$f0 = explode(NL, $ls0);
		sort($f0);
		$ls1 = trim(shell_exec("ls $d1 2>/dev/null"));
		$f1 = $ls1 ? explode(NL, $ls1) : [];
		sort($f1);
		$fcp = [];
		$ov = 0;
		foreach($f0 as $f) {
			if(array_search($f, $f1) === false) {
				$fcp[] = $f;
			} elseif(exec("diff $d0/$f $d1/$f")) {
				$fcp[] = $f;
				$ov++;
			}
		}
		if(count($fcp)) {
			msg("Copy DTMF command support files to /usr/share/asterisk/agi-bin/?\n"
				."Files to be copied: " . implode(', ', $fcp));
			if($ov)
				msg("Warning: This will result in $ov file(s) being overwritten");
			if(count($f1))
				msg("Existing files in directory: " . implode(', ', $f1));
			$s = readline("Enter 'y' to confirm, any other key to skip: ");
			if($s === 'y') {
				foreach($fcp as $f)
					execCmd("cp $d0/$f $d1/$f");
			}
		}
	}
	msg("See $webdir/$asdir/docs/rpt.conf and extensions.conf for DTMF command setup notes.");
}

msg("Install/Update Complete.");

// Show URLs where AllScan can be accessed and other notes
$nn = exec("grep '^NODE = ' /etc/asterisk/extensions.conf");
$bp = exec("grep '^bindport = ' /etc/asterisk/iax.conf");
$ip = exec("wget -t 1 -T 3 -q -O- http://checkip.dyndns.org:8245 | cut -d':' -f2 | cut -d' ' -f2 | cut -d'<' -f1");
$hn = exec('hostname');
$lanip = exec("hostname -I | cut -f1 -d' '");
if(!filter_var($lanip, FILTER_VALIDATE_IP)) {
	$lanip = exec("ifconfig | grep inet | head -1 | awk '{print $2}'");
	if($lanip === '127.0.0.1')
		$lanip = exec("ifconfig | grep inet | tail -1 | awk '{print $2}'");
}
$lip = '';
if($lanip)
	$lip = "http://$lanip/$asdir/";
if($hn) {
	if($lip)
		$lip .= ' or ';
	$lip .= "http://$hn.local/$asdir/";
}
if(!$lip)
	$lip = '[Local IPV4 address / hostname could not be determined]';

$wip = "http://$ip/$asdir/";
if(preg_match('/NODE = ([0-9]{4,6})/', $nn, $m) == 1) {
	$node = $m[1];
	$wip .= " or http://$node.nodes.allstarlink.org/$asdir/";
}
$port = 4569;
if(preg_match('/bindport = ([0-9]{4,5})/', $bp, $m) == 1) {
	$port = $m[1];
}

msg("AllScan can be accessed at:\n\t$lip on the local network, or\n"
	."\t$wip remotely\n\tif your router has port $port forwarded to this node.");

if($dlfiles) {
	msg("Be sure to bookmark the above URL(s) in your browser.");
	msg("IMPORTANT: After updates do a CTRL-F5 in your browser (or long-press the reload button in mobile\n"
		."browsers), or clear the browser cache, so that CSS and JavaScript files will properly update.");
}

exit();

// ---------------------------------------------------

function checkUpdate(&$ver) {
	global $asdir;
	$fname = "include/common.php";
	if(!file_exists("$asdir/$fname"))
		return true;
	$file = file("$asdir/$fname");
	if(empty($file))
		return true;
	foreach($file as $line) {
		if(preg_match('/^\$AllScanVersion = "v([0-9\.]{3,4})"/', $line, $m) == 1) {
			$ver = $m[1];
			break;
		}
	}
	msg("AllScan current version: $ver");
	if($ver < 0.92)
		return true;

	$url = "https://raw.githubusercontent.com/davidgsd/AllScan/main/$fname";
	$file = file($url);
	if(empty($file))
		errExit("Retrieve $fname from github failed. Try executing \"wget '$url'\" and check error messages. Also confirm that your node supports https and that its system time/RTC is set correctly.");

	foreach($file as $line) {
		if(preg_match('/^\$AllScanVersion = "v([0-9\.]{3,4})"/', $line, $m) == 1) {
			$gver = $m[1];
			break;
		}
	}
	if(empty($gver))
		errExit("Error parsing $url. Please visit $asurl and follow the install/update instructions.");

	msg("AllScan github version: $gver");
	return ($ver < $gver);
}

function checkInstallerVersion() {
	global $AllScanInstallerUpdaterVersion;
	msg("AllScan Installer/Updater Version: $AllScanInstallerUpdaterVersion");
	$fname = basename(__FILE__);
	msg("Checking github version...");
	$url = "https://raw.githubusercontent.com/davidgsd/AllScan/main/$fname";
	$asurl = 'https://github.com/davidgsd/AllScan';
	$file = file($url);
	if(empty($file))
		errExit("Retrieve $fname from github failed. Try executing \"wget '$url'\" and check error messages. Also confirm that your node supports https and that its system time/RTC is set correctly.");

	foreach($file as $line) {
		if(preg_match('/^\$AllScanInstallerUpdaterVersion = "([0-9\.]{3,4})"/', $line, $m) == 1) {
			$gver = $m[1];
			break;
		}
	}
	if(empty($gver))
		errExit("Error parsing $url. Please visit $asurl and follow the install/update instructions there.");

	msg("AllScan Installer/Updater github version: $gver");
	if($AllScanInstallerUpdaterVersion != $gver)
		errExit("This file is out-of-date. Please visit $asurl and follow the install/update instructions there.");
}

// Execute command, show the command, show the output and return val
function execCmd($cmd) {
	echo "Executing cmd: $cmd ... ";
	$out = '';
	$res = 0;
	$ok = (exec($cmd, $out, $res) !== false && !$res);
	$s = $ok ? 'OK' : 'ERROR';
	msg($s);
	return $ok;
}

function passthruCmd($cmd) {
	msg("Executing cmd: $cmd");
	$res = 0;
	$ok = (passthru($cmd, $res) !== false && !$res);
	$s = $ok ? 'OK' : 'ERROR';
	msg("Return Code: $s");
	return $ok;
}

function checkDbDir() {
	global $group, $ver;
	// Confirm /etc/allscan dir exists and is writable by web server
	$asdbdir = '/etc/allscan';
	if(!is_dir($asdbdir))
		execCmd("mkdir $asdbdir");
	if((fileperms($asdbdir) & 0777) != 0775)
		execCmd("chmod 775 $asdbdir");
	if(getGroupName($asdbdir) !== $group)
		execCmd("chgrp $group $asdbdir");
	// Backup DB file
	$dbfile = $asdbdir . '/allscan.db';
	if(!$ver)
		$ver = 'bak';
	$bakfile = "$dbfile.$ver";
	if(file_exists($dbfile) && !file_exists($bakfile))
		execCmd("cp $dbfile $bakfile");
}

function checkSmDir() {
	global $group;
	// Verify supermon folder favorites.ini and favorites.ini.bak writable by web server
	$smdir = 'supermon';
	if(is_dir($smdir)) {
		$favsini = 'favorites.ini';
		$favsbak = "$favsini.bak";
		msg("Confirming supermon $favsini and $favsbak writable by web server");
		chdir($smdir);

		if(!file_exists($favsini))
			execCmd("touch $favsini");
		if(!file_exists($favsbak))
			execCmd("touch $favsbak");

		if((fileperms($favsini) & 0777) != 0664)
			execCmd("chmod 664 $favsini");
		if((fileperms($favsbak) & 0777) != 0664)
			execCmd("chmod 664 $favsbak");
		if((fileperms('.') & 0777) != 0775)
			execCmd("chmod 775 .");

		if(getGroupName($favsini) !== $group)
			execCmd("chgrp $group $favsini");
		if(getGroupName($favsbak) !== $group)
			execCmd("chgrp $group $favsbak");
		if(getGroupName('.') !== $group)
			execCmd("chgrp $group .");

		chdir('..');
	}
}

function restartWebServer() {
	msg("Restarting web server...");
	if(is_executable('/usr/bin/apachectl') || is_executable('/usr/sbin/apachectl'))
		$cmd = "apachectl restart 2> /dev/null";
	else
		$cmd = "systemctl restart lighttpd.service 2> /dev/null";
	if(!execCmd($cmd))
		msg("Restart webserver or restart node now");
}

function getGroupName($f) {
	if(!($id = filegroup($f)))
		return '';
	$a = posix_getgrgid($id);
	return $a['name'] ?? '';
}

function msg($s) {
	echo $s . NL;
}

function errExit($s) {
	msg("\nERROR: $s\n");
	exit();
}
