<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
date_default_timezone_set('America/New_York');

require_once('../include/apiInit.php');
require_once('AMI.php');
require_once('nodeInfo.php');

if(!readOk())
	die("Insufficient user permission to retrieve data\n");

// Validate request
if(empty($_GET['nodes'])) {
	sendData(['status' => 'Unknown request!']);
	exit();
}

// Read parms
$passedNodes = explode(',', trim(strip_tags($_GET['nodes'])));

// Load allmon.ini
$cfg = readAllmonCfg();
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

// Open a socket to each Asterisk Manager
$ami = new AMI();
$servers = [];
$fp = [];

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
			if($ami->login($fp[$host], $cfg[$node]['user'], $cfg[$node]['passwd']) !== false) {
				$servers[$host] = 'y';
				$data['status'] .= 'Login OK';
			} else {
				$data['status'] .= "Login Failed. Check allmon.ini settings.";
			}
		}
		sendData($data, 'connection');
	}
}

// Main loop - build $data array and output as a json object
$current = [];
$saved = [];
$nodeTime = [];
$ticToc = '';
while(1) {
	foreach($nodes as $node) {
		// Is host of this node logged in?
		if(!isset($servers[$cfg[$node]['host']]))
			continue;
		$connectedNodes = getNode($fp[$cfg[$node]['host']], $node);
		$sortedConnectedNodes = sortNodes($connectedNodes);
		// Build array of time values
		$nodeTime[$node]['node'] = $node;
		$nodeTime[$node]['info'] = getAstInfo($fp[$cfg[$node]['host']], $node);
		// Build array
		$current[$node]['node'] = $node;
		$current[$node]['info'] = getAstInfo($fp[$cfg[$node]['host']], $node);
		// Save remote nodes
		$current[$node]['remote_nodes'] = [];
		$i = 0;
		foreach($sortedConnectedNodes as $remoteNode => $arr) {
			// Store remote nodes time values
			$nodeTime[$node]['remote_nodes'][$i]['elapsed'] = $arr['elapsed'];
			$nodeTime[$node]['remote_nodes'][$i]['last_keyed'] = $arr['last_keyed'];
			// Store remote nodes other than time values
			// Array key of remote_nodes is not node number to prevent javascript (for in) sorting
			$current[$node]['remote_nodes'][$i]['node'] = $arr['node'];
			$current[$node]['remote_nodes'][$i]['info'] = $arr['info'];
			$current[$node]['remote_nodes'][$i]['link'] = ucwords(strtolower($arr['link']));
			$current[$node]['remote_nodes'][$i]['ip'] = $arr['ip'];
			$current[$node]['remote_nodes'][$i]['direction'] = $arr['direction'];
			$current[$node]['remote_nodes'][$i]['keyed'] = $arr['keyed'];
			$current[$node]['remote_nodes'][$i]['mode'] = $arr['mode'];
			$current[$node]['remote_nodes'][$i]['elapsed'] = '&nbsp;';
			$current[$node]['remote_nodes'][$i]['last_keyed'] = $arr['last_keyed'] === 'Never' ? 'Never' : NBSP;
			$current[$node]['remote_nodes'][$i]['cos_keyed'] = $arr['cos_keyed'];
			$current[$node]['remote_nodes'][$i]['info'] = $arr['info'];
			$current[$node]['remote_nodes'][$i]['node'] = $arr['node'];
			$current[$node]['remote_nodes'][$i]['tx_keyed'] = $arr['tx_keyed'];
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
	if(isset($SMLOOPDELAY) && ($SMLOOPDELAY > 299999 && $SMLOOPDELAY < 30000001)) {
		usleep($SMLOOPDELAY);
	} else {
		usleep(500000); // Wait Default 0.5 seconds
	}
}

fwrite($fp, "ACTION: Logoff\r\n\r\n");

exit();

// Get status for this $node
function getNode($fp, $node) {
	global $ami;
	$actionRand = mt_rand(); // Asterisk Manger Interface an actionID so we can find our own response
	$actionID = 'xstat' . $actionRand;
	if(fwrite($fp, "ACTION: RptStatus\r\nCOMMAND: XStat\r\nNODE: $node\r\nActionID: $actionID\r\n\r\n") !== false) {
		// Get RptStatus
		$rptStatus = $ami->getResponse($fp, $actionID);
	} else {
		sendData(['status'=>'XStat failed!']);
	}
	// format of Conn lines: Node# isKeyed lastKeySecAgo lastUnkeySecAgo
	$actionID = 'sawstat' . $actionRand;
	if(fwrite($fp, "ACTION: RptStatus\r\nCOMMAND: SawStat\r\nNODE: $node\r\nActionID: $actionID\r\n\r\n") !== false) {
		// Get RptStatus
		$sawStatus = $ami->getResponse($fp, $actionID);
	} else {
		sendData(['status'=>'sawStat failed!']);
	}
	// Parse this $node. Returns an array of currently connected nodes
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
	$never_heard = [];
	$sortedNodes = [];
	// Build arrays of heard and unheard
	foreach($nodes as $nodeNum => $row) {
		if($row['last_keyed'] == '-1') {
			$never_heard[$nodeNum] = 'Never heard';
		} else {
			$arr[$nodeNum] = $row['last_keyed'];
		}
	}
	// Sort nodes that have been heard
	if(count($arr) > 0) {
		asort($arr, SORT_NUMERIC);
	}
	// Add in nodes that have not been heard
	if(count($never_heard) > 0) {
		ksort($never_heard, SORT_NUMERIC);
		foreach($never_heard as $nodeNum => $row) {
			$arr[$nodeNum] = $row;
		}
	}
	// Build sorted node array
	foreach($arr as $nodeNum => $row) {
		// Build last_keyed string. Converts seconds to hours, minutes, seconds.
		if($nodes[$nodeNum]['last_keyed'] > -1) {
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
	$links = [];
	$conns = [];
	// Parse 'rptStat Conn:' lines
	$lines = explode("\n", $rptStatus);
	foreach ($lines as $line) {
		if(preg_match('/Conn: (.*)/', $line, $matches)) {
			$arr = preg_split("/\s+/", trim($matches[1]));
			if(is_numeric($arr[0]) && $arr[0] > 3000000) {
				// No IP w/EchoLink
				$conns[] = [$arr[0], "", $arr[1], $arr[2], $arr[3], $arr[4]];
			} else {
				$conns[] = $arr;
			}
		}
		if(preg_match('/Var: RPT_RXKEYED=./', $line, $matches)) {
			$rxKeyed = substr($matches[0], strpos($matches[0], "=") + 1);
		}
		if(preg_match('/Var: RPT_TXKEYED=./', $line, $matches)) {
			$txKeyed = substr($matches[0], strpos($matches[0], "=") + 1);
		}
	}
	// Parse 'sawStat Conn:' lines
	$keyups = [];
	$lines = explode("\n", $sawStatus);
	foreach($lines as $line) {
		if(preg_match('/Conn: (.*)/', $line, $matches)) {
			$arr = preg_split("/\s+/", trim($matches[1]));
			$keyups[$arr[0]] = ['node' => $arr[0], 'isKeyed' => $arr[1], 'keyed' => $arr[2], 'unkeyed' => $arr[3]];
		}
	}
	// Parse 'LinkedNodes:' line
	if(preg_match("/LinkedNodes: (.*)/", $rptStatus, $matches)) {
		$longRangeLinks = preg_split("/, /", trim($matches[1]));
	}
	foreach($longRangeLinks as $line) {
		$n = substr($line,1);
		$modes[$n]['mode'] = substr($line,0,1);
	}
	// Combine above arrays
	if(count($conns)) {
		// Local connects
		foreach($conns as $node) {
			$n = $node[0];
			$curNodes[$n]['node'] = $node[0];
			$curNodes[$n]['info'] = getAstInfo($fp, $node[0]);
			$curNodes[$n]['ip'] = $node[1];
			$curNodes[$n]['direction'] = $node[3];
			$curNodes[$n]['elapsed'] = $node[4];
			if(isset($node[5])) {
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
	return $curNodes;
}
