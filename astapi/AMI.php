<?php

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
	return (preg_match("/Authentication accepted/", $login) == 1);
}

function command($fp, $cmdString) {
	// Generate ActionID to associate with response
	$actionID = 'cpAction_' . mt_rand();
	if((fwrite($fp, "ACTION: COMMAND\r\nCOMMAND: $cmdString\r\nActionID: $actionID\r\n\r\n")) > 0) {
    	$rptStatus = $this->getResponse($fp, $actionID);
		$res = explode("\r\n", $rptStatus);
    	return (strpos($res[1], '--END COMMAND--') !== false) ? 'OK' : $res[1];
	}
    return "Get node $cmdString failed";
}

function getResponse($fp, $actionID) {
    while(1) {
		$str = fgets($fp);
		// Look for ActionID set in command()
		if(trim($str) === "ActionID: $actionID") {
			$response = $str;
			while(1) {
				$str = fgets($fp);
                if($str === "\r\n")
		            return $response;
				$response .= $str;
			}
		}
    }
}

}
