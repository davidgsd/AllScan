<?php
// AllScan main includes & common functions
// Author: David Gleason - AllScan.info
$AllScanVersion = "v0.79";
require_once('Html.php');
require_once('logUtils.php');
require_once('timeUtils.php');
require_once('viewUtils.php');
require_once('dbUtils.php');
require_once('DB.php');
require_once('UserModel.php');
require_once('CfgModel.php');

// API functions
define('GET_CPU_TEMP', 'getCpuTemp');

// Enable files to be written with group writeable permissions
umask(0002);

/*	AllScan can be installed in any top level folder within the www server root folder. allscan/ is the default,
	additional copies can be installed in other test/version-specific/backup dirs.
	This enables servers with multiple nodes to have separate allscan installs for each, eg. allscan567890/, ...
	Each install requires its own DB file in /etc/allscan/. Examples:
	Install Dir				DB File name
	wwwroot/allscan/		/etc/allscan/allscan.db			(default)
	wwwroot/allscan-test/	/etc/allscan/allscan-test.db
	wwwroot/				/etc/allscan/.db
	If you copy/move/backup an allscan install, eg. `cp -a allscan allscan-bak`, you should also copy the DB file:
	`cp /etc/allscan/allscan.db /etc/allscan/allscan-bak.db`
*/
// File System Cfgs - initialized in asInit() before dbInit() can be called
$wwwroot = '';	// eg. var/www/html
$asdir = '';	// eg. allscan
$subdir = '';	// eg. '' or user
$relpath = '';	// eg. allscan or allscan/user
$urlbase = '';	// eg. /allscan (prepended to url paths eg. <img src=\"$urlbase/AllScan.png\">)
// Title cfgs
$title = '';
$title2 = '';

function asInit(&$msg) {
	global $wwwroot, $asdir, $subdir, $relpath, $urlbase;
	$wwwroot = $_SERVER['DOCUMENT_ROOT'];
	$path = pathinfo($_SERVER['SCRIPT_NAME']);
	$relpath = $asdir = substr($path['dirname'], 1);
	if($asdir && strpos($asdir, '/')) {
		$dirs = explode('/', $asdir);
		$asdir = array_shift($dirs);
		$subdir = implode('/', $dirs);
	}
	$urlbase = $asdir ? "/$asdir" : '';
	$msg[] = "wwwroot=$wwwroot, asdir=$asdir, subdir=$subdir, relpath=$relpath";
	// Default install results: wwwroot=/var/www/html/, asdir=allscan, subdir=, relpath=allscan
	// Or if in an allscan subdir eg. user: same as above but subdir=user, relpath=allscan/user
}

function htmlInit($title) {
	global $html, $urlbase;
	echo $html->htmlOpen($title)
		.	"<link href=\"$urlbase/css/main.css\" rel=\"stylesheet\" type=\"text/css\">" . NL
		.	"<link href=\"$urlbase/favicon.ico\" rel=\"icon\" type=\"image/x-icon\">" . NL
		.	'<meta name="viewport" content="width=device-width, initial-scale=0.6">' . NL
		.	"<script src=\"$urlbase/js/main.js\"></script>" . NL
		.	'</head>' . NL;
}

function pageInit($onload='', $showHdrLinks=true) {
	global $html, $AllScanVersion, $gCfg, $urlbase, $subdir, $title, $title2, $cfgModel, $userCnt;
	// Return now if not called from an HTML context
	if(!isset($html))
		return;

	htmlInit('AllScan - AllStarLink Favorites Management & Scanning');
	// Load Title cfgs. Do this after htmlInit(), global.inc can cause whitespace output
	if(isset($cfgModel) && $cfgModel->checkGlobalInc()) {
		$title2 = $title = $gCfg[call] . ' ' . $gCfg[location];
		if($gCfg[title])
			$title2 .= ' - ' . $gCfg[title];
	} else {
		$title = '[Call Sign] [Location]';
		$title2 = '[Node Title] - ' . $title;
	}
	// Output header
	$hdr = $lnk = [];
	$hdr[] = $html->a("$urlbase/", null, 'AllScan', 'logo') . " <small>$AllScanVersion</small>";
	$lnk[] = $html->a(getScriptName(), null, $title, 'title');
	if($showHdrLinks && $userCnt)
		$lnk = array_merge($lnk, getHdrLinks());
	$hdr[] = implode(' | ', $lnk);
	$hdr[] = "<span id=\"hb\"><img src=\"$urlbase/AllScan.png\" width=16 height=16 alt=\"*\"></span>";
	echo "<body$onload>" . NL . '<header>' . NL . implode(ENSP, $hdr) . '</header>' . NL . BR;
}

function getHdrLinks() {
	global $html, $urlbase, $user;
	$lnk = [];
	if(isset($user->user_id) && validDbID($user->user_id)) {
		// Show links to Cfg and User modules if Admin user
		if(adminUser()) {
			$url = "$urlbase/cfg/";
			$title = 'Cfgs';
			$lnk[] = ($url === getScriptName()) ? $title : $html->a($url, null, $title);
			$url = "$urlbase/user/";
			$title = 'Users';
			$lnk[] = ($url === getScriptName()) ? $title : $html->a($url, null, $title);
		}
		// Show Settings & Logout links
		$url = "$urlbase/user/settings/";
		$title = 'Settings';
		$lnk[] = ($url === getScriptName()) ? $title : $html->a($url, null, $title);
		$lnk[] = $html->a("$urlbase/user/", ['logout'=>1], 'Logout');
	} else {
		// Show Login link
		$lnk[] = $html->a("$urlbase/user/", null, 'Login');
	}
	return $lnk;
}

function msg($txt, $class=null) {
	global $html;
	if(isset($html)) {
		if($class)
			$txt = $html->span($txt, $class);
		$txt .= BR;
	}
	echo $txt . NL;
}

$allmonini = ['allmon.ini', '../supermon/allmon.ini', '/etc/asterisk/allmon.ini.php', '../allmon2/allmon.ini.php',
				'/etc/allmon3/allmon3.ini', '../supermon2/user_files/allmon.ini'];
//'/etc/asterisk/manager.conf'

// Get nodes list and host IP(s)
function getNodeCfg(&$msg, &$hosts) {
	global $allmonini;
	// Check for file in our directory and if not found look in the asterisk, supermon and allmon2 dirs
	foreach($allmonini as $f) {
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
	$msg[] = "No valid [node#] and host definitions found. Check that you have AllMon or Supermon installed or " . BR
		.	"an allmon.ini.php file in /etc/asterisk/ containing a [YourNode#] line followed by host and passwd defines.";
	return false;
}

// Below called by astapi files, which should only happen if controller file eg. index.php already called
// getNodeCfg() above which confirms there is a valid file available
function readNodeCfg() {
	global $allmonini;
	// Check for file in our directory and if not found look in the asterisk, supermon and allmon dirs
	foreach($allmonini as $f) {
		if(file_exists($f)) {
			$cfg = parse_ini_file($f, true);
			if($cfg === false)
				continue;
			// Allmon3 uses 'pass' instead of 'passwd' cfg name, convert here
			foreach($cfg as &$c) {
				if(isset($c['pass']) && !isset($c['passwd']))
					$c['passwd'] = $c['pass'];
			}
			unset($c);
			return $cfg;
		}
	}
	return false;
}

$astdbtxt = ['astdb.txt', '../supermon/astdb.txt', '/var/log/asterisk/astdb.txt'];

// Read AstDB file, looking in all commonly used locations
function readAstDb(&$msg) {
	global $astdbtxt;
	// Check for file in our directory and in the allmon/supermon locations
	// If exists in more than one place use the newest. Download it if not found
	$mtime = [0, 0, 0];
	foreach($astdbtxt as $i => $f) {
		if(file_exists($f)) {
			if(filesize($f) < 1024) {
				$msg[] = "$f last updated " . date('Y-m-d', filemtime($f));
				$msg[] = "$f invalid filesize - try running AllMon/Supermon astdb.php to reload";
			} else {
				$mtime[$i] = filemtime($f);
				$msg[] = "$f last updated " . date('Y-m-d', $mtime[$i]);
			}
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
		$file = $astdbtxt[$keys[0]];
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
// getNodeCfg() above which confirms there is a valid file available
function readAstDb2() {
	global $astdbtxt;
	// Check for file in our directory and if not found look in the asterisk, supermon and allmon2 dirs
	// If it exists in more than one place use the newest
	$mtime = [0, 0, 0];
	foreach($astdbtxt as $i => $f) {
		if(file_exists($f) && filesize($f) >= 1024) {
			$mtime[$i] = filemtime($f);
		}
	}
	arsort($mtime, SORT_NUMERIC);
	if(!reset($mtime)) {
		return false;
	}
	$keys = array_keys($mtime);
	$file = $astdbtxt[$keys[0]];
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

function checkDiskSpace(&$msg, $dir='/') {
	$free = disk_free_space($dir);
	$total = disk_total_space($dir);
	if($free) {
		$free = round($free/1073741824, 2);
		$total = round($total/1073741824, 2);
		$p = round(100 * $free / $total, 1);
		if($dir === '/')
			$msg[] = "File system space free $p% ($free/$total GB)";
		else
			$msg[] = "$pct% space free ($free / $total GB) in '$dir'";
	} else {
		$msg[] = "Error reading '$dir' disk free space";
	}
	// Check for log files > 50MB
	$cwd = getcwd();
	$d1 = '/var/log';
	if(chdir($d1) === false) {
		$msg[] = "Unable to cd to $d1";
		return;
	}
	checkLargeFiles($msg, $d1);
	$d1 = '/var/log/asterisk';
	if(chdir($d1) === false) {
		$msg[] = "Unable to cd to $d1";
		chdir($cwd);
		return;
	}
	checkLargeFiles($msg, $d1);
	chdir($cwd);
	// find /tmp -type f -size +50000k -delete
}

function checkLargeFiles(&$msg, $dir) {
	$cmd = "find . -maxdepth 1 -type f -size +50000k";
	$ret = exec($cmd, $out, $res);
	$cnt = count($out);
	if($cnt) {
		$msg[] = "$cnt file(s) > 50MB found in $dir:";
		foreach($out as $f) {
			$size = round(filesize($f)/1048576, 1);
			$f = str_replace('./', '', $f);
			$msg[] = "$f $size MB";
			if((posix_geteuid() === 0) && unlink($f))
				$msg[] = "Deleted $f";
		}
	}
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
	return $csv ? array_map(function($s) { return trim($s, " ;\n\r\t\v\x00"); }, explode(',', $csv)) : [];
}
function arrayToCsv($a) {
	return is_array($a) ? implode(',', $a) : $a;
}

function parseIntList($s) {
	if(!$s || preg_match_all('/(\d+)/', $s, $m) < 1)
		return [];
	return $m[0];
}

function error($s) {
	global $html;
	if(isset($html))
		return $html->span($s, 'error') . BR;
	return "ERROR: $s\n";
}
function errMsg($msg, $logToFile=false) {
	echo error($msg);
	if($logToFile)
		logErr($msg);
}
function logErr($msg) {
	echo $msg . NL;
	logToFile($msg);
}
function okMsg($msg) {
	global $html;
	echo $html->p($msg, 'ok');
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
	$emailMatchString = "/^\w+[\+\.\w-]*@([\w-]+\.)*\w+[\w-]*\.([a-z]{2,10}|\d+)$/i";
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

// Verify INT fields in an object have a numeric value, set to '0' if not.
function checkIntVals(&$o, $k) {
	foreach($k as $p) {
		if(!isset($o->$p) || !is_numeric($o->$p))
			$o->$p = '0';
	}
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

// path examples: '/' for allscan root dir, 'user/' for login page, or 'test/x.php' (no leading slash)
function redirect($path='') {
	global $asdir;
	$loc = $asdir ? "/$asdir/$path" : "/$path";
	header("Location: $loc");
	exit();
}

function asExit($errMsg=null) {
	global $html;
	if($errMsg)
		errMsg($errMsg);
	if(isset($html))
		echo "</body>\n</html>\n";
	exit();
}
