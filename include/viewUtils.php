<?php
// viewUtils.php
// Author: David Gleason - AllScan.info
require_once('commonForms.php');

define('SET_NODE_INFO_CFGS', 'Set Node Info Cfgs');

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

/**
 * Fetch SkywarnPlus-NG status HTML (same output as main page alert line). For main page and api/skywarn.php.
 */
function getSkywarnAlertHtml() {
	global $gCfg;
	$apiUrl = trim($gCfg[skywarn_api_url] ?? '');
	if(!$apiUrl) {
		return 'SkyWarn+NG: API URL not configured';
	}
	$statusUrl = rtrim($apiUrl, '/') . '/api/status';
	$ctx = stream_context_create(['http' => ['timeout' => 5]]);
	$json = @file_get_contents($statusUrl, false, $ctx);
	if($json === false) {
		return 'SkyWarn+NG: API Offline';
	}
	$data = json_decode($json, true);
	if(!is_array($data)) {
		return 'SkyWarn+NG: API Error';
	}
	if(!empty($data['error'])) {
		return 'SkyWarn+NG: ' . htmlspecialchars($data['error'], ENT_QUOTES, 'UTF-8');
	}
	if(empty($data['has_alerts']) || empty($data['alerts'])) {
		return 'SkyWarn+NG: No Alerts';
	}
	$alerts = array_slice($data['alerts'], 0, 3);
	$severityColors = [
		'Extreme' => '#FF0000',
		'Severe'  => '#FF6600',
		'Moderate'=> '#FFCC00',
		'Minor'   => '#FFFF00'
	];
	$alertParts = [];
	foreach($alerts as $alert) {
		$event = htmlspecialchars($alert['event'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
		$severity = $alert['severity'] ?? 'Unknown';
		$color = $severityColors[$severity] ?? '#FF0000';
		$alertParts[] = '<span style="color: ' . $color . ';">' . $event . '</span>';
	}
	return 'SkyWarn+NG: ' . implode(', ', $alertParts);
}

function showSkywarnAlerts() {
	echo '<p id="skywarn-msg" class="skywarn-simple">' . getSkywarnAlertHtml() . '</p>' . NL;
}

function showNodeCtrlForm() {
	global $node, $remNode, $favsFile, $asdir, $gCfg;
	if(modifyOk())
		echo '<form id="nodeForm" method="post" action="/' . $asdir . '/"><fieldset>
<input type=hidden id="localnode" name="localnode" value="' . $node . '">
<input type=hidden id="conncnt" name="conncnt" value="0">
<input type=hidden id="favsfile" name="favsfile" value="' . $favsFile .'">
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
	else
		echo '<input type=hidden id="localnode" name="localnode" value="' . $node . '">' . NL;
}

function showSetNodeInfoForm() {
	global $html, $user, $gCfgName, $gCfg;
	$form = new stdClass();
	$form->fieldsetLegend = SET_NODE_INFO_CFGS;
	$form->submit = SET_NODE_INFO_CFGS;
	$form->fields = [
		$gCfgName[call] =>     ['t' => ['call', $gCfg[call]]],
		$gCfgName[location] => ['t' => ['location', $gCfg[location]]],
		$gCfgName[title] =>    ['t' => ['title', $gCfg[title]]],
	];
	$form->id = 'nodeInfoForm';
	echo htmlForm($form) . BR;
}

function showFooterLinks() {
	global $html;
	$out = [];
	$links = ['AllScan.info' => 'https://allscan.info/',
		'Updates' => 'https://github.com/davidgsd/AllScan#allscan',
		'AllStarLink.org' => 'https://www.allstarlink.org/',
		'Keyed&nbsp;Nodes' => 'http://stats.allstarlink.org/stats/keyed',
		'ASL&nbsp;Forum' => 'https://community.allstarlink.org/',
		'AllScan FB' => 'https://www.facebook.com/groups/allscan',
		'eHam.net' => 'https://www.eham.net/'];
	foreach($links as $title => $url)
		$out[] = $html->a($url, null, $title, null, '_blank') . NL;
	$sep = ' | ';
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
