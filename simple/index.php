<?php
// AllScan simplified touch interface
require_once('../include/common.php');
require_once('../include/hwUtils.php');
require_once('../astapi/AMI.php');
require_once('../astapi/nodeInfo.php');
$html = new Html();
$msg = [];

asInit($msg);
chdir('..');
$db = dbInit();
$userCnt = checkTables($db, $msg);
if(!$userCnt)
	redirect('user/');
$cfgModel = new CfgModel($db);
$userModel = new UserModel($db);
$user =	$userModel->validate();
if(!readOk())
	redirect('user/');

if(!getAmiCfg($msg))
	simplePageError(implode(BR, $msg));
$node = $amicfg->node;
$astdb = readAstDb($msg);
if($astdb === false)
	simplePageError(implode(BR, $msg));

$favsFile = '';
$favsFiles = findFavsFiles($favsFile);
$parms = getRequestParms();
if(!empty($favsFiles) && isset($parms['favsfile']) && in_array($parms['favsfile'], $favsFiles))
	$favsFile = $parms['favsfile'];

$favsData = getFavsData($favsFile, $node, $astdb, $msg);
$channels = [];
foreach($favsData['favList'] as $f) {
	$rawLabel = isset($favsData['favs'][$f[0]]) ? $favsData['favs'][$f[0]]->label : $f[2];
	$callSign = '';
	if(array_key_exists($f[1], $astdb)) {
		$callSign = $astdb[$f[1]][1];
	} elseif($f[2] && $f[2] !== '-') {
		$nameParts = preg_split('/\s+/', $f[2]);
		$callSign = $nameParts[0];
	}
	$channels[] = [
		'num' => $f[0],
		'node' => (string)$f[1],
		'call' => $callSign,
		'label' => $rawLabel,
		'name' => $f[2],
		'desc' => $f[3],
		'loc' => $f[4]
	];
}

checkTitleCfgs();
$canModify = modifyOk();
$pageTitle = 'AllScan Simple';
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$channelJson = json_encode($channels, $jsonFlags);
$titleJson = json_encode($title2, $jsonFlags);
$msgJson = json_encode(implode(BR, $msg), $jsonFlags);

echo $html->htmlOpen($pageTitle)
	. "<link href=\"$urlbase/css/simple.css\" rel=\"stylesheet\" type=\"text/css\">" . NL
	. "<link href=\"$urlbase/favicon.ico\" rel=\"icon\" type=\"image/x-icon\">" . NL
	. '<meta name="viewport" content="width=device-width, initial-scale=1">' . NL
	. "<script src=\"$urlbase/js/simple.js\"></script>" . NL
	. '</head>' . NL
	. '<body onload="simpleInit()">' . NL;
?>
<div id="app" class="simple-app">
	<header class="topbar">
		<a class="brand" href="<?php echo $urlbase; ?>/">AllScan</a>
		<div class="node-title"><?php echo htmlspecial($title2); ?></div>
		<div class="node-number">Node <?php echo htmlspecial($node); ?></div>
	</header>

	<main class="simple-main">
		<section id="statusPanel" class="status-panel state-idle">
			<div>
				<div id="statusLabel" class="status-label">NO CONNECTION</div>
				<div id="statusDetail" class="status-detail">Waiting for node status...</div>
			</div>
			<button id="disconnectBtn" class="disconnect-btn" type="button" disabled>Disconnect</button>
		</section>

		<section id="warningPanel" class="warning-panel hidden">
			<div>
				<div class="warning-title">EXTRA CONNECTIONS</div>
				<div id="warningDetail" class="warning-detail">Additional nodes are connected.</div>
			</div>
			<button id="disconnectAllBtn" class="disconnect-all-btn" type="button">DISCONNECT ALL</button>
		</section>

		<section id="readonlyPanel" class="readonly-panel hidden">
			<div>
				<div class="readonly-title">READ ONLY</div>
				<div class="readonly-detail">Login required to change channels.</div>
			</div>
			<button id="loginBtn" class="login-btn" type="button">Login</button>
		</section>

		<section class="channels-shell">
			<button id="scrollUpBtn" class="scroll-btn" type="button" aria-label="Scroll channels up">&#9650;</button>
			<div id="channelsViewport" class="channels-viewport">
				<div id="channelsGrid" class="channels-grid"></div>
			</div>
			<button id="scrollDownBtn" class="scroll-btn" type="button" aria-label="Scroll channels down">&#9660;</button>
		</section>
	</main>

	<footer class="simple-footer">
		<div id="scanmsg"></div>
		<div id="statmsg"></div>
	</footer>
</div>

<script>
window.simpleConfig = {
	baseUrl: <?php echo json_encode($urlbase, $jsonFlags); ?>,
	loginUrl: <?php echo json_encode("$urlbase/user/", $jsonFlags); ?>,
	localNode: <?php echo json_encode((string)$node, $jsonFlags); ?>,
	title: <?php echo $titleJson; ?>,
	canModify: <?php echo $canModify ? 'true' : 'false'; ?>,
	channels: <?php echo $channelJson; ?>,
	startupMessages: <?php echo $msgJson; ?>
};
</script>
<?php
echo "</body>\n</html>\n";
exit();

function simplePageError($errMsg) {
	global $html, $urlbase;
	$errMsg = htmlspecial(str_replace(BR, ' ', $errMsg));
	echo $html->htmlOpen('AllScan Simple Error')
		. "<link href=\"$urlbase/css/simple.css\" rel=\"stylesheet\" type=\"text/css\">" . NL
		. '<meta name="viewport" content="width=device-width, initial-scale=1">' . NL
		. "</head>\n<body>\n"
		. '<div class="simple-app"><main class="simple-main">'
		. '<section class="status-panel state-warning"><div>'
		. '<div class="status-label">ERROR</div>'
		. '<div class="status-detail">' . $errMsg . '</div>'
		. '</div></section></main></div>'
		. "\n</body>\n</html>\n";
	exit();
}
