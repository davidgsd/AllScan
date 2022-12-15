<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<title>AllScan - AllStarLink Favorites Management & Scanning</title>
<link href="css/main.css" rel="stylesheet" type="text/css">
<link href="favicon.ico" rel="icon" type="image/x-icon">
<meta name="viewport" content="width=device-width, initial-scale=0.5">
<script src="js/main.js"></script>
</head>
<?php
require_once("include/common.php");
require_once("include/viewUtils.php");
require_once(API . 'global.inc');
require_once(API . 'common.inc');
$html = new Html();
$title = $CALL . ' ' . $LOCATION;
$title2 = $TITLE2 . ' - ' . $title;
// Get Allstar database file
$db = $ASTDB_TXT;	// Defined in global.inc
$astdb = [];
if(file_exists($db)) {
    $fh = fopen($db, "r");
    if(flock($fh, LOCK_SH)) {
        while(($line = fgets($fh)) !== FALSE) {
            $arr = preg_split("/\|/", trim($line));
            $astdb[$arr[0]] = $arr;
        }
    }
    flock($fh, LOCK_UN);
    fclose($fh);
}
$cnt = count($astdb);
$msg = "$cnt Nodes in ASL DB";

// Read allmon INI file, get nodes list
if(!file_exists(API . 'allmon.ini'))
	asExit("allmon.ini not found");
$cfg = parse_ini_file(API . 'allmon.ini', true);
//varDump($config);
$nodes = explode(',', $cfg['All Nodes']['nodes']);
$node = $nodes[0];

// Handle form submits
$parms = getRequestParms();
//varDump($parms);
if(isset($parms['Submit'])) {
	if($pfmsg = processForm($parms))
		$msg .= BR . $pfmsg;
}

$remNode = isset($parms['node']) && is_numeric($parms['node']) ? $parms['node'] : '';

echo "<body onLoad=\"initEventStream('" . API . "', 'server.php?nodes=$node');\">\n";
?>
<header>
<a href="/allscan/" class="h1">AllScan</a> <small>v0.1</small>&ensp;
<a href="#" class="title"><?php echo $title ?></a>&ensp;
<span id="hb"><img src="AllScan.png" width=16 height=16 alt="*" style="position:relative;top:-1px;" class="nr"></span>
</header>
<br>
<h2>Connection Status</h2>

<table class="gridtable" id="table_<?php echo $node ?>">
<colgroup><col span="1"><col span="1"><col span="1"><col span="1"><col span="1"><col span="1"><col span="1"></colgroup>
<thead>
<tr><th colspan="7"><i><?php echo $title2 ?></i></th></tr>
<tr><th>&nbsp;&nbsp;Node&nbsp;&nbsp;</th><th>Node Info</th><th>Received</th><th>Link</th><th>Dir</th><th>Connected</th><th>Mode</th></tr>
</thead>
<tbody>
<tr><td colspan="7">Waiting...</td></tr>
</tbody>
</table>

<p>
<form id="nodeForm" method="post" action="/allscan/">
<fieldset>
<input type=hidden id="localnode" name="localnode" value="<?php echo $node ?>">
<input type=text id="node" name="node" value="<?php echo $remNode ?>" maxlength="7">
<input type=button value="Connect" onClick="connectNode('connect');">
<input type=button value="Disconnect" onClick="disconnectNode();">
<input type=button value="Monitor" onClick="connectNode('monitor');">
<input type=button value="Local Monitor" onClick="connectNode('localmonitor');">
<input type=checkbox id="permanent"><label for="permanent">Permanent</label>
<br>
<input type=submit name="Submit" value="Add to Favorites">
<input type=submit name="Submit" value="Delete Favorite">
</fieldset>
</form>
</p>

<?php
h2('Favorites');
// Read in favorites.ini
$favs = [];
$favcmds = [];
$favsFile = API . 'favorites.ini';
if(file_exists($favsFile)) {
	$cpConfig = parse_ini_file(API . 'favorites.ini', true);
	// Combine [general] stanza with this node's stanza
	$cpCommands = $cpConfig['general'];
	if(isset($cpConfig[$node])) {
		foreach($cpConfig[$node] as $type => $arr) {
			if($type == 'label') {
				foreach($arr as $label) {
					$cpCommands['label'][] = $label;
				}
			} elseif($type == 'cmd') {
				foreach($arr as $cmd) {
					$cpCommands['cmd'][] = $cmd;
				}
			}			
		}
	}
	$cpCommands['label'] = array_map('trim', $cpCommands['label']);
	$cpCommands['cmd'] = array_map('trim', $cpCommands['cmd']);
	foreach($cpCommands['cmd'] as $i => $c) {
		if(!$c) {
			unset($cpCommands['cmd'][$i], $cpCommands['label'][$i]);
		} else {
			if(preg_match('/[0-9]{4,6}/', $c, $m) == 1)
				$favs[$i] = (object)['node'=>$m[0], 'label'=>$cpCommands['label'][$i], 'cmd'=>$c];
			else
				$favcmds[$i] = (object)['label'=>$cpCommands['label'][$i], 'cmd'=>$c];
		}
	}
	// if(count($favcmds))
		// varDump($favcmds);
} else {
	p("$favsFile not found. Check Supermon/AllMon install or create a blank file with www-data writeable permissions.");
}

// Combine favs node, label data with astdb data into favList
$favList = [];
foreach($favs as $n => $f) {
	list($x, $call, $desc, $loc) = array_key_exists($f->node, $astdb) ?
			$astdb[$f->node] : [$n, '-', 'Not in ASL DB', '-'];
	$name = str_replace([$f->node, $call, $desc, $loc, ' ,'], ' ', $f->label);
	$name = trim(str_replace('  ', ' ', $name), " .,;\n\r\t\v\x00");
	if(!$name)
		$name = $call;
	elseif(strpos($name, $call) === false)
		$name = $call . ' ' . $name;
	$favList[] = [$n, $name, $desc, $loc, $f->node];
}

// Sort favList by specified column if fs parm is set
$colKey = ['num','name','desc','loc','node'];
$sortCol = isset($_GET['fs']) && in_array($_GET['fs'], $colKey) ? $_GET['fs'] : 'num';
if($sortCol && !empty($favList) && count($favList) > 1) {
	$col = array_search($sortCol, $colKey);
	$sortAsc = !(isset($_GET['fso']) && $_GET['fso'] === 'd');
	$favList = sortArray($favList, $col, !$sortAsc);
}

// Output Favorites table
if(empty($favList)) {
	p('No Favorites have yet been added');
} else {
	$hdrCols = ['#', 'Name', 'Desc', 'Location', 'Node'];
	if(count($favList) > 1) {
		foreach($hdrCols as $key => &$col) {
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
	$out = $html->tableOpen($hdrCols, null, 'results', null);
	foreach($favList as $f) {
		$nodeNumAttr = ['4' => 'class="nodeNum" onClick="setNodeBox(' . $f[4] . ')"'];
		$out .= $html->tableRow($f, null, null, false, $nodeNumAttr);
	}
	$out .= $html->tableClose();
	echo $out;
}

// Status Messages field (written to by JS functions)
echo "<p id=\"statmsg\" class=\"gray\">$msg</p>\n";

// Show CPU Temp
if(file_exists("/sys/class/thermal/thermal_zone0/temp")) {
    $cpuTemp = exec("/usr/local/sbin/supermon/get_temp");
	if($cpuTemp) {
		$cpuTemp = str_replace(['palegreen', 'yellow', 'CPU:'], ['darkgreen', '#660', 'CPU Temp:'], $cpuTemp);
		p($cpuTemp, null, false);
	}
}

asExit();

function sortArray($list, $col, $desc) {
	$colVals = [];
	foreach($list as $val)
		$colVals[] = $val[$col];
	// Sort the column data while retaining array keys
	if($desc)
		arsort($colVals);
	else
		asort($colVals);
	// Reorder the input array
	$out = [];
	foreach(array_keys($colVals) as $k)
		$out[] = $list[$k];
	return $out;
}

function processForm($parms) {
	global $astdb;
	$node = $parms['node'];
	$msg = [];
	$fname = API . 'favorites.ini';
	switch($parms['Submit']) {
		case "Add to Favorites":
			$msg[] = "Add Node $node to Favorites requested";
			if(!array_key_exists($node, $astdb)) {
				$msg[] = "Node $node not found in ASL DB. Check Node Number.";
				break;
			}
			// Parse file lines and add new favorite after last label,cmd lines.
			// Note: Does not look at [general] or [node#] sections (TBI)
			if(($favs = readFileLines($fname, $msg)) === false)
				break;
			$n = count($favs);
			$msg[] = "$n lines read from $fname";
			$lastCmdLn = 0;
			for($i=0; $i < $n; $i++) {
				if(strpos($favs[$i], 'cmd[]') === 0)
					$insertLn = $i + 2;
			}
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
			if(!writeFileLines($fname, $favs, $msg))
				break;
			$msg[] = "Successfully wrote $n lines to $fname";
			break;
		case "Delete Favorite":
			// Parse file lines and delete favorite's label,cmd lines.
			// Note: Does not look for [general] or [node#] sections
			if(($favs = readFileLines($fname, $msg)) === false)
				break;
			$n = count($favs);
			$msg[] = "$n lines read from $fname";
			$lastCmdLn = 0;
			for($i=0; $i < $n; $i++) {
				if(strpos($favs[$i], 'cmd[]') == 0 && strpos($favs[$i], " $node\""))
					$delLn = $i + 1;
			}
			if(!isset($delLn)) {
				$msg[] = "Node not found in $fname";
				break;
			}
			$nLines = 2;
			$startLn = $delLn - $nLines;
			if($startLn <= 0) {
				$msg[] = "Invalid $fname format";
				break;
			}
			// Also delete blank line after entry if present
			if($favs[$delLn] === '' && $delLn < $n - 1)
				$nLines++;
			for($i=0; $i < $nLines; $i++)
				unset($favs[$startLn + $i]);
			$n = count($favs);
			if(!writeFileLines($fname, $favs, $msg))
				break;
			$msg[] = "Successfully wrote $n lines to $fname";
			break;
	}
	return implode(BR, $msg);
}

function readFileLines($fname, &$msg, $bak=true) {
	if(!file_exists($fname)) {
		$msg[] = "$fname not found";
		return false;
	}
	// Read in file and save a copy to .bak extension, verify we have write permission
	$f = file_get_contents($fname);
	if(!$f) {
		$msg[] = "Read $fname failed. Check directory/file permissions";
		return false;
	}
	if($bak && !file_put_contents("$fname.bak", $f)) {
		$msg[] = "Write $fname.bak failed. Check directory/file permissions";
		return false;
	}
	/* if($bak && !chmod("$fname.bak", 0664)) {
		$msg[] = "Chmod 0664 $fname.bak failed. Check directory/file permissions";
		return false;
	} */
	return explode(NL, $f);
}

function writeFileLines($fname, $f, &$msg) {
	$f = implode(NL, $f);
	if(!file_put_contents($fname, $f)) {
		$msg[] = "Write $fname failed. Check directory/file permissions";
		return false;
	}
	/*if(!chmod($fname, 0664)) {
		$msg[] = "Chmod 0664 $fname.new failed. Check directory/file permissions";
		return false;
	}*/
	return true;
}
