<?php
require_once("include/common.php");
require_once("include/viewUtils.php");
require_once("include/hwUtils.php");
require_once(API . 'global.inc');
require_once(API . 'common.inc');
$html = new Html();
htmlInit('AllScan - AllStarLink Favorites Management & Scanning Web App');
$title = $CALL . ' ' . $LOCATION;
$title2 = $TITLE2 . ' - ' . $title;
$msg = [];

// Load ASL DB
$astdb = [];
$rows = readFileLines($ASTDB_TXT, $msg);
if($rows) {
	foreach($rows as $row) {
        $arr = explode('|', trim($row));
        $astdb[$arr[0]] = $arr;
    }
	unset($rows);
}
$cnt = count($astdb);
$msg[] = "$cnt Nodes in ASL DB";

// Read allmon.ini, get nodes list
if(!file_exists(API . 'allmon.ini'))
	asExit("allmon.ini not found");
$cfg = parse_ini_file(API . 'allmon.ini', true);
$nodes = explode(',', $cfg['All Nodes']['nodes']);
$node = $nodes[0];

// Handle form submits
$parms = getRequestParms();
//varDump($parms);
if(isset($parms['Submit'])) {
	processForm($parms, $msg);
}

$remNode = isset($parms['node']) && is_numeric($parms['node']) ? $parms['node'] : '';

echo 	"<body onLoad=\"initEventStream('server.php?nodes=$node');\">" . NL
	.	'<header>' . NL
	.	$html->a('/allscan/', null, 'AllScan', 'h1') . " <small>$AllScanVersion</small>" . ENSP
	.	$html->a('#', null, $title, 'title') . ENSP
	.	'<span id="hb"><img src="AllScan.png" width=16 height=16 class="nr" alt="*"></span>' . NL
	.	'</header>' . NL . BR;
?>

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

<?php
h2('Favorites');
// Read in favorites.ini
$favs = [];
$favcmds = [];
$favsFile = API . 'favorites.ini';
if(file_exists($favsFile)) {
	$favsIni = parse_ini_file(API . 'favorites.ini', true);
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
	$favList[] = [$n, $f->node, $name, $desc, $loc];
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
	$hdrCols = ['#', 'Node', 'Name', 'Desc', 'Location'];
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
		$nodeNumAttr = ['1' => 'class="nodeNum" onClick="setNodeBox(' . $f[1] . ')"'];
		$out .= $html->tableRow($f, null, null, false, $nodeNumAttr);
	}
	$out .= $html->tableClose();
	echo $out;
}

// Status Messages div
$msg = implode(BR, $msg);
echo "<div id=\"statmsg\">$msg</div>" . BR;

// Show CPU Temp & Info/Update Links
echo cpuTemp() . BR;
$links = [
	'AllScan Info & Updates' => 'https://github.com/davidgsd/AllScan',
	'AllStarLink.org' => 'https://www.allstarlink.org/',
	'AllStarLink Forum' => 'https://community.allstarlink.org/',
	'QRZ ASL Forum' => 'https://forums.qrz.com/index.php?forums/echolink-irlp-tech-board.76/',
	'eHam.net' => 'https://www.eham.net/'
];
$out = [];
foreach($links as $title => $url)
	$out[] = $html->a($url, null, $title, null, 'info');
echo $html->div(implode(ENSP . '|' . ENSP, $out), 'm5');

asExit();

// ---------------------------------------------------

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

function processForm($parms, &$msg) {
	global $astdb;
	$node = $parms['node'];
	$fname = API . 'favorites.ini';
	switch($parms['Submit']) {
		case "Add to Favorites":
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
			if(($favs = readFileLines($fname, $msg, true)) === false)
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
			$msg[] = "Delete Node $node from Favorites requested";
			if(!validDbID($node)) {
				$msg[] = "Invalid node#.";
				break;
			}
			// Parse file lines and delete favorite's label,cmd lines.
			// Note: Does not look for [general] or [node#] sections
			if(($favs = readFileLines($fname, $msg, true)) === false)
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
}
