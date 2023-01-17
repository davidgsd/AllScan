<?php
// AllScan API Controller - Handles requests from AllScan JavaScript functions
// No need to Authenticate user yet, this API currently returns only CPU temp
//require_once('../include/apiInit.php');

error_reporting(E_ERROR | E_WARNING | E_PARSE);
require_once("../include/common.php");

require_once("../include/hwUtils.php");
// Supports the following API functions:
// f=getCpuTemp : returns color-coded CPU Temp string

// Filter and validate user input
$parms = getRequestParms();
if(!isset($parms['f']))
	exit();

$msg = [];
processForm($parms['f'], $msg);

exit();

// ---------------------------------------------------
function processForm($f, &$msg) {
	switch($f) {
		case GET_CPU_TEMP:
			sendData(['status' => true, 'data' => cpuTemp()], $f);
			break;
	}
}

function sendData($data, $event='errMsg') {
	$resp = ['event' => $event, 'data' => $data];
	echo json_encode($resp);
	ob_flush();
	flush();
}
