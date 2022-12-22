<?php
require_once("include/common.php");
require_once("include/viewUtils.php");
require_once("include/hwUtils.php");
$html = new Html();
htmlInit('AllScan - AllStarLink Favorites Management & Scanning Web App');

define('favsini', 'favorites.ini');
define('smfavsini', '../supermon/favorites.ini');
$favsFile = file_exists(favsini) ? favsini : (file_exists(smfavsini) ? smfavsini : null);

define('globalinc', 'global.inc');
define('smglobalinc', '../supermon/global.inc');
$globalInc = file_exists(globalinc) ? globalinc : (file_exists(smglobalinc) ? smglobalinc : null);

// Load node and host definitions
$hosts = [];
$msg = [];
$nodes = readAllmonIni($msg, $hosts);
if(!empty($nodes) && !empty($hosts)) {
	$node = $nodes[0];
	$host = $hosts[0];

	// Load ASL DB
	$astdb = readAstDb($msg);

	if($astdb !== false)
		$onLoad = " onLoad=\"initEventStream('server.php?nodes=$node')\"";

	// Handle form submits
	$parms = getRequestParms();
	if(isset($_POST['Submit']) && $astdb !== false) {
		processForm($parms, $msg);
	}
}

// Load Title cfgs
if($globalInc) {
	include($globalInc);
	$title = $CALL . ' ' . $LOCATION;
	$title2 = $TITLE2 . ' - ' . $title;
} else {
	$title = '[CALL] [LOCATION]';
	$title2 = '[TITLE2] - ' . $title;
}

// Output header
echo 	"<body$onLoad>" . NL
	.	'<header>' . NL
	.	$html->a('/allscan/', null, 'AllScan', 'logo') . " <small>$AllScanVersion</small>" . ENSP
	.	$html->a('#', null, $title, 'title') . ENSP
	.	'<span id="hb"><img src="AllScan.png" width=16 height=16 alt="*"></span>' . NL
	.	'</header>' . NL . BR;

if(!isset($node) || $astdb === false)
	asExit(implode(BR, $msg));

if(!$globalInc) {
	p('global.inc not found. Check Supermon install or enter data below to create file in ' . getcwd() . '/.');
	showGlobalIncForm();
}

$autodisc = !isset($parms['autodisc']) || $parms['autodisc'];
$remNode = (isset($parms['node']) && validDbID($parms['node']) && strlen($parms['node']) < 9) ? $parms['node'] : '';
?>

<h2>Connection Status</h2>
<div class="twrap">
<table class="grid" id="table_<?php echo $node ?>">
<thead>
<tr><th colspan="7"><i><?php echo $title2 ?></i></th></tr>
<tr><th>&nbsp;&nbsp;Node&nbsp;&nbsp;</th><th>Node Info</th><th>Received</th><th>Link</th><th>Dir</th><th>Connected</th><th>Mode</th></tr>
</thead>
<tbody>
<tr><td colspan="7">Waiting...</td></tr>
</tbody>
</table>
</div><br>

<form id="nodeForm" method="post" action="/allscan/">
<fieldset>
<input type=hidden id="localnode" name="localnode" value="<?php echo $node ?>">
<input type=hidden id="conncnt" name="conncnt" value="0">
<label for="node">Node#</label><input type="text" inputmode="numeric" pattern="[0-9]*"
	id="node" name="node" maxlength="8" value="<?php echo $remNode ?>">
<input type=submit name="Submit" value="Add Favorite" class="small">
<input type=submit name="Submit" value="Delete Favorite" class="small">
<br>
<input type=button value="Connect" onClick="connectNode('connect');">
<input type=button value="Disconnect" onClick="disconnectNode();">
<input type=button value="Monitor" onClick="connectNode('monitor');">
<input type=button value="Local Monitor" onClick="connectNode('localmonitor');">
<br>
<input type=checkbox id="permanent"><label for="permanent">Permanent</label>&nbsp;
<input type=checkbox id="autodisc"<?php if($autodisc) echo ' checked' ?>><label
	for="autodisc">Disconnect before Connect</label>
</fieldset>
</form>

<?php
h2('Favorites');
// Read in favorites.ini
$favs = [];
$favcmds = [];
if(!$favsFile) {
	msg('favorites.ini not found. Check Supermon install or click below to create file in ' . getcwd() . '/.');
	showFavsIniForm();
} else {
	$favsIni = parse_ini_file($favsFile, true);
	if($favsIni === false) {
		p("Error parsing $favsFile. Check file format/permissions or create file with www-data writeable permissions.");
	} else {
		// Combine [general] stanza with this node's stanza
		$favsCfg = $favsIni['general'];
		if(isset($favsIni[$node])) {
			foreach($favsIni[$node] as $type => $arr) {
				if($type == 'label') {
					foreach($arr as $label) {
						$favsCfg['label'][] = $label;
					}
				} elseif($type == 'cmd') {
					foreach($arr as $cmd) {
						$favsCfg['cmd'][] = $cmd;
					}
				}
			}
		}
		$favsCfg['label'] = array_map('trim', $favsCfg['label']);
		$favsCfg['cmd'] = array_map('trim', $favsCfg['cmd']);
		foreach($favsCfg['cmd'] as $i => $c) {
			if(!$c) {
				unset($favsCfg['cmd'][$i], $favsCfg['label'][$i]);
			} else {
				if(preg_match('/[0-9]{4,6}/', $c, $m) == 1)
					$favs[$i] = (object)['node'=>$m[0], 'label'=>$favsCfg['label'][$i], 'cmd'=>$c];
				else
					$favcmds[$i] = (object)['label'=>$favsCfg['label'][$i], 'cmd'=>$c];
			}
		}
		// if(count($favcmds))
			// varDump($favcmds);
		$msg[] = _count($favs) . " favorites read from $favsFile";
	}
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
	$favList[] = [$n, $f->node, $name, $desc, $loc, NBSP, NBSP];
}

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
		$nodeNumAttr = ['1' => 'class="nodeNum" onClick="setNodeBox(' . $f[1] . ')"'];
		// Link name to ASL stats page for node
		$f[2] = $html->a("http://stats.allstarlink.org/stats/" . $f[1], null, $f[2], null, 'stats');
		$out .= $html->tableRow($f, null, null, false, $nodeNumAttr);
	}
	$out .= $html->tableClose();
	echo $out;
}

// Status Messages div
echo "<p id=\"scanmsg\"></p>";
$msg = implode(BR, $msg);
echo "<div id=\"statmsg\">$msg</div>" . BR;

// Show CPU Temp & Info/Update Links
echo cpuTemp() . ENSP . '|' . ENSP
	.	'<input type=button class="small" value="Restart Asterisk" onClick="astrestart();">';

$links = [
	'AllScan.info' => 'https://allscan.info/',
	'AllScan Updates' => 'https://github.com/davidgsd/AllScan#allscan',
	'AllStarLink.org' => 'https://www.allstarlink.org/',
	'Keyed Nodes' => 'http://stats.allstarlink.org/stats/keyed',
	'ASL Forum' => 'https://community.allstarlink.org/',
	'QRZ ASL Forum' => 'https://forums.qrz.com/index.php?forums/echolink-irlp-tech-board.76/',
	'eHam.net' => 'https://www.eham.net/'
];
$out = [];
foreach($links as $title => $url)
	$out[] = $html->a($url, null, $title, null, '_blank');
echo $html->div(implode(ENSP . '|' . ENSP, $out), 'm5');

asExit();

// ---------------------------------------------------
function processForm($parms, &$msg) {
	global $astdb, $favsFile, $globalInc;
	$node = $parms['node'];
	switch($parms['Submit']) {
		case "Add Favorite":
			$msg[] = "Add Node $node to Favorites requested";
			if(!validDbID($node)) {
				$msg[] = "Invalid node#.";
				break;
			}
			if(!array_key_exists($node, $astdb)) {
				$msg[] = "Node $node not found in ASL DB. Check Node Number.";
				break;
			}
			// Parse file lines and add new favorite after last label,cmd lines.
			// Note: Does not look at [general] or [node#] sections (TBI)
			if(($favs = readFileLines($favsFile, $msg, true)) === false)
				break;
			$n = count($favs);
			$msg[] = "$n lines read from $favsFile";
			$lastCmdLn = 0;
			for($i=0; $i < $n; $i++) {
				if(strpos($favs[$i], 'cmd[]') === 0) {
					if(strpos($favs[$i], " $node\"")) {
						$msg[] = "Node $node already exists in favorites.";
						break(2);
					}
					$insertLn = $i + 2;
				}
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
			if(!writeFileLines($favsFile, $favs, $msg))
				break;
			$msg[] = "Successfully wrote $n lines to $favsFile";
			break;
		case "Delete Favorite":
			$msg[] = "Delete Node $node from Favorites requested";
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
			$lastCmdLn = 0;
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
		case CREATE_FAVORITESINI_FILE:
			$from = 'docs/favorites.ini.sample';
			$to = favsini;
			if(copy($from, $to)) {
				$msg[] = "Copied $from to $to OK";
				chmod($to, 0664);
				$favsFile = $to;
			} else {
				$msg[] = error("Copy $from to $to Error. Check directory permissions.");
			}
			break;
		case CREATE_GLOBALINC_FILE:
			$from = 'docs/global.inc.sample';
			$file = file_get_contents($from);
			if($file === false)  {
				$msg[] = error("$from not found");
				break;
			}
			$tags = ['[CALL]', '[LOCATION]', '[TITLE2]'];
			$parms = [$parms['call'], $parms['location'], $parms['title2']];
			$file = str_replace($tags, $parms, $file);
			$to = globalinc;
			if(file_put_contents($to, $file)) {
				$msg[] = "Wrote to $to OK";
				chmod($to, 0664);
				$globalInc = $to;
			} else {
				$msg[] = error("Error saving $to. Check directory permissions.");
			}
			break;
	}
}

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
