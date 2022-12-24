<?php
$AllScanVersion = "v0.38";
require_once('Html.php');
require_once('logUtils.php');

function msg($txt, $class=null) {
	global $html;
	if(isset($html)) {
		if($class)
			$txt = $html->span($txt, $class);
		$txt .= BR;
	}
	echo $txt . NL;
}

// Get nodes list and host IP(s)
function readAllmonIni(&$msg, &$hosts) {
	// Check for file in our directory and if not found look in the asterisk, supermon and allmon2 dirs
	$file = ['allmon.ini', '/etc/asterisk/allmon.ini.php', '../supermon/allmon.ini', '../allmon2/allmon.ini.php'];
	foreach($file as $f) {
		if(file_exists($f)) {
			$cfg = parse_ini_file($f, true);
			if($cfg === false) {
				$msg[] = "Error parsing $f";
			} else {
				$nodes = [];
				foreach($cfg as $n => $c) {
					if(validDbId($n) && isset($c['host']) && $c['host']) {
						$nodes[] = $n;
						$hosts[] = $c['host'];
					}
				}
				if(empty($nodes) || empty($hosts)) {
					$msg[] = "No valid node found in $f";
					$msg[] = varDumpClean($cfg, true);
				} else {
					$msg[] = "Node $nodes[0] $hosts[0] read from $f";
					if(count($nodes) > 1)
						$msg[] = "(More than one node is defined in $f, AllScan currently uses only the first.)";
					return $nodes;
				}
			}
		}
	}
	$cwd = getcwd();
	$msg[] = "No valid [node#] and host definitions found. Check that you have AllMon2 or Supermon installed, "
		.	"or place an allmon.ini file in $cwd/ containing a [YourNode#] line followed by a "
		.	"host=[NodeIPAddr:Port] line (eg. \"host=127.0.0.1:5038\").";
	return false;
}

// Below called by astapi files, which should only happen if controller file eg. index.php already called
// readAllmonIni() above which confirms there is a valid file available
function readAllmonCfg() {
	// Check for file in our directory and if not found look in the asterisk, supermon and allmon2 dirs
	$file = ['../allmon.ini', '/etc/asterisk/allmon.ini.php', '../../supermon/allmon.ini', '../../allmon2/allmon.ini.php'];
	foreach($file as $f) {
		if(file_exists($f)) {
			$cfg = parse_ini_file($f, true);
			if($cfg === false)
				continue;
			return $cfg;
		}
	}
	return false;
}

// Read AstDB file, looking in all commonly used locations
function readAstDb(&$msg) {
	// Check for file in our directory and in the allmon/supermon locations
	// If exists in more than one place use the newest. Download it if not found
	$file = ['astdb.txt', '../supermon/astdb.txt', '/var/log/asterisk/astdb.txt'];
	$mtime = [0, 0, 0];
	foreach($file as $i => $f) {
		if(file_exists($f)) {
			$mtime[$i] = filemtime($f);
			$msg[] = "$f last updated " . date('Y-m-d', $mtime[$i]);
		}
	}
	arsort($mtime, SORT_NUMERIC);
	if(!reset($mtime)) {
		$msg[] = "No astdb.txt file found. Check that you have AllMon2 or Supermon properly installed, "
			.	"and a cron job or other mechanism set up to periodically update the file.";
		if(!downloadAstDb($msg))
			return false;
		$file = 'astdb.txt';
	} else {
		$keys = array_keys($mtime);
		$file = $file[$keys[0]];
	}
	$msg[] = "Reading $file...";
	$rows = readFileLines($file, $msg);
	if(!$rows) {
		return false;
	}
	foreach($rows as $row) {
		$arr = explode('|', trim($row));
		$astdb[$arr[0]] = $arr;
	}
	unset($rows);
	$cnt = count($astdb);
	if(!$cnt) {
		$msg[] = "$file invalid. Check that you have AllMon2 or Supermon properly installed, "
			.	"and a cron job or other mechanism set up to periodically update the file.";
		return false;
	}
	$msg[] = "$cnt Nodes in ASL DB";
	return $astdb;
}

// Below called by astapi files, which should only happen if controller file eg. index.php already called
// readAllmonIni() above which confirms there is a valid file available
function readAstDb2() {
	// Check for file in our directory and if not found look in the asterisk, supermon and allmon2 dirs
	// If it exists in more than one place use the newest
	$file = ['../astdb.txt', '../../supermon/astdb.txt', '/var/log/asterisk/astdb.txt'];
	$mtime = [0, 0, 0];
	foreach($file as $i => $f) {
		if(file_exists($f)) {
			$mtime[$i] = filemtime($f);
		}
	}
	arsort($mtime, SORT_NUMERIC);
	if(!reset($mtime)) {
		return false;
	}
	$keys = array_keys($mtime);
	$file = $file[$keys[0]];
	$rows = readFileLines($file, $msg);
	if(!$rows) {
		return false;
	}
	foreach($rows as $row) {
		$arr = explode('|', trim($row));
		$astdb[$arr[0]] = $arr;
	}
	unset($rows);
	return $astdb;
}

function downloadAstDb(&$msg) {
	$url = 'http://allmondb.allstarlink.org/';
	$data = @file_get_contents($url);
	if($data !== false) {
		$file = 'astdb.txt';
		if(file_put_contents($file, $data)) {
			$msg[] = "Retrieved and saved $file OK";
			return true;
		}
		$msg[] = error("Error saving ./$file. Check directory permissions.");
	} else {
		$msg[] = error("Error retrieving $url.");
	}
	return false;
}

function escapeXmlKey($key) {
	$key = str_replace([' ', ',', '/'], ['', '_', '_'], $key);
	if(is_numeric($key))
		$key = '_' . $key;
	return $key;
}
function escapeXmlValue($value) {
	$value = htmlspecialchars($value, ENT_NOQUOTES);
	return $value;
}
function arrayToXml($array, &$xml, $nType) {
	foreach($array as $key => $value) {
		if(is_object($value))
			$value = (array)$value;
		if(is_array($value)) {
			if(is_numeric($key)) {
				$subnode = $xml->addChild($nType);
				$subnode->addAttribute('id', $key);
				arrayToXml($value, $subnode, $nType);
			} else {
				$key = escapeXmlKey($key);
				$subnode = $xml->addChild($key);
				arrayToXml($value, $subnode, $nType);
			}
		} else {
			$key = escapeXmlKey($key);
			$value = escapeXmlValue($value);
			$xml->addChild($key, $value);
		}
	}
}

function outputXmlFile($data, $filename=null) {
	header('Expires: 0');
	header('Cache-Control: private, must-revalidate, post-check=60, pre-check=120');
	if($filename) {
		header('Content-Description: File Transfer');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
	}
	header('Content-Type: text/xml');
	ob_clean();
	flush();
	echo $data;
	exit();
}

function arrayToObj($array, $keys) {
	$obj = new stdClass();
	foreach($keys as $key) {
		if(isset($array[$key])) {
			// Trim leading and trailing whitespace. This function is usually used for processing form submits
			$obj->$key = trim($array[$key]);
		}
	}
	return $obj;
}
function csvToArray($csv) {
	$arr = $csv ? explode(',', $csv) : [];
	return $arr;
}

function error($s) {
	return "<span class=\"error\">$s</span>";
}
function errMsg($msg, $logToFile=false) {
	global $html;
	echo $html->pre($msg, 'error');
	if($logToFile)
		logErr($msg);
}
function logErr($msg) {
	echo $msg . NL;
	logToFile($msg);
}
function okMsg($msg) {
	global $html;
	echo $html->pre($msg, 'ok');
}
function varDump($var, $return=false) {
	global $html;
	if(is_array($var) || is_object($var))
		$var = print_r($var, true);
	else {
		if(!isset($var))
			$var = '[not set]';
		elseif($var === null)
			$var = '[null]';
		elseif($var === true)
			$var = "[true]";
		elseif($var === false)
			$var = "[false]";
		elseif($var === '')
			$var = "''";
		else
			$var = htmlspecial($var);
	}
	if($return)
		return $var;
	echo $html->pre($var);
}
function getRequestParms() {
	return (empty($_GET) ? $_POST : $_GET);
}
function getScriptName() {
	$name = $_SERVER['SCRIPT_NAME'];
	$name = preg_replace("#/index.php$#", "/", $name);
	return $name;
}
function getRequestURI() {
	return $_SERVER['REQUEST_URI'];
}
function validIpAddr($ipa) {
	if(!$ipa)
		return false;
	return (filter_var($ipa, FILTER_VALIDATE_IP) !== false);
}
function validEmail($email) {
	$emailMatchString = "/^\w+[\+\.\w-]*@([\w-]+\.)*\w+[\w-]*\.([a-z]{2,4}|\d+)$/i";
	$valid = (preg_match($emailMatchString, $email) == 1);
	return $valid;
}
function scanCtypes($buf) {
	$ret = new stdClass();
	$ret->nSpaces = $ret->Digits = $ret->nChars = 0;
	$len = strlen($buf);
	for($x=0; $x < $len; $x++) {
		if($buf[$x] == ' ')
			$ret->nSpaces++;
		elseif(ctype_digit($buf[$x]))
			$ret->nDigits++;
		elseif(ctype_alpha($buf[$x]))
			$ret->nChars++;
	}
	return $ret;
}
function checkAsciiChar($c) {
	$c = ord($c);
	if($c < 9 || $c > 126)
		return false;
	if($c > 10 && $c < 32 && $c != 13)
		return false;
	return true;
}
function checkAscii($str) {
	$len = strlen($str);
	for($n=0; $n < $len; $n++) {
		if(!checkAsciiChar($str[$n]))
			return false;
	}
	return true;
}
function validDbID($i) {
	return ($i > 0 && ctype_digit((string)$i));
}
function validUint($i) {
	return ($i >= 0 && ctype_digit((string)$i));
}
function validInt32($i) {
	return ($i >= -32768 && $i <= 32767 && ctype_digit((string)abs($i)));
}

function roundp($val, $prec=0) { // Round while maintaining specified precision
	$out = round($val, $prec);
	if($out == '-0')
		$out = 0;
	if($prec > 0)
		$out = sprintf("%0.{$prec}f", $out);
	return $out;
}
function _count($x) { // Count function that doesn't output warnings for non-arrays
	if(!$x)
		return 0;
	return is_array($x) ? count($x) : 1;
}

function getRemoteAddr() {
	$id = getenv('REMOTE_ADDR');
	if(strlen($id) < 7 || strlen($id) > 39 || preg_match('/[^0-9a-f\.:]/', $id) == 1)
		return null;
	return $id;
}

function readFileLines($fname, &$msg, $bak=false) {
	if(!file_exists($fname)) {
		$msg[] = "$fname not found";
		return false;
	}
	// Read in file and save a copy to .bak extension, verify we have write permission
	$f = file_get_contents($fname);
	if(!$f) {
		$msg[] = error("Read $fname failed. Check directory/file permissions");
		return false;
	}
	if($bak && !file_put_contents("$fname.bak", $f)) {
		$msg[] = error("Write $fname.bak failed. Check directory/file permissions");
		return false;
	}
	/* if($bak && !chmod("$fname.bak", 0664)) {
		$msg[] = "Chmod 0664 $fname.bak failed. Check directory/file permissions";
		return false;
	} */
	return explode(NL, $f);
}

function writeFileLines($fname, $f, &$msg) {
	$f = implode(NL, $f);
	if(!file_put_contents($fname, $f)) {
		$msg[] = error("Write $fname failed. Check directory/file permissions");
		return false;
	}
	/*if(!chmod($fname, 0664)) {
		$msg[] = "Chmod 0664 $fname.new failed. Check directory/file permissions";
		return false;
	}*/
	return true;
}

// Escape commas with double quotes, newlines with space, convert objects to arrays
function escapeCsv($data) {
	if(is_array($data)) {
		foreach($data as &$d)
			$d = escapeCsv($d);
	} elseif(is_object($data)) {
		$data = escapeCsv((array)$data);
	} else {
		$data = str_replace(NL, ' ', $data);
		if(strpos($data, ',') !== false) {
			$data = '"' . $data . '"';
		}
	}
	return $data;
}
function outputCsvHeader($filename) {
	// Escape double-quotes. Seems to be the only thing that works for all browsers
	$filename = str_replace('"', "''", $filename);
	header('Expires: 0');
	header('Cache-Control: private, must-revalidate, post-check=60, pre-check=120');
	header('Content-Description: File Transfer');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Content-Type: text/csv');
	ob_clean();
	flush();
}

function asExit($errMsg=null) {
	if($errMsg)
		errMsg($errMsg);
	$out = '</body>' . NL;
	$out .= '</html>' . NL;
	echo $out;
	exit();
}
