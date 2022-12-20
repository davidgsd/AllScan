<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
require_once('../include/common.php');
// TBI: Authenticate user

// Filter and validate user input
if((!isset($_POST['node']) || !$_POST['node']) && (!isset($_POST['nodes']) || !$_POST['nodes'])) {
	sendData(['status' => 'No node(s) specified']);
	exit();
}
if(isset($_POST['node']) && $_POST['node']) {
	$node = trim(strip_tags($_POST['node']));
	$nodes = [$node];
} else {
	$nodes = trim(strip_tags($_POST['nodes']));
	$nodes = explode(',', $nodes);
}
foreach($nodes as $n) {
	if(!validDbID($n)) {
		sendData(['status' => "Invalid node '$n' specified in nodes list"]);
	}
}
$cnt = count($nodes);

//sendData(['status' => "$cnt valid nodes in request"], 'stats');
//exit();

$stats = [];
$time = time();

// For now only one node will be requested at a time
foreach($nodes as $n) {
	if($n < 2000 || $n >= 2000000) {
		sendData(['status' => "Node $n not a public node"], 'stats');
		continue;
	}
	$error = '';
	$url = "http://stats.allstarlink.org/api/stats/$n";
	if(function_exists('curl_init')) {
		$data = doWebRequest($url, null, $error);
	} else {
		$data = file_get_contents($url);
	}
	if(!$data) {
		sendData(['status' => "ASL API response failed for Node $n. Error=$error"], 'stats');
		break;
	}
	$resp = json_decode($data);
	$s = parseStats($resp, $time);
	$time = $s->timeAgo;
	// Data structure: event=stats; status=LogMsg; stats=statsStruct
	$msg = "$n: Tx=$s->keyed Act=$s->active $s->timeAgo LCnt=$s->linkCnt Rx%=$s->busyPct TxTm=$s->txtime WT=$s->wt";
	sendData(['status' => $msg, 'stats' => $s], 'stats');
}

exit();

function parseStats($resp, $time) {
	$s = new stdClass();
	if(isset($resp->stats)) {
		$stats = $resp->stats;
		$s->node = $stats->node;
		$data = $stats->data;
		$s->uptime = $data->apprptuptime;
		$s->keyups = $data->totalkeyups;
		$s->txtime = $data->totaltxtime;
		$s->busyPct = ($s->uptime > 60 && $s->txtime > 10) ? round(100 * $s->txtime / $s->uptime) : '-';
		$s->linkCnt = _count($data->links);
		$s->keyed = $data->keyed ? '1' : '0';
		$s->time = $data->time;
		$s->timeAgo = max($time - $data->time, 0);
		unset($data->linkedNodes);
		$s->updated = strtotime($stats->updated_at);
		unset($stats->user_node);
	}
	if(isset($resp->node)) {
		$node = $resp->node;
		$s->regtime = $node->regseconds;
		$s->status = $node->Status;
		$s->active = ($s->status === 'Active') ? '1' : '0';
		$s->wt = $node->access_webtransceiver;
	}
	return $s;
}

function doWebRequest($url, $parms=null, &$error) {
	$ch = curl_init();
	$timeout = 10;
	$def = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
    ];
    curl_setopt_array($ch, $def);
	curl_setopt($ch, CURLOPT_GET, true);
	if($parms !== null) {
		//curl_setopt($ch, CURLOPT_GET, true);
		/* $p = [];
		foreach($parms as $k=>$v)
			$p[] = "$k=$v";
		curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $p)); */
	}
	$result = curl_exec($ch);
	if(!$result) {
		$errno = curl_errno($ch);
		if($errno == 28) {
			$error = "Network response timeout";
		} else {
			$err = curl_error($ch);
			if($err)
				$error = "HTTP Error: $err";
			else
				$error = "Unknown HTTP error";
		}
		curl_close($ch);
		return null;
	}
	$intRetCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if($intRetCode != 200) {
		if($intRetCode == 400) {
			$error = "ASL HTTP return code 400 (Timeout).";
		} elseif($intRetCode == 403) {
			$error = "ASL HTTP return code 403.";
		} else {
			$rc = ($intRetCode == 404) ? '404 (Error)' : $intRetCode;
			$error = "ASL HTTP return code $rc";
		}
		/* if($intRetCode != 400) {
			p("Request URL: $url");
			if($parms) {
				msg("Parms:");
				varDumpClean($parms);
			}
		} */
		return null;
	}
	return $result;
}

function sendData($data, $event='errMsg') {
	$resp = ['event' => $event, 'data' => $data];
	echo json_encode($resp);
	ob_flush();
	flush();
}
