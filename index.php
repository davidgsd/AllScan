<?php
// AllScan Main controller (index.php)
// Author: David Gleason - AllScan.info
require_once('include/common.php');
require_once('include/hwUtils.php');
require_once('astapi/AMI.php');
require_once('astapi/nodeInfo.php');
$html = new Html();
$msg = [];

// Init base cfgs
asInit($msg);
// Init DB (exits on error)
$db = dbInit();
// Validate/create required DB tables. Returns count of users in user table (exits on error)
$userCnt = checkTables($db, $msg);
if(!$userCnt)
	redirect('user/');
// Init gCfgs (exits on error)
$cfgModel = new CfgModel($db);
// Init Users module, validate user
$userModel = new UserModel($db);
$user =	$userModel->validate();
if(!readOk())
	redirect('user/');

$msg[] = "User: $user->name, IP: $user->ip_addr";
checkDiskSpace($msg);
$onLoad = '';

// Load node and host definitions
if(getAmiCfg($msg)) {
	$node = $amicfg->node;
	// Load ASL DB
	$astdb = readAstDb($msg);
	if($astdb !== false)
		$onLoad = " onLoad=\"asInit('server.php?nodes=$node')\"";

	// Handle form submits
	$parms = getRequestParms();
	if(isset($_POST['Submit']) && $astdb !== false) {
		if(isset($parms['favsfile']) && $parms['favsfile'])
			$favsFile = $parms['favsfile'];
		processForm($parms, $msg);
		// Reset parms after processing post submits
		unset($_POST, $parms);
		if(isset($favsFile))
			$parms = ['favsfile' => $favsFile];
	}

	// Determine favorites file location(s)
	$favsFile = '';
	$favsFiles = findFavsFiles($favsFile);
	if(!empty($favsFiles)) {
		if(isset($parms['favsfile']) && in_array($parms['favsfile'], $favsFiles))
			$favsFile = $parms['favsfile'];
	} else {
		unset($favsFile);
	}
}

pageInit($onLoad, true, checkUpdate());

if(!isset($node) || $astdb === false)
	asExit(implode(BR, $msg));

if(!$gCfg[call] && adminUser()) {
	p('Node Call Sign, Location and/or Title have not yet been set. Enter these below:');
	showSetNodeInfoForm();
}

if(!isset($parms))
	$parms = [];

$remNode = (isset($parms['node']) && validDbID($parms['node']) &&
			strlen($parms['node']) < 9) ? $parms['node'] : '';

showConnStatusTable();
showNodeCtrlForm();

h2('Favorites');
$favsData = getFavsData($favsFile ?? '', $node, $astdb, $msg);
$favs = $favsData['favs'];
$favcmds = $favsData['favcmds'];
$favList = $favsData['favList'];
// Sort favList by specified column if fs parm is set
$colKey = ['num', 'node', 'name', 'desc', 'loc'];
$sortCol = isset($_GET['fs']) && in_array($_GET['fs'], $colKey) ? $_GET['fs'] : 'num';
if($sortCol && !empty($favList) && count($favList) > 1) {
	$col = array_search($sortCol, $colKey);
	$sortAsc = !(isset($_GET['fso']) && $_GET['fso'] === 'd');
	$favList = sortArray($favList, $col, !$sortAsc);
}
// Output Favorites table
if(empty($favList)) {
	if($favsData['error'])
		p($favsData['error']);
	elseif(!isset($favsFile) || !$favsFile)
		p('No Favorites file found. Favorites files can be uploaded on the Cfgs Tab. '
			. 'A favorites-Sample.ini file can be downloaded from the AllScan github page');
	else
		p('No Favorites have yet been added');
} else {
	$hdrCols = ['#', 'Node', 'Name', 'Desc', 'Location', '<small>Rx%</small>', '<small>LCnt</small>'];
	if(count($favList) > 1) {
		foreach($hdrCols as $key => &$col) {
			if($key > 4)
				break;
			$ck = $colKey[$key];
			if($sortCol === $ck) {
				// Link to sort in opposite order
				$col = getSortLink($parms, $ck, !$sortAsc, $col, 'fs', 'fso');
				// Show an arrow indicating current sort col and direction
				$col .= upDownArrow($sortAsc);
			} else {
				// Link to sort in ASC order (or DESC order for time cols)
				$col = getSortLink($parms, $ck, true, $col, 'fs', 'fso');
			}
		}
	}
	$out = $html->tableOpen($hdrCols, null, 'favs', null, 'favs');
	foreach($favList as $f) {
		$nodeNumAttr = ['1' => 'class="nodeNum" onClick="setNodeBox('.$f[1].')" '
						.	'onDblClick="connectNode(\'connect\')"'];
		// Link name to ASL stats page for node
		if($f[1] >= 2000 && $f[1] < 3000000)
			$f[2] = $html->a("http://stats.allstarlink.org/stats/" . $f[1], null, $f[2], null, 'stats');
		if($f[3] == '')
			$f[3] = '-';
		if($f[4] == '')
			$f[4] = '-';
		$out .= $html->tableRow($f, null, null, false, $nodeNumAttr);
	}
	$out .= $html->tableClose();
	echo $out;
}

// Show Favorites File select control
showFavsSelect($favsFiles, $favsFile);

// Status Messages div
echo "<p id=\"scanmsg\"></p>";
$msg = implode(BR, $msg);
echo "<div id=\"statmsg\">$msg</div>" . BR;

$sep = ENSP . '|' . ENSP;
// Show CPU Temp if available
if(($ct = cpuTemp()))
	echo '<span id="cputemp">' . $ct . '</span>' . $sep . NL;

// Show function buttons and Links
echo $html->linkButton('Node Stats', "http://stats.allstarlink.org/stats/$node", 'small', null, null, 'stats');

if(writeOk())
	echo $html->linkButton('Restart Asterisk', null, 'small', null, 'astrestart();');

if(modifyOk() && !empty($gCfg[cmdbuttons]))
	showCustomCmdButtons();

showFooterLinks();

asExit();

// ---------------------------------------------------
function processForm($parms, &$msg) {
	global $astdb, $favsFile, $gCfg, $cfgModel;
	$node = $parms['node'];
	switch($parms['Submit']) {
		case "Add Favorite":
			$msg[] = "Add Node $node to Favorites requested";
			if(!validDbID($node)) {
				$msg[] = "Invalid node#.";
				break;
			}
			if(!array_key_exists($node, $astdb) && ($nodeNum >= 2000 && $nodeNum < 3000000)) {
				$msg[] = "Node $node not found in ASL DB. Check Node Number and that your astdb file is up-to-date.";
				break;
			}
			// Parse file lines and add new favorite after last label,cmd lines.
			// Note: Does not look at [general] or [node#] sections (TBI)
			if(isset($favsFile)) {
				if(($favs = readFileLines($favsFile, $msg, true)) === false)
					break;
				$n = count($favs);
				$msg[] = "$n lines read from $favsFile";
			} else {
				$favsFile = $gCfg[favsIniLoc][0];
				$favs = ['[general]', ''];
				$n = count($favs);
			}
			$insertLn = 0;
			for($i=0; $i < $n; $i++) {
				if(strpos($favs[$i], 'cmd[]') === 0) {
					if(strpos($favs[$i], " $node\"")) {
						$msg[] = "Node $node already exists in favorites.";
						break(2);
					}
					$insertLn = $i + 2;
				}
			}
			if(!$insertLn)
				$insertLn = $n;
			// Add blank line after last fav entry if not present
			if($favs[$insertLn] !== '') {
				array_splice($favs, $insertLn, 0, ['']);
				$n++;
			}
			list($x, $call, $desc, $loc) = $astdb[$node];
			$label = "label[] = \"$call $desc, $loc $node\"";
			$cmd = "cmd[] = \"rpt cmd %node% ilink 3 $node\"";
			array_splice($favs, $insertLn, 0, [$label, $cmd, '']);
			$n = count($favs);
			if(!writeFileLines($favsFile, $favs, $msg))
				break;
			$msg[] = "Successfully wrote $n lines to $favsFile";
			break;
		case "Delete Favorite":
			$msg[] = "Delete Node $node from Favorites requested";
			if(!isset($favsFile)) {
				$msg[] = "Favorites file does not exist.";
				break;
			}
			if(!validDbID($node)) {
				$msg[] = "Invalid node#.";
				break;
			}
			// Parse file lines and delete favorite's label,cmd lines.
			// Note: Does not look for [general] or [node#] sections
			if(($favs = readFileLines($favsFile, $msg, true)) === false)
				break;
			$n = count($favs);
			$msg[] = "$n lines read from $favsFile";
			for($i=0; $i < $n; $i++) {
				if(strpos($favs[$i], 'cmd[]') == 0 && strpos($favs[$i], " $node\""))
					$delLn = $i + 1;
			}
			if(!isset($delLn)) {
				$msg[] = "Node $node not found in $favsFile";
				break;
			}
			$nLines = 2;
			$startLn = $delLn - $nLines;
			if($startLn <= 0) {
				$msg[] = error("Invalid $favsFile format");
				break;
			}
			// Also delete blank line after entry if present
			if($favs[$delLn] === '' && $delLn < $n - 1)
				$nLines++;
			for($i=0; $i < $nLines; $i++)
				unset($favs[$startLn + $i]);
			$n = count($favs);
			if(!writeFileLines($favsFile, $favs, $msg))
				break;
			$msg[] = "Successfully wrote $n lines to $favsFile";
			break;
		case SET_NODE_INFO_CFGS:
			if(!isset($parms['call']) || !isset($parms['location']) || !isset($parms['title']))
				break;
			$gCfg[call] = $parms['call'];
			$gCfg[location] = $parms['location'];
			$gCfg[title] = $parms['title'];
			$cfgModel->saveCfgs();
			if($cfgModel->error)
				$msg[] = error('Error saving Node Info Cfgs: ' . $cfgModel->error);
			else
				$msg[] = 'Saved Node Info Cfgs OK';
			break;
	}
}

function checkUpdate() {
	global $msg, $gCfg;
	if(!$gCfg[updatecheck] || !adminUser())
		return false;
	$fname = "include/common.php";
	$vpat = '/^\$AllScanVersion = "v([0-9\.]{3,4})"/';
	if(!file_exists($fname))
		return true;
	$file = file($fname);
	if(empty($file))
		return true;
	foreach($file as $line) {
		if(preg_match($vpat, $line, $m) == 1) {
			$vl = $m[1];
			break;
		}
	}
	if(empty($vl))
		return true;

	$url = "https://raw.githubusercontent.com/davidgsd/AllScan/main/$fname";
	$file = file($url);
	if(empty($file)) {
		$msg[] = "Unable to retrieve $url";
		return false;
	}
	foreach($file as $line) {
		if(preg_match($vpat, $line, $m) == 1) {
			$vr = $m[1];
			break;
		}
	}
	if(empty($vr)) {
		$msg[] = "Error parsing $url";
		return false;
	}

	$vl = (float)trim($vl);
	$vr = (float)trim($vr);
	if($vl != $vr) {
		$msg[] = "AllScan v$vr is now available, click the Update link for more info.";
		return true;
	}
	return false;
}

function showCustomCmdButtons() {
	global $gCfg, $html;
	foreach($gCfg[cmdbuttons] as $b) {
		if(!preg_match('/(\*[0-9a-d;]{1,40})/', $b, $m))
			continue;
		$cmd = $m[1];
		echo $html->linkButton($b, null, 'small', null, "setNodeBox('$cmd');dtmfCmd();");
	}
}

function sortArray($list, $col, $desc) {
	$colVals = [];
	foreach($list as $val)
		$colVals[] = $val[$col];
	// Sort column, retain keys
	$opt = $col ? (SORT_STRING | SORT_FLAG_CASE) : SORT_REGULAR;
	if($desc)
		arsort($colVals, $opt);
	else
		asort($colVals, $opt);
	// Reorder input array
	$out = [];
	foreach(array_keys($colVals) as $k)
		$out[] = $list[$k];
	return $out;
}
