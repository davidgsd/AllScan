#!/usr/bin/php
<?php
define('NL', "\n");

/* This is currently just a test script, that copies some files. This does not need to be executed,
docs/rpt.conf has all needed instructions and commands for setting up AllScan-recommended DTMF commands.
The rest of this file should be ignored for now. */

/* This script will copy the necessary shell script and speech files to /usr/share/asterisk/agi-bin/
to support DTMF commands for saying the node's LAN, mDNS, and WAN IP addresses, turning WiFi on and off,
rebooting or powering off, and playing back a test audio file.

NOTE: See the comments in docs/rpt.conf and follow the instructions there.
*/
// This should be run from CLI only (SSH)
if(isset($_SERVER['REMOTE_ADDR']) || isset($_SERVER['HTTP_HOST'])) {
	echo "This script must be run from the Command Line Interface only.<br>\n";
	exit(0);
}

$dir1 = 'agi-bin';
$dir2 = '/usr/share/asterisk/agi-bin';
if(!is_dir($dir1))
	errExit("Dir '$dir1' not found.");

if(!is_dir($dir2))
	errExit("Dir '$dir2' not found.");

msg("Copying .sh and .ul files from '$dir1' to '$dir2'...");

if(!passthruCmd("ls -l $dir1/"))
	errExit('ls failed');

if(!passthruCmd("ls -l $dir2/"))
	errExit('ls failed');

if(!passthruCmd("cp $dir1/* $dir2/"))
	errExit('cp failed');

msg("Updating file permissions...");

if(!passthruCmd("chmod 755 $dir2/*.sh"))
	errExit('chmod failed');

if(!passthruCmd("chmod 644 $dir2/*.ul"))
	errExit('chmod failed');

if(!passthruCmd("ls -l $dir2/"))

msg('Files copied successfully. See comments in this file for text to add to rpt.conf and extensions.conf');

exit();

// ---------------------------------------------------
// Execute command, show the command, show the output and return val
function execCmd($cmd) {
	msg("Executing cmd: $cmd");
	$out = '';
	$res = 0;
	$ok = (exec($cmd, $out, $res) !== false && !$res);
	$s = $ok ? 'OK' : 'ERROR';
	msg("Return Code: $s");
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

function msg($s) {
	echo $s . NL;
}

function errExit($s) {
	msg('ERROR: ' . $s);
	msg('Check directory permissions and that this script was run as sudo/root.');
	exit();
}
