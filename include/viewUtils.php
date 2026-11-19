<?php
// viewUtils.php
// Author: David Gleason - AllScan.info
require_once('commonForms.php');
require_once('scheduleUtils.php');

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

function showNodeCtrlForm() {
	global $node, $remNode, $favsFile, $asdir, $gCfg;
	if(modifyOk())
		echo '<form id="nodeForm" method="post" action="/' . $asdir . '/"><fieldset>
<input type=hidden id="localnode" name="localnode" value="' . $node . '">
<input type=hidden id="conncnt" name="conncnt" value="0">
<input type=hidden id="favsfile" name="favsfile" value="' . $favsFile .'">
<input type=hidden id="originalnode" name="originalnode" value="">
<input type=hidden id="editlabel" name="editlabel" value="">
<input type=hidden id="editorder" name="editorder" value="">
<label for="node">Node#</label><input type="text" inputmode="tel" pattern="[0-9a-dA-D\*#]*"
	id="node" name="node" maxlength="10" value="' . $remNode . '">
<input type=button value="Connect" onClick="connectNode(\'connect\');">
<input type=button value="Disconnect" onClick="disconnectNode();">
<input type=button value="Monitor" onClick="connectNode(\'monitor\');">
<br>
<input type=submit name="Submit" value="Add Favorite" class="small">
<input type=button value="Edit Favorite" class="small" onClick="openFavoriteEditor();">
<input type=submit name="Submit" value="Delete Favorite" class="small"
	onClick="return confirmFavAction(\'Delete Favorite\');">
' . (schedEnabled() ? '<input type=button value="Schedule" class="small" onClick="openScheduleDialog();">
' : '') . '<input type=button value="Local Mon" onClick="connectNode(\'localmonitor\');">
<input type=button value="DTMF" onClick="dtmfCmd();">
<br>
<span style="white-space:nowrap"><input type=checkbox id="permanent"><label for="permanent">Permanent</label>&nbsp;
<input type=checkbox id="autodisc"' . ($gCfg[autodisc_def] ? ' checked' : '') . ' data-autodisc-default="'
	. ($gCfg[autodisc_def] ? 'true' : 'false') . '"><label for="autodisc">Disconnect before Connect</label>&nbsp;
<input type=checkbox id="automondisc"><label
	for="automondisc">Disconnect before Monitor</label></span>

<div id="favoriteEditModal" class="favEditModal" hidden onClick="favoriteEditorBackdrop(event);">
<div class="favEditPanel" role="dialog" aria-modal="true" aria-labelledby="favoriteEditTitle">
<h3 id="favoriteEditTitle">Edit Favorite</h3>
<label for="favoriteEditNode">Node Number</label>
<input type="text" inputmode="numeric" pattern="[0-9]*" id="favoriteEditNode" maxlength="8">
<label for="favoriteEditOrder">Order</label>
<input type="number" min="1" step="1" id="favoriteEditOrder">
<label for="favoriteEditLabel">Friendly Name/Label</label>
<input type="text" id="favoriteEditLabel" maxlength="120">
<div class="favEditActions">
<button type="submit" name="Submit" value="Edit Favorite" class="small"
	onClick="return submitFavoriteEdit();">Save &amp; Close</button>
<input type="button" value="Cancel" class="small" onClick="closeFavoriteEditor();">
</div>
</div>
</div>
</fieldset></form>
<div id="scheduleModal" class="favEditModal" hidden onClick="scheduleBackdrop(event);">
<div class="favEditPanel" role="dialog" aria-modal="true" aria-labelledby="scheduleTitle">
<h3 id="scheduleTitle">Schedule Connect/Disconnect</h3>
<div id="schedWarn" class="schedWarn" hidden>The scheduler has not run yet. It starts within a minute of being enabled. If this message persists, check the cron setup described in the README.</div>
<label for="schedAction">Action</label>
<select id="schedAction"><option value="connect">Connect</option><option value="monitor">Monitor</option>
<option value="localmonitor">Local Monitor</option><option value="disconnect">Disconnect</option></select>
<label for="schedNode">Node Number (0 = disconnect all)</label>
<input type="text" inputmode="numeric" pattern="[0-9]*" id="schedNode" maxlength="7">
<label for="schedTime">Time (24h, <span id="schedServerTime"></span>)</label>
<input type="time" id="schedTime" value="08:00">
<div class="schedDays">Days: <label><input type=checkbox class="schedDay" value="0">Su</label>
<label><input type=checkbox class="schedDay" value="1">Mo</label><label><input type=checkbox class="schedDay" value="2">Tu</label>
<label><input type=checkbox class="schedDay" value="3">We</label><label><input type=checkbox class="schedDay" value="4">Th</label>
<label><input type=checkbox class="schedDay" value="5">Fr</label><label><input type=checkbox class="schedDay" value="6">Sa</label>
<small>(none = every day)</small></div>
<div><label><input type=checkbox id="schedPerm">Permanent</label>
<label><input type=checkbox id="schedAutodisc">Disconnect all first</label></div>
<div class="favEditActions">
<input type="button" value="Add Schedule" class="small" onClick="addSchedule();">
<input type="button" value="Close" class="small" onClick="closeScheduleDialog();">
</div>
<div id="schedMsg"></div>
<table id="schedList" class="favs"></table>
</div>
</div>
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
