<?php
define('AMI_DEBUG_LOG', 'log.txt');

class AMI {
public $aslver = '2.0/unknown';

function connect($ip, $port) {
	if(!validIpAddr($ip) || !$port)
		return false;
	return fsockopen($ip, $port, $errno, $errstr, 5);
}

function login($fp, $user, $password) {
	$actionID = $user . $password;
	fwrite($fp,"ACTION: LOGIN\r\nUSERNAME: $user\r\nSECRET: $password\r\nEVENTS: 0\r\nActionID: $actionID\r\n\r\n");
	$res = $this->getResponse($fp, $actionID);
	// logToFile('RES: ' . varDumpClean($res, true), AMI_DEBUG_LOG);
	$ok = (strpos($res[2], "Authentication accepted") !== false);
	if(!$ok)
		return false;
	// Determine App-rpt version. ASL3 and Asterisk 20 have some differences in AMI commands
	// eg. in ASL2 restart command is "restart now" but in ASL3 it's "core restart now".
	$s = $this->command($fp, 'rpt show version');
	if(preg_match('/app_rpt version: ([0-9\.]{1,9})/', $s, $m) == 1)
		$this->aslver = $m[1];
	return $ok;
}

function command($fp, $cmdString, $debug=false) {
	// Generate ActionID to associate with response
	$actionID = 'cpAction_' . mt_rand();
	$ok = true;
	$msg = [];
	if((fwrite($fp, "ACTION: COMMAND\r\nCOMMAND: $cmdString\r\nActionID: $actionID\r\n\r\n")) > 0) {
		if($debug)
			logToFile('CMD: ' . $cmdString . ' - ' . $actionID, AMI_DEBUG_LOG);
		$res = $this->getResponse($fp, $actionID, $debug);
		if(!is_array($res))
			return $res;
		// Check for Asterisk AMI Success/Error response
		foreach($res as $r) {
			if($r === 'Response: Error')
				$ok = false;
			elseif(preg_match('/Output: (.*)/', $r, $m) == 1)
				$msg[] = $m[1];
		}
		if(_count($msg))
			return implode(NL, $msg);
		if($ok)
			return 'OK';
		return 'ERROR';
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
	$ignore = ['Privilege: Command', 'Command output follows'];
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
				if($str === '' || in_array($str, $ignore))
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
