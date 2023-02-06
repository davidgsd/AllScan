<?php

function getAstInfo($fp, $nodeNum) {
	global $astdb;
	// Build info string
	if(isset($astdb[$nodeNum])) {
		$dbNode = $astdb[$nodeNum];
		$info = $dbNode[1] . ' ' . $dbNode[2] . ' ' . $dbNode[3];
		// Link to ASL stats page
		$info = "<a href=\"http://stats.allstarlink.org/stats/$nodeNum\" target=\"stats\">$info</a>";
	} elseif($nodeNum > 3000000) {
		// EchoLink lookup function by KN2R
		$info = echolink_cache_lookup($fp, $nodeNum);
	} elseif(!empty($node['ip'])) {
		if(strlen(trim($node['ip'])) > 3) {
			$info = 'Web Txcvr / Phone Portal (' . $node['ip'] . ')';
		} else {
			$info = 'Unknown Mode';
		}
	} elseif(is_numeric($nodeNum)) {
		$info = 'Node not in database';
	} elseif(`echo $nodeNum |egrep -c "\-P"` > 0) {
		$info = 'AllStar Phone Portal user';
	} else {
		$info = 'IaxRpt / Web Transceiver client';
	}
	return $info;
}

$elnk_cache = [];

function echolink_cache_lookup($fp, $echonode) {
	global $elnk_cache, $ami;
	$lookup = (int)substr($echonode, 1); // Strips off the leading "3" and leading zeros
	if(isset($elnk_cache[$lookup])) {
		$column = $elnk_cache[$lookup];
		$time = time();
		if($time > $column[0])
			unset ($elnk_cache[$lookup]);
		$info = $column[1] . " [EchoLink $lookup] (" . $column[2] . ")";
		return $info;
	} else {
		$AMI = $ami->command($fp, "echolink dbget nodename $lookup"); // Get EchoLink data from Asterisk
		$rows = explode ("\n", $AMI);
		$column = explode ("|", $rows[0]);
		$str = $column[0];
		if($column[0] == $lookup) {
			$column[0] = time() + 300;
			$elnk_cache[$lookup] = $column;
			$info = $column[1] . " [EchoLink $lookup] (" . $column[2] . ")";
			return $info;
		} else {
			$column = [];
			$column[0] = time() + 30;
			$column[1] = $column[2] = "No info";
			$elnk_cache[$lookup] = $column;
			$info = $column[1] . " [EchoLink $lookup] (" . $column[2] . ")";
			return $info;
		}
	}
}
