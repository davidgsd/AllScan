<?php
define('AMI_DEBUG_LOG', 'log.txt');

class AMI {

function connect($host) {
	// Set default port if not provided
	$arr = explode(':', $host);
	$ip = $arr[0];
	$port = isset($arr[1]) ? $arr[1] : 5038;
	return fsockopen($ip, $port, $errno, $errstr, 5);
}

function login($fp, $user, $password) {
	$actionID = $user . $password;
	fwrite($fp,"ACTION: LOGIN\r\nUSERNAME: $user\r\nSECRET: $password\r\nEVENTS: 0\r\nActionID: $actionID\r\n\r\n");
	$login = $this->getResponse($fp, $actionID);
	return (strpos($login, "Authentication accepted") !== false);
}

function command($fp, $cmdString, $debug=false) {
	// Generate ActionID to associate with response
	$actionID = 'cpAction_' . mt_rand();
	if((fwrite($fp, "ACTION: COMMAND\r\nCOMMAND: $cmdString\r\nActionID: $actionID\r\n\r\n")) > 0) {
		if($debug)
			logToFile('CMD: ' . $cmdString . ' - ' . $actionID, AMI_DEBUG_LOG);
		$rptStatus = $this->getResponse($fp, $actionID, $debug);
		// Some AMI response line endings have just a NL and no CR
		$res = explode("\n", $rptStatus);
		array_walk($res, 'trim');
		if($debug)
			logToFile('RESP: ' . varDumpClean($res, true), AMI_DEBUG_LOG);
		return (strpos($res[1], '--END COMMAND--') !== false) ? 'OK' : $res[1];
	}
	return "Get node $cmdString failed";
}

function getResponse($fp, $actionID, $debug=false) {
	$t0 = time();
	$response = '';
	while(time() - $t0 < 20) {
		$str = fgets($fp);
		if($str === false)
			return $response;
		if($debug)
			logToFile('1: ' . $str, AMI_DEBUG_LOG);
		// Look for ActionID set in command()
		if(trim($str) === "ActionID: $actionID") {
			$response = $str;
			while(time() - $t0 < 20) {
				$str = fgets($fp);
				if($str === "\r\n" || $str[0] === "\n" || $str === false)
					return $response;
				$response .= $str;
				if($debug)
					logToFile('2: ' . $str, AMI_DEBUG_LOG);
			}
		}
	}
	return $response ? $response : 'Timeout';
}

}
