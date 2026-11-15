<?php
// scheduleUtils.php - Scheduled node connect/disconnect support
//
// Schedules are stored as JSON in /etc/allscan/schedule.json (web server writeable).
// They are executed by _tools/runSchedule.php, which must be called once per minute from
// root's crontab. The web UI only edits the JSON file, it never touches crontab.

define('SCHED_FILE', '/etc/allscan/schedule.json');
// Written by runSchedule.php each time it runs. Used by the UI to detect a missing cron entry
define('SCHED_HEARTBEAT', '/etc/allscan/schedule.heartbeat');
define('SCHED_MAX_ENTRIES', 50);
// Activity lines appended by runSchedule.php (root). astapi/server.php forwards new lines to the
// browser's status message box
define('SCHED_ACTIVITY', '/etc/allscan/schedule.activity');
define('SCHED_ACTIVITY_MAXLINES', 200);

$schedActions = [
	'connect' => 'Connect',
	'monitor' => 'Monitor',
	'localmonitor' => 'Local Monitor',
	'disconnect' => 'Disconnect'
];

// Use the system timezone so that the web server and cron runner agree on what "08:00" means
function schedSetTimezone() {
	$tz = @trim(file_get_contents('/etc/timezone'));
	if($tz && @date_default_timezone_set($tz))
		return;
	$tz = @readlink('/etc/localtime');
	if($tz && preg_match('#zoneinfo/(.+)$#', $tz, $m))
		@date_default_timezone_set($m[1]);
}

function schedLoad() {
	if(!file_exists(SCHED_FILE))
		return [];
	$s = file_get_contents(SCHED_FILE);
	$a = json_decode($s, true);
	return is_array($a) ? array_values($a) : [];
}

function schedSave($entries, &$msg) {
	$json = json_encode(array_values($entries), JSON_PRETTY_PRINT);
	if(file_put_contents(SCHED_FILE, $json, LOCK_EX) === false) {
		$msg = 'Write ' . SCHED_FILE . ' failed. Check directory/file permissions';
		return false;
	}
	return true;
}

// Validate user input for a new entry. Returns entry array or false with $err set
function schedValidate($in, &$err) {
	global $schedActions;
	$action = $in['action'] ?? '';
	if(!isset($schedActions[$action])) {
		$err = 'Invalid action';
		return false;
	}
	$node = $in['node'] ?? '';
	if($action === 'disconnect' && $node === '0') {
		// Disconnect all
	} elseif(!ctype_digit($node) || strlen($node) < 1 || strlen($node) > 7) {
		$err = 'Invalid node number';
		return false;
	}
	$time = $in['time'] ?? '';
	if(!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $time)) {
		$err = 'Time must be HH:MM (24 hour)';
		return false;
	}
	$days = [];
	foreach(explode(',', $in['days'] ?? '') as $d) {
		if($d === '')
			continue;
		if(!ctype_digit($d) || $d > 6) {
			$err = 'Invalid day';
			return false;
		}
		$days[(int)$d] = (int)$d;
	}
	sort($days);
	if(!count($days))
		$days = [0, 1, 2, 3, 4, 5, 6];
	return [
		'id' => bin2hex(random_bytes(4)),
		'action' => $action,
		'node' => $node,
		'time' => $time,
		'days' => $days,
		'perm' => ($in['perm'] ?? '') === 'true',
		'autodisc' => ($in['autodisc'] ?? '') === 'true',
		'enabled' => true
	];
}

function schedDue($e, $now) {
	return !empty($e['enabled']) && $e['time'] === date('H:i', $now)
		&& in_array((int)date('w', $now), $e['days']);
}

function schedDescribe($e) {
	global $schedActions;
	$names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
	$d = count($e['days']) == 7 ? 'Daily' : implode(',', array_map(function($i) use ($names) { return $names[$i]; }, $e['days']));
	return $schedActions[$e['action']] . ' ' . ($e['node'] === '0' ? 'all' : $e['node'])
		. ($e['perm'] ? ' (perm)' : '') . ' at ' . $e['time'] . ' ' . $d;
}

// Execute one entry via AMI. $ami is a connected/logged-in AMI object, $fp its socket.
// Mirrors the behavior of astapi/connect.php. Returns a status string.
function schedExecute($ami, $fp, $localnode, $e) {
	$node = $e['node'];
	$out = [];
	switch($e['action']) {
		case 'connect':
			if(!empty($e['autodisc'])) {
				$out[] = $ami->command($fp, "rpt cmd $localnode ilink 6 0");
				usleep(500000);
			}
			$ilink = $e['perm'] ? 13 : 3;
			break;
		case 'monitor':
			if(!empty($e['autodisc'])) {
				$out[] = $ami->command($fp, "rpt cmd $localnode ilink 6 0");
				usleep(500000);
			}
			$ilink = $e['perm'] ? 12 : 2;
			break;
		case 'localmonitor':
			$ilink = $e['perm'] ? 18 : 8;
			break;
		case 'disconnect':
			$ilink = ($node === '0') ? 6 : 11;
			break;
		default:
			return 'Unknown action';
	}
	$out[] = $ami->command($fp, "rpt cmd $localnode ilink $ilink $node");
	return implode(' ', $out);
}

// Write to a file that may have been created by another user (eg. root, by an older cron entry).
// If the write fails but the directory is writable, replace the file with one we own.
function schedWrite($file, $data, $flags=0) {
	if(@file_put_contents($file, $data, $flags) !== false)
		return true;
	if(file_exists($file) && is_writable(dirname($file)) && @unlink($file))
		return @file_put_contents($file, $data, $flags) !== false;
	return false;
}

// Append a line to the activity file, keeping only the last SCHED_ACTIVITY_MAXLINES lines.
function schedActivity($text) {
	$text = trim(preg_replace('/\s+/', ' ', $text));
	$line = 'Schedule ' . date('H:i:s') . ': ' . $text . "\n";
	schedWrite(SCHED_ACTIVITY, $line, FILE_APPEND | LOCK_EX);
	$lines = @file(SCHED_ACTIVITY);
	if($lines && count($lines) > SCHED_ACTIVITY_MAXLINES * 1.25)
		schedWrite(SCHED_ACTIVITY, implode('', array_slice($lines, -SCHED_ACTIVITY_MAXLINES)), LOCK_EX);
}

// ---- Cron entry management ----
// The runner is called once per minute from the crontab of the user these functions run as
// (the web server user when called from the web UI). It does not need root.
define('SCHED_LOG', '/etc/allscan/schedule.log');

function schedRunnerPath() {
	return realpath(__DIR__ . '/../_tools/runSchedule.php');
}
function schedCronLine() {
	return '* * * * * ' . schedRunnerPath() . ' >> ' . SCHED_LOG . ' 2>&1';
}
function schedCrontabBin() {
	foreach(['/usr/bin/crontab', '/bin/crontab', '/usr/sbin/crontab'] as $f)
		if(is_executable($f))
			return $f;
	return false;
}
// Current user's crontab lines, or false if crontab is unavailable/not permitted
function schedCronRead() {
	$bin = schedCrontabBin();
	if(!$bin)
		return false;
	exec("$bin -l 2>/dev/null", $out, $rc);
	// rc 1 with no output = no crontab for this user yet
	return ($rc === 0 || !count($out)) ? $out : false;
}
function schedCronHasEntry($lines) {
	foreach($lines ?: [] as $l)
		if(strpos($l, 'runSchedule.php') !== false && substr(ltrim($l), 0, 1) !== '#')
			return true;
	return false;
}
function schedRunnerActive() {
	return file_exists(SCHED_HEARTBEAT) && time() - filemtime(SCHED_HEARTBEAT) < 180;
}
// Scheduling is available if our crontab has the entry or the runner has run recently
// (eg. from root's crontab)
function schedEnabled() {
	return schedCronHasEntry(schedCronRead()) || schedRunnerActive();
}
function schedCronWrite($lines, &$err) {
	$bin = schedCrontabBin();
	if(!$bin) {
		$err = 'crontab command not found';
		return false;
	}
	$p = proc_open("$bin -", [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	if(!is_resource($p)) {
		$err = 'Unable to run crontab';
		return false;
	}
	fwrite($pipes[0], implode("\n", $lines) . (count($lines) ? "\n" : ''));
	fclose($pipes[0]);
	$out = trim(stream_get_contents($pipes[1]) . ' ' . stream_get_contents($pipes[2]));
	fclose($pipes[1]);
	fclose($pipes[2]);
	if(proc_close($p) !== 0) {
		$err = "crontab failed: $out";
		return false;
	}
	return true;
}
function schedCronEnable(&$err) {
	$lines = schedCronRead();
	if($lines === false) {
		$err = 'Unable to read crontab. The web server user may not be permitted to use cron (check /etc/cron.allow and /etc/cron.deny)';
		return false;
	}
	if(schedCronHasEntry($lines))
		return true;
	if(!schedRunnerPath()) {
		$err = '_tools/runSchedule.php not found';
		return false;
	}
	if(!is_executable(schedRunnerPath())) {
		$err = '_tools/runSchedule.php is not executable. Run the AllScan update script or chmod +x it';
		return false;
	}
	$lines[] = schedCronLine();
	return schedCronWrite($lines, $err);
}
function schedCronDisable(&$err) {
	$lines = schedCronRead();
	if($lines === false) {
		$err = 'Unable to read crontab';
		return false;
	}
	$keep = array_values(array_filter($lines, function($l) { return strpos($l, 'runSchedule.php') === false; }));
	if(count($keep) === count($lines))
		return true;
	return schedCronWrite($keep, $err);
}
