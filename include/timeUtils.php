<?php
require_once('timezones.php');
define('SECS_PER_DAY', 86400);
define('SECS_PER_WEEK', 604800);

function convertTS($dt, $tz_id, $dbFmt=false) {
	global $timezoneDef;
	$date = new DateTime($dt . ' UTC');
	if($dbFmt) {
		$fmt = 'Y-m-d H:i';
	} else {		
		// Show time only if from less than 22 hrs ago
		$now = new DateTime('now');
		$diff = $date->diff($now);
		if($diff->d || $diff->m || $diff->y || $diff->h > 21)
			$fmt = 'M j, Y g:i A';
		else
			$fmt = 'g:i A';
	}
	if($tz_id)
		$date->setTimezone(new DateTimeZone($timezoneDef[$tz_id]));
	return $date->format($fmt);
}
function convertTStoUTC($dt, $tz_id) {
	global $timezoneDef;
	if(!$tz_id)
		return $dt;
	$date = new DateTime($dt, new DateTimeZone($timezoneDef[$tz_id]));
	$date->setTimezone(new DateTimeZone('UTC'));
	return $date->format('Y-m-d H:i:s');
}
// Return seconds difference from GMT of specified time zone
function getTimeZoneOffset($tz) {
	$dtz = new DateTimeZone($tz);
	$dt = new DateTime('now', $dtz);
	return $dtz->getOffset($dt);
}

// TIME / DATE / TIMEZONE / CONVERSION FUNCTIONS
function utcToServerTimestamp($ts) {
	return toDateTime(gmstrtotime($ts));
}
function serverToUtcTimestamp($ts) {
	return toGmDateTime(strtotime($ts));
}
// Converts UTC date time to unix timestamp
function gmstrtotime($str) {
    return(strtotime($str . ' UTC'));
}
function togmt($ts) {
	$sec = gmdate('s', $ts);
	$min = gmdate('i', $ts);
	$hr  = gmdate('H', $ts);
	$day = gmdate('d', $ts);
	$mon = gmdate('m', $ts);
	$yr  = gmdate('Y', $ts);
	return mktime($hr, $min, $sec, $mon, $day, $yr);
}
function buildTimestamp($y, $mo, $d, $h, $m, $s) { // Result is in server timezone
	if($y < 100)
		$y += 2000;
	return date('Y-m-d H:i:s', mktime($h, $m, $s, $mo, $d, $y));
}
function getTimestamp($time, $tzid=null, $fmt='Y-m-d H:i') {
	global $timezoneDef;
	if(is_numeric($time))
		$time = '@' . $time;
	$date = new DateTime($time);
	if($tzid)
		$date->setTimezone(new DateTimeZone($timezoneDef[$tzid]));		
	return $date->format($fmt);
}
function getCurTimestamp($tzid=null) {
	global $timezoneDef;
	$fmt = 'Y-m-d H:i:s';
	if($tzid) {
		$date = new DateTime('now', new DateTimeZone($timezoneDef[$tzid]));
		return $date->format($fmt);
	}
	return date($fmt);
}
function getCurTime() { // Get cur time str (Server Timezone)
	return date('H:i:s');
}
function getCurTimestampGmt() { // Get cur datetime str (GMT)
	return toGmDateTime(time());
}
function toDateTime($ts) { // Convert Unix tstamp to Server Timezone datetime str
	return date('Y-m-d H:i:s', $ts);
}
function toGmDateTime($ts) { // Convert Unix tstamp to GMT datetime str
	return gmdate('Y-m-d H:i:s', $ts);
}
// Return # seconds into week starting Sunday 0000Z
function gmGetSecsIntoWeek($ts=null) {
	// Unix tstamp is in UTC (mod by 86400 gives UTC hour, verified on a server set to MDT zone)
	if(!$ts)
		$ts = time();
	// Unix time starts on Thursday. For 0=Sun...4=Thu..., Add 4 then mod 7
	return ($ts + 4 * SECS_PER_DAY) % SECS_PER_WEEK;
}
function diffSecs($str1, $str2=null) { // Returns: T2-T1 if T2 is set, otherwise time()-T1
	$ts1 = is_numeric($str1) ? $str1 : strtotime($str1);
	$ts2 = $str2 ? (is_numeric($str2) ? $str2 : strtotime($str2)) : time();
	return $ts2 - $ts1;
}
function diffDays($str1, $str2=null) { // Returns: T2-T1 if T2 is set, otherwise time()-T1 in days
	return diffSecs($str1, $str2) / 86400.0;
}
function diffDaysFromGmt($str1) {
	return (time() - gmstrtotime($str1)) / 86400.0;
}
function timeCmp($ts1, $ts2) { // Returns T1 - T2
	return strtotime($ts1) - strtotime($ts2);
}
function timeDiffReadable($from, $to=null) {
	$to = ($to === null) ? time() : $to;
	$to = is_int($to) ? $to : strtotime($to);
	$from = is_int($from) ? $from : strtotime($from);
	$units = ['year' => 29030400, 'month' => 2419200, 'week' => 604800,
				'day' => 86400, 'hr' => 3600, 'min' => 60, 'sec' => 1];
	$diff = abs($from - $to);
	$suffix = ($from > $to) ? 'from now' : 'ago';
	foreach($units as $unit => $mult) {
		if($diff >= $mult) {
			return intval($diff / $mult) . ' ' . $unit
				. ((intval($diff / $mult) == 1) ? '' : 's') . ' ' . $suffix;
		}
	}
	if($diff === 0)
		$diff = '0 sec ago';
	return $diff;
}
