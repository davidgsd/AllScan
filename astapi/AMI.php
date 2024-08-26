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
	$res = $this->getResponse($fp, $actionID);
	// logToFile('RES: ' . varDumpClean($res, true), AMI_DEBUG_LOG);
	return (strpos($res[2], "Authentication accepted") !== false);
}

function command($fp, $cmdString, $debug=false) {
	// Generate ActionID to associate with response
	$actionID = 'cpAction_' . mt_rand();
	if((fwrite($fp, "ACTION: COMMAND\r\nCOMMAND: $cmdString\r\nActionID: $actionID\r\n\r\n")) > 0) {
		if($debug)
			logToFile('CMD: ' . $cmdString . ' - ' . $actionID, AMI_DEBUG_LOG);
		$res = $this->getResponse($fp, $actionID, $debug);
		if(!is_array($res))
			return $res;
		if($debug)
			logToFile('RES: ' . varDumpClean($res, true), AMI_DEBUG_LOG);
		// Check for Asterisk AMI Success response
		if(strpos($res[0], 'Response: ') === 0)
			return 'OK';
		// else return text received
		return $res[0];
	}
	return "Get node $cmdString failed";
}

/* 	Example ASL2 AMI response: 
		Response: Follows
		Privilege: Command
		ActionID: cpAction_...
		--END COMMAND--
	Example ASL3 AMI response:
		Response: Success
		Command output follows
		Output:
		ActionID: cpAction_...
	=> "Response:" line indicates success of associated ActionID.
*/

function getResponse($fp, $actionID, $debug=false) {
	$ignore = ['Privilege: Command', 'Command output follows', 'Output:'];
	$t0 = time();
	$response = [];
	if($debug)
		$sn = getScriptName();
	while(time() - $t0 < 20) {
		$str = fgets($fp);
		if($str === false)
			return $response;
		$str = trim($str);
		if($str === '')
			continue;
		if($debug)
			logToFile("$sn 1: $str", AMI_DEBUG_LOG);
		if(strpos($str, 'Response: ') === 0) {
			$response[] = $str;
		} elseif($str === "ActionID: $actionID") {
			$response[] = $str;
			while(time() - $t0 < 20) {
				$str = fgets($fp);
				if($str === "\r\n" || $str[0] === "\n" || $str === false)
					return $response;
				$str = trim($str);
				if($str === '' || strpos($str, $ignore) === 0)
					continue;
				$response[] = $str;
				if($debug)
					logToFile("$sn 2: $str", AMI_DEBUG_LOG);
			}
		}
	}
	if(count($response))
		return $response;
	logToFile("$sn: Timeout", AMI_DEBUG_LOG);
	return 'Timeout';
}

}
