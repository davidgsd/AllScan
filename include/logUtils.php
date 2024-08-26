<?php
// Log functions
function p($msg, $class=null, $escapeTxt=true) {
	global $html;
	echo $html->p($msg, $class, $escapeTxt);
}
function h1($msg, $class=null, $escapeTxt=true) {
	global $html;
	echo $html->h(1, $msg, $class, $escapeTxt);
}
function h2($msg, $class=null, $escapeTxt=true) {
	global $html;
	echo $html->h(2, $msg, $class, $escapeTxt);
}
function h3($msg, $class=null, $escapeTxt=true) {
	global $html;
	echo $html->h(3, $msg, $class, $escapeTxt);
}
function h4($msg, $class=null, $escapeTxt=true) {
	global $html;
	echo $html->h(4, $msg, $class, $escapeTxt);
}
function pre($msg, $class=null, $escapeTxt=true, $style=null) {
	global $html;
	echo $html->pre($msg, $class, $escapeTxt, $style);
}
function br($n=1) {
	global $html;
	return $html->br($n);
}
function logToFile($msg, $file=null) {
	global $user;
	$msg = trim($msg);
	if(!$msg)
		return;
	if($file === null)
		$file = 'errLog.txt';
	if($msg === null)
		$msg = '[null]';
	elseif(is_object($msg) || is_array($msg))
		$msg = varDump($msg, true);
	if(isset($user->name))
		$name = $user->name;
	$tstamp = getCurTimestamp();
	file_put_contents($file, "$tstamp: $name: $msg\n", FILE_APPEND);
}
function varDumpClean($var, $return=false) {
	$var = varDump($var, true);
	$res = explode("\n", $var);
	foreach($res as $i => $r) {
		if(preg_match('/[0-9a-zA-Z]/', $r) != 1)
			unset($res[$i]);
	}
	$res = implode("\n", $res);
	$res = str_replace("\t\t", "\t", $res);
	if($return)
		return $res;
	echo $res;
}
function displayArray($a, $class='results') {
	global $html;
	$keys = array_keys($a);
	$out = $html->tableOpen(null, null, $class, null);
	foreach($keys as $key) {
		if(!is_array($a[$key])) {
			$val = $a[$key];
		} else {
			if(count($a[$key]))
				$val = displayArray($a[$key]);
			else
				$val = '[Empty]';
		}
		$out .= $html->tableRow(["<b>$key</b>", $val], null);
	}
	$out .= $html->tableClose();
	return $out;
}
