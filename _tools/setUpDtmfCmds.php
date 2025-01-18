#!/usr/bin/php
<?php
define('NL', "\n");
/* This script will copy the necessary shell script and speech files to /usr/share/asterisk/agi-bin/ to support DTMF commands for saying the node's LAN or WAN IP address, turning WiFi on and off, rebooting or powering off, and playing back a test audio file, First add the following to rpt.conf:

69 = autopatchup,context=my-ip,noct=1,farenddisconnect=1,dialtime=9000,quiet=1


Then add the following to extensions.conf:

; *690: Say LAN IP Address
exten => 0,1,AGI(getip.sh)
exten => 0,n,Wait(1)
exten => 0,n,SayAlpha(${result})
exten => 0,n,Hangup

; *691: Say WAN IP Address
exten => 1,1,Set(result=${CURL(https://api.ipify.org)})
exten => 1,n,Wait(1)
exten => 1,n,SayAlpha(${result})
exten => 1,n,Hangup

; *692: Turn Off Wi-Fi
exten => 2,1,AGI(wifidown.sh)
exten => 2,n,Wait(1)
exten => 2,n,Playback(/usr/share/asterisk/agi-bin/wifidisabled)
exten => 2,n,Hangup

; *693: Turn On Wi-Fi
exten => 3,1,AGI(wifiup.sh)
exten => 3,n,Wait(1)
exten => 3,n,Playback(/usr/share/asterisk/agi-bin/wifienabled)
exten => 3,n,Hangup

; *694: Reboot Node
exten => 4,1,AGI(reboot.sh)
exten => 4,n,Wait(1)
exten => 4,n,Playback(/usr/share/asterisk/agi-bin/reboot)
exten => 4,n,Hangup

; *695: Power Off Node
exten => 5,1,AGI(poweroff.sh)
exten => 5,n,Wait(1)
exten => 5,n,Playback(/usr/share/asterisk/agi-bin/poweroff)
exten => 5,n,Hangup

; *699: Play test file
exten => 9,1,Wait(1)
exten => 9,n,Playback(/usr/share/asterisk/agi-bin/test)
exten => 9,n,Hangup


Then enable the following lines in modules.conf

load => res_agi.so                  ; Asterisk Gateway Interface (AGI)
load => res_speech.so               ; Generic Speech Recognition API


Then execute this file (sudo php ./setUpDtmfCmds.php) to copy the .sh and .ul files to the Asterisk agi-bin folder and set the correct permissions,
*/
// This should be run from CLI only (SSH), not over the web
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
