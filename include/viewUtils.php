<?php
// viewUtils.php
// Author: David Gleason - AllScan.info
require_once('commonForms.php');
define('CREATE_FAVORITESINI_FILE', 'Create Favorites.ini File');

function showFavsIniForm() {
	echo '<form id="nodeForm" method="post" action="/allscan/"><fieldset>' . NL
		.'<input type=submit name="Submit" value="' . CREATE_FAVORITESINI_FILE . '">' . NL
		.'</fieldset></form>' . BR;
}

function showConnStatusTable() {
	global $node, $title2;
	echo '<h2>Connection Status</h2>
<div class="twrap">
<table class="grid" id="table_' . $node .'">
<thead>
<tr><th colspan="6"><i>' . $title2 . '</i></th></tr>
<tr><th>&nbsp;&nbsp;Node&nbsp;&nbsp;</th><th>Node Info</th><th>Received</th><th>Dir</th><th>Connected</th><th>Mode</th></tr>
</thead>
<tbody>
<tr><td colspan="6">Waiting...</td></tr>
</tbody>
</table>
</div><br>
';
}

function showNodeCtrlForm() {
	global $node, $remNode, $gCfg;
	echo '<form id="nodeForm" method="post" action="/allscan/"><fieldset>
<input type=hidden id="localnode" name="localnode" value="' . $node . '">
<input type=hidden id="conncnt" name="conncnt" value="0">
<label for="node">Node#</label><input type="text" inputmode="tel" pattern="[0-9a-dA-D\*#]*"
	id="node" name="node" maxlength="10" value="' . $remNode . '">
<input type=submit name="Submit" value="Add Favorite" class="small">
<input type=submit name="Submit" value="Delete Favorite" class="small">
<br>
<input type=button value="Connect" onClick="connectNode(\'connect\');">
<input type=button value="Disconnect" onClick="disconnectNode();">
<input type=button value="Monitor" onClick="connectNode(\'monitor\');">
<input type=button value="Local Mon" onClick="connectNode(\'localmonitor\');">
<input type=button value="DTMF" onClick="dtmfCmd();">
<br>
<input type=checkbox id="permanent"><label for="permanent">Permanent</label>&nbsp;
<input type=checkbox id="autodisc"' . ($gCfg[autodisc_def] ? ' checked' : '') . '><label
	for="autodisc">Disconnect before Connect</label>
</fieldset></form>
';
}

function showFooterLinks() {
	global $html;
	$out = [];
	$links = ['AllScan.info' => 'https://allscan.info/',
		'Updates' => 'https://github.com/davidgsd/AllScan#allscan',
		'AllStarLink.org' => 'https://www.allstarlink.org/',
		'Keyed&nbsp;Nodes' => 'http://stats.allstarlink.org/stats/keyed',
		'ASL&nbsp;Forum' => 'https://community.allstarlink.org/',
		'QRZ&nbsp;ASL&nbsp;Forum' => 'https://forums.qrz.com/index.php?forums/echolink-irlp-tech-board.76/',
		'eHam.net' => 'https://www.eham.net/'];
	foreach($links as $title => $url)
		$out[] = $html->a($url, null, $title, null, '_blank');
	$sep = ENSP . '|' . ENSP;
	echo $html->div(implode($sep, $out), 'm5');
}

function getSortLink($urlparms, $sortCol, $sortAsc, $col, $colName='sortCol', $ordName='sortOrd') {
	global $html;
	$url = getScriptName();
	$parms = $urlparms;
	// Start at page 1 when re-sorting
	unset($parms['page']);
	$parms[$colName] = $sortCol;
	$parms[$ordName] = $sortAsc ? 'a' : 'd';
	return $html->a($url, $parms, $col, null, null, false);
}

function upDownArrow($up) {
	$s = $up ? '&#9650;' : '&#9660;';
	return "<span class=\"arrow\">$s</span>";
}
