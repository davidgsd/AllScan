<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
date_default_timezone_set('America/New_York');

require_once('../include/apiInit.php');
require_once('AMI.php');
require_once('nodeInfo.php');

if(!readOk()) {
	sendData(['status' => 'Insufficient user permission to retrieve data.']);
	exit();
}

// Validate request
if(empty($_GET['nodes'])) {
	sendData(['status' => 'Unknown request!']);
	exit();
}

// Read parms
$passedNodes = explode(',', trim(strip_tags($_GET['nodes'])));

chdir('..');

// Load allmon.ini
$cfg = readNodeCfg();
if($cfg === false) {
	sendData(['status' => "allmon.ini not found."]);
	exit();
}

// Load ASL DB
$astdb = readAstDb2();

// Verify nodes are in ini file
$nodes = [];
foreach($passedNodes as $i => $node) {
	if(isset($cfg[$node])) {
		$nodes[] = $node;
	} else {
		sendData(['node'=>$node, 'status'=>"Node $node not in allmon.ini"], 'nodes');
	}
}

// Do not time out
set_time_limit(0);

// Open a socket to each Asterisk Manager
$ami = new AMI();
$servers = [];
$fp = [];
$chandriver = 'Unknown';
$amicd = '';
$rxstatssupported = false;

foreach($nodes as $node) {
	$host = $cfg[$node]['host'];
	if(!$host) {
		$data['status'] = "Invalid host setting in allmon.ini [$node]";
		sendData($data, 'connection');
		continue;
	}
	$data = ['host'=>$host, 'node'=>$node];
	// Connect and login to each manager only once
	if(!array_key_exists($host, $servers)) {
		$data['status'] = "Connecting to Asterisk Manager $node $host...";
		$fp[$host] = $ami->connect($host);
		if($fp[$host] === false) {
			$data['status'] .= 'Connect Failed. Check allmon.ini settings.';
		} else {
			// Try to login
			$amiuser = $cfg[$node]['user'];
			$pass = $cfg[$node]['passwd'];
			if($ami->login($fp[$host], $amiuser, $pass) !== false) {
				$servers[$host] = 'y';
				$data['status'] .= 'Login OK';
			} else {
				$data['status'] .= "Login Failed. Check allmon.ini settings.";
			}
			// Log version info
			$data['status'] .= "<br>ASL Ver: $ami->aslver, AllScan Ver: "
					. substr($AllScanVersion, 1);
			// Check if rxaudiostats supported
			$msg = checkRxStatsSupport($ami, $fp[$host]);
			if(_count($msg))
				$data['status'] .= BR . implode(BR, $msg);
		}
		sendData($data, 'connection');
	}
}

// Main loop - build $data array and output as a json object
$current = [];
$saved = [];
$nodeTime = [];
while(1) {
	foreach($nodes as $node) {
		// Is host of this node logged in?
		if(!isset($servers[$cfg[$node]['host']]))
			continue;
		$connectedNodes = getNode($fp[$cfg[$node]['host']], $node);
		$sortedConnectedNodes = sortNodes($connectedNodes);
		$info = getAstInfo($fp[$cfg[$node]['host']], $node);
		// Build array of time values
		$nodeTime[$node]['node'] = $node;
		$nodeTime[$node]['info'] = $info;
		// Build array
		$current[$node]['node'] = $node;
		$current[$node]['info'] = $info;
		// Save remote nodes
		$current[$node]['remote_nodes'] = [];
		$i = 0;
		foreach($sortedConnectedNodes as $arr) {
			// Store remote nodes time values
			$nodeTime[$node]['remote_nodes'][$i]['elapsed'] = $arr['elapsed'] ?? 0;
			$nodeTime[$node]['remote_nodes'][$i]['last_keyed'] = $arr['last_keyed'];
			// Store remote nodes other than time values
			// Array key of remote_nodes is not node number to prevent javascript (for in) sorting
			$current[$node]['remote_nodes'][$i]['node'] = $arr['node'] ?? '';
			$current[$node]['remote_nodes'][$i]['info'] = $arr['info'] ?? '';
			$current[$node]['remote_nodes'][$i]['link'] = ucwords(strtolower($arr['link'] ?? ''));
			$current[$node]['remote_nodes'][$i]['ip'] = $arr['ip'] ?? '';
			$current[$node]['remote_nodes'][$i]['direction'] = $arr['direction'] ?? '';
			$current[$node]['remote_nodes'][$i]['keyed'] = $arr['keyed'] ?? '';
			$current[$node]['remote_nodes'][$i]['mode'] = $arr['mode'] ?? '';
			$current[$node]['remote_nodes'][$i]['elapsed'] = '&nbsp;';
			$current[$node]['remote_nodes'][$i]['last_keyed'] = $arr['last_keyed'] === 'Never' ? 'Never' : NBSP;
			$current[$node]['remote_nodes'][$i]['cos_keyed'] = $arr['cos_keyed'] ?? 0;
			$current[$node]['remote_nodes'][$i]['tx_keyed'] = $arr['tx_keyed'] ?? 0;
			$current[$node]['remote_nodes'][$i]['lnodes'] = $arr['lnodes'] ?? [];
			$i++;
		}
	}
	// Send current nodes only when data changes
	if($current !== $saved) {
		sendData($current, 'nodes');
		$saved = $current;
	}
	// Send times every cycle
	sendData($nodeTime, 'nodetimes');
	// Wait 500mS
	usleep(500000);
}

fwrite($fp, "ACTION: Logoff\r\n\r\n");

exit();

function checkRxStatsSupport($ami, $fp) {
	global $chandriver, $amicd, $rxstatssupported;
	$res = $ami->command($fp, "susb tune menu-support Y");
	if(strpos($res, 'RxAudioStats') === 0) {
		$rxstatssupported = true;
		$chandriver = 'Simpleusb';
		$amicd = 'susb';
	}
	if(!$rxstatssupported) {
		$res = $ami->command($fp, "radio tune menu-support Y");
		if(strpos($res, 'RxAudioStats') === 0) {
			$rxstatssupported = true;
			$chandriver = 'Usbradio';
			$amicd = 'radio';
		}
	}
	if(!$rxstatssupported) {
		$msg[] = "ASL version does not support RxAudioStats";
	} else {
		$msg[] = "RxAudioStats supported, $chandriver driver";
		$res = $ami->command($fp, "$amicd show settings");
		if(preg_match('/Card is ([-0-9]{1,2})/', $res, $m) == 1 && $m[1] >= 0) {
			$msg[] = "Channel driver settings:";
			$ra = explode(NL, $res);
			foreach($ra as $m) {
				if(strposa($m, ['Output ', 'Rx ', 'Tx ']))
					$msg[] = $m;
			}
		}
	}
	return $msg;
}

// Get status for this $node
function getNode($fp, $node) {
	global $ami;
	static $errCnt=0;
	$actionRand = mt_rand(); // Asterisk Manger Interface an actionID so we can find our own response
	$actionID = 'xstat' . $actionRand;
	if(fwrite($fp, "ACTION: RptStatus\r\nCOMMAND: XStat\r\nNODE: $node\r\nActionID: $actionID\r\n\r\n") !== false) {
		$rptStatus = $ami->getResponse($fp, $actionID);
	} else {
		sendData(['status'=>'XStat failed!']);
		// On ASL3 if Asterisk restarts above error repeats indefinitely. Let client JS reinit connection.
		if(++$errCnt > 9)
			exit();
	}
	// format of Conn lines: Node# isKeyed lastKeySecAgo lastUnkeySecAgo
	$actionID = 'sawstat' . $actionRand;
	if(fwrite($fp, "ACTION: RptStatus\r\nCOMMAND: SawStat\r\nNODE: $node\r\nActionID: $actionID\r\n\r\n") !== false) {
		$sawStatus = $ami->getResponse($fp, $actionID);
	} else {
		sendData(['status'=>'sawStat failed!']);
		// On ASL3 if Asterisk restarts above error repeats indefinitely. Let client JS reinit connection.
		if(++$errCnt > 9)
			exit();
	}
	// Returns an array of currently connected nodes
	$current = parseNode($fp, $rptStatus, $sawStatus);
	return $current;
}

function sendData($data, $event='errMsg') {
	echo "event: $event\n";
	echo 'data: ' . json_encode($data) . "\n\n";
	ob_flush();
	flush();
}

function sortNodes($nodes) {
	$arr = [];
	$notHeard = [];
	$sortedNodes = [];
	// Build arrays of heard and unheard
	foreach($nodes as $nodeNum => $row) {
		if(!isset($row['last_keyed']) || $row['last_keyed'] == '-1') {
			$notHeard[$nodeNum] = 'Never heard';
		} else {
			$arr[$nodeNum] = $row['last_keyed'];
		}
	}
	// Sort nodes that have been heard
	if(count($arr) > 0) {
		asort($arr, SORT_NUMERIC);
	}
	// Add in nodes that have not been heard
	if(count($notHeard) > 0) {
		ksort($notHeard, SORT_NUMERIC);
		foreach($notHeard as $nodeNum => $row) {
			$arr[$nodeNum] = $row;
		}
	}
	// Build sorted node array
	foreach($arr as $nodeNum => $row) {
		// Build last_keyed string. Converts seconds to hours, minutes, seconds.
		if(isset($nodes[$nodeNum]['last_keyed']) && $nodes[$nodeNum]['last_keyed'] > -1) {
			$t = $nodes[$nodeNum]['last_keyed'];
			$h = floor($t / 3600);
			$m = floor(($t / 60) % 60);
			$s = $t % 60;
			$nodes[$nodeNum]['last_keyed'] = sprintf("%02d:%02d:%02d", $h, $m, $s);
		} else {
			$nodes[$nodeNum]['last_keyed'] = 'Never';
		}
		$sortedNodes[$nodeNum] = $nodes[$nodeNum];
	}
	return $sortedNodes;
}

function parseNode($fp, $rptStatus, $sawStatus) {
	$curNodes = [];
	$conns = []; // Directly connected nodes
	$lnodes = []; // All connected nodes
	$modes = [];
	// Parse 'rptStat Conn:' lines
	foreach($rptStatus as $line) {
		if(preg_match('/Conn: (.*)/', $line, $matches)) {
			$arr = preg_split("/\s+/", trim($matches[1]));
			if(is_numeric($arr[0]) && $arr[0] > 3000000) {
				// No IP w/EchoLink
				$conns[] = [$arr[0], "", $arr[1], $arr[2], $arr[3], $arr[4]];
			} else {
				$conns[] = $arr;
			}
		}
		if(preg_match('/Var: RPT_RXKEYED=(.)/', $line, $matches)) {
			$rxKeyed = $matches[1];
		}
		if(preg_match('/Var: RPT_TXKEYED=(.)/', $line, $matches)) {
			$txKeyed = $matches[1];
		}
		if(preg_match("/LinkedNodes: (.*)/", $line, $matches)) {
			$longRangeLinks = preg_split("/, /", trim($matches[1]));
			foreach($longRangeLinks as $line) {
				$n = substr($line, 1);
				$modes[$n]['mode'] = substr($line, 0, 1);
				if(is_numeric($n) && $n >= 2000 && $n < 1000000)
					$lnodes[] = $n;
			}
		}
	}
	// Parse 'sawStat Conn:' lines
	$keyups = [];
	foreach($sawStatus as $line) {
		if(preg_match('/Conn: (.*)/', $line, $matches)) {
			$arr = preg_split("/\s+/", trim($matches[1]));
			$keyups[$arr[0]] = ['node' => $arr[0], 'isKeyed' => $arr[1], 'keyed' => $arr[2], 'unkeyed' => $arr[3]];
		}
	}
	// Combine above arrays
	if(count($conns)) {
		// Local connects
		foreach($conns as $node) {
			$n = $node[0];
			$curNodes[$n]['node'] = $node[0];
			$curNodes[$n]['info'] = getAstInfo($fp, $node[0]);
			$curNodes[$n]['ip'] = $node[1];
			if(isset($node[5])) {
				$curNodes[$n]['direction'] = $node[3];
				$curNodes[$n]['elapsed'] = $node[4];
				$curNodes[$n]['link'] = $node[5];
			} else {
				$curNodes[$n]['direction'] = $node[2];
				$curNodes[$n]['elapsed'] = $node[3];
				if(isset($modes[$n]['mode']))
					$curNodes[$n]['link'] = ($modes[$n]['mode'] === 'C') ? "Connecting" : "Established";
			}
			$curNodes[$n]['keyed'] = 'N/A';
			$curNodes[$n]['last_keyed'] = 'N/A';
			$curNodes[$n]['mode'] = isset($modes[$n]) ? $modes[$n]['mode'] : 'Local Monitor';
			$n++;
		}
		// Pull in keyed
		foreach($keyups as $node => $arr) {
			$curNodes[$node]['keyed'] = $arr['isKeyed'] ? 'yes' : 'no';
			$curNodes[$node]['last_keyed'] = $arr['keyed'];
		}
		$curNodes[1]['node'] = 1;
	} else {
		$curNodes[1]['info'] = "NO CONNECTION";
	}
	$curNodes[1]['cos_keyed'] = ($rxKeyed === "1") ? 1 : 0;
	$curNodes[1]['tx_keyed'] = ($txKeyed === "1") ? 1 : 0;
	// Add list of all connected nodes
	$curNodes[1]['lnodes'] = $lnodes;
	return $curNodes;
}
