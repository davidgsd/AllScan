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
$stats = [];
$time = time();

// For now only one node will be requested at a time
foreach($nodes as $n) {
	if($n < 2000 || $n >= 2000000) {
		sendData(['status' => "Node $n not a public node"], 'stats');
		continue;
	}
	$error = '';
	$retcode = 0;
	$url = "http://stats.allstarlink.org/api/stats/$n";
	$data = doWebRequest($url, null, $error, $retcode);
	if(!$data) {
		sendData(['status' => "ASL stats $error", 'node' => $n, 'retcode' => $retcode], 'stats');
		break;
	}
	$resp = json_decode($data);
	$s = parseStats($resp, $time);
	if(!isset($s->node)) {
		$s->node = $n;
		$s->active = $s->keyed = 0;
		$s->busyPct = $s->linkCnt = $s->txtime = '-';
	}
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

function doWebRequest($url, $parms=null, &$error, &$retcode) {
	//$httpCodes = [400 => 'Timeout', 403 => 'Forbidden', 404 => 'Error', 429 => 'Too Many Requests'];
	if(function_exists('curl_init')) {
		$ch = curl_init();
		$timeout = 10;
		$def = [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => $timeout,
			CURLOPT_TIMEOUT => $timeout,
		];
		curl_setopt_array($ch, $def);
		if($parms !== null) {
			curl_setopt($ch, CURLOPT_POST, true);
			$p = [];
			foreach($parms as $k=>$v)
				$p[] = "$k=$v";
			curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $p));
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
		$retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
	} else {
		ini_set('default_socket_timeout', 10);
		$result = @file_get_contents($url);
		preg_match('/([0-9])\d+/', $http_response_header[0], $matches);
		$retcode = intval($matches[0]);
	}
	if($retcode != 200) {
		//$txt = array_key_exists($rc, $httpCodes) ? "$rc ($httpCodes[$rc])" : $rc;
		$error = "HTTP return code $retcode";
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
