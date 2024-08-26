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
		foreach($res as $i => &$r) {
			$r = trim($r);
			if(!$r)
				unset($res[$i]);
		}
		unset($r);
		if($debug)
			logToFile('RES: ' . varDumpClean($res, true), AMI_DEBUG_LOG);
		// Check for Asterisk AMI Success response (ASL3)
		if(isset($res[0]) && $res[0] === 'Response: Success')
			return 'OK';
		// Check for old Asterisk AMI OK response (ASL2/HV)
		if(isset($res[1]) && strpos($res[1], '--END COMMAND--') !== false)
			return 'OK';
		// else return text received or Error if none
		if(isset($res[1]))
			return $res[1];
		if(isset($res[0]))
			return $res[0];
		return 'Error';
	}
	return "Get node $cmdString failed";
}

function getResponse($fp, $actionID, $debug=false) {
	$t0 = time();
	$response = '';
	if($debug)
		$sn = getScriptName();
	while(time() - $t0 < 20) {
		$str = fgets($fp);
		if($str === false)
			return $response;
		if($debug)
			logToFile("$sn 1: $str", AMI_DEBUG_LOG);
		// ASL3: new Asterisk sends Success response before ActionID
		if(strpos($str, 'Response: ') === 0) {
			$response .= $str;
		}
		// Look for ActionID set in command()
		elseif(trim($str) === "ActionID: $actionID") {
			$response .= $str;
			while(time() - $t0 < 20) {
				$str = fgets($fp);
				if($str === "\r\n" || $str[0] === "\n" || $str === false)
					return $response;
				// Filter extra messages returned from ASL3 AMI
				if(strpos($str, 'Command output follows') !== false)
					continue;
				if(strpos($str, 'Output:') !== false)
					continue;
				$response .= $str;
				if($debug)
					logToFile("$sn 2: $str", AMI_DEBUG_LOG);
			}
		}
	}
	return $response ? $response : 'Timeout';
}

}
