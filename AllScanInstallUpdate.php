#!/usr/bin/php
<?php
$AllScanInstallerUpdaterVersion = "v1.25";
define('NL', "\n");
// Execute this script by running "sudo ./AllScanInstallUpdate.php" from any directory. We'll then determine
// the location of the web root folder, cd to that folder, check if you have AllScan installed and install
// it if not, or will check the version and update the install if a newer version is available.
//
// NOTE: Updating can result in modified files being overwritten. This script will backup the allscan
// folder to allscan.bak.[ver#]/ You may then need to copy any added/modified files back into the folder.
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
clearstatcache();

// Check if dir exists. If so, see if an update is needed. If not, install AllScan
$dlfiles = true;
if(is_dir($asdir)) {
	msg("$asdir dir exists. Checking if update needed...");
	$fname = 'common.php';
	if(($s = `grep '^\$AllScanVersion' $asdir/include/$fname`)) {
		if(preg_match('/"v([0-9\.]{3,5})"/', $s, $m) == 1)
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
		if(preg_match('/"v([0-9\.]{3,5})"/', $s, $m) == 1)
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
execCmd("chmod 664 $asdir/*.ini");
execCmd("chgrp $group $asdir/*.ini");

checkDbDir();

checkSmDir();

msg("PHP Version: " . phpversion());

// Confirm necessary php extensions are installed
msg("Checking OS packages and PHP extensions...");
if(!class_exists('SQLite3')) {
	msg("Required SQLite3 Class not found." . NL
		."Try running the update commands below to update your OS and php-sqlite3 package." . NL
		."You may also need to enable the pdo_sqlite and sqlite3 extensions in php.ini.");
}

msg("Ready to run OS update/upgrade commands." . NL
	."If you have recently updated and upgraded your system you do not need to do this now." . NL
	."THE UPDATE/UPGRADE PROCESS CAN POTENTIALLY CAUSE OLDER SOFTWARE PACKAGES TO STOP WORKING." . NL
	."DO NOT PROCEED WITH THIS STEP IF YOU ARE NOT SURE OR IF YOU ARE NOT AN AUTHORIZED SERVER ADMIN." . NL
	."Otherwise it is recommended to ensure your system is up-to-date.");
$s = readline("Enter 'y' to proceed, any other key to skip this step: ");
if($s === 'y') {
	if(is_executable('/usr/bin/apt-get')) {
		passthruCmd("apt-get -y update");
		passthruCmd("apt-get -y upgrade");
	} elseif(is_executable('/usr/bin/yum')) {
		passthruCmd("yum -y update");
		passthruCmd("yum -y upgrade");
	} elseif(is_executable('/usr/bin/pacman')) {
		passthruCmd("pacman -Syu");
		passthruCmd("pacman -S php-sqlite");
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

// Copy DTMF command script and audio files to Asterisk folder
msg("Copy DTMF command support files to /usr/share/asterisk/agi-bin/?");
$s = readline("Enter 'y' to confirm, any other key to skip: ");
if($s === 'y') {
	$d0 = "$webdir/$asdir/_tools/agi-bin";
	$d1 = "/usr/share/asterisk/agi-bin";
	$ls0 = trim(shell_exec("ls $d0"));
	if(!$ls0) {
		msg("Error reading $d0/.");
	} else {
		if(!file_exists($d1)) {
			msg("$d1/ not found.");
		} else {
			$f0 = explode(NL, $ls0);
			$ls1 = trim(shell_exec("ls $d1"));
			$f1 = $ls1 ? explode(NL, $ls1) : [];
			$fcp = [];
			foreach($f0 as $f) {
				if(array_search($f, $f1) === false || exec("diff $d0/$f $d1/$f"))
					$fcp[] = $f;
			}
			if(!count($fcp)) {
				msg("Files already present.");
			} else {
				foreach($fcp as $f)
					execCmd("cp $d0/$f $d1/$f");
			}
		}
		msg("See $webdir/$asdir/docs/rpt.conf and extensions.conf for DTMF command setup notes.");
	}
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
	// Verify supermon folder favorites.ini and favorites.ini.bak are writable by web server
	$smdir = 'supermon';
	if(is_dir($smdir)) {
		$favsini = 'favorites.ini';
		$favsbak = "$favsini.bak";
		msg("Confirming supermon $favsini and $favsbak are writable by web server");
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
	msg('ERROR: ' . $s);
	msg('Check directory permissions and that this script was run as sudo/root.');
	exit();
}
