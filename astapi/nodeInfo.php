<?php

function getAstInfo($fp, $nodeNum) {
	global $astdb;
	// Build info string
	if(isset($astdb[$nodeNum])) {
		$dbNode = $astdb[$nodeNum];
		$info = $dbNode[1] . ' ' . $dbNode[2] . ' ' . $dbNode[3];
	} elseif($nodeNum > 3000000) {
		// EchoLink and IRLP stuff added by Paul Aidukas, KN2R
		$info = echolink_cache_lookup($fp, $nodeNum);
	} elseif($nodeNum > 80000) {
		$info = irlp_cache_lookup($nodeNum);
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

$irlp_cache = [];
$elnk_cache = [];

function irlp_cache_lookup($irlpnode) {
	global $irlp_cache, $IRLP_CALLS, $ZCAT, $AWK;
	$lookup = (int)substr("$irlpnode", 1); // Strip leading 8 and 0's
	$lookup2 = $lookup;
	$lookup3 = substr($lookup2, 3, 1);
 	if(isset($irlp_cache[$lookup])) {
		$column = $irlp_cache[$lookup];
		$time = time();
		if($time > $column[0])
			unset($irlp_cache[$lookup]);
		$info = $column[1] . " [IRLP $lookup] " . $column[2];
		return $info;
	} else {
		if($lookup >= 9000 && $lookup3 > 0) {
			$lookup = substr_replace($lookup, 0, 3);
		}
		$res = `$ZCAT $IRLP_CALLS | $AWK '-F|' 'BEGIN{IGNORECASE=1} $1 ~ /$lookup/ {printf ("%s\x18", $0);}'`;
		$table = explode("\x18", $res);
		array_pop($table);
		foreach($table as $row) {
			$column = explode ("|", $row);
			$node = trim($column[0]);
			$call = trim($column[1]);
			$qth = trim ($column[2] . ", " . $column[3] . " " . $column[4]);
			$str = $column[0];
			$column = [];
			if($node == $lookup) {
				$column[0] = time() + 600;
				$column[1] = $call;
				$column[2] = $qth;
				$irlp_cache[$lookup] = $column;
				if($lookup >= 9000 && $lookup3 > 0) {
					$column[1] = "REF" . "$lookup2";
				}
				$info = $column[1] . " [IRLP $lookup2] " . $column[2];
				return ($info);
			} else {
				$column[0] = time() + 30;
				$column[1] = "No info";
				$column[2] = "No info";
				$irlp_cache[$lookup] = $column;
				return $info;
			}
		}
	}
}

function echolink_cache_lookup($fp, $echonode) {
	global $elnk_cache, $ami;
	$lookup = (int)substr($echonode, 1);  // Strips off the leading "3" and leading zeros.
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
			$column[1] = "No info";
			$column[2] = "No info";
			$elnk_cache[$lookup] = $column;
			$info = $column[1] . " [EchoLink $lookup] (" . $column[2] . ")";
			return $info;
		}
	}
}
