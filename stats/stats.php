<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
require_once('../include/common.php');
// TBI: Authenticate user

// Filter and validate user input
if(!isset($_POST['nodes']) || !$_POST['nodes']) {
	sendData(['status' => 'No nodes specified']);
	exit();
}
$nodes = trim(strip_tags($_POST['nodes']));
$nodes = explode(',', $nodes);
foreach($nodes as $n) {
	if(!validDbID($n)) {
		sendData(['status' => "Invalid node '$n' specified in nodes list"]);
	}
}
$cnt = count($nodes);

sendData(['status' => "$cnt valid nodes in request"], 'stats');

exit();

function sendData($data, $event='errMsg') {
	$resp = ['event' => $event, 'data' => $data];
	echo json_encode($resp);
	ob_flush();
	flush();
}
