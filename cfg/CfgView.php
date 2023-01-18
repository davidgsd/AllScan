<?php
// CfgView.php class
// Author: David Gleason - AllScan.info
define('EDIT_CFG', 'Edit Cfg');
define('DEFAULT_CFG', 'Set to Default Value');

class CfgView {

function showCfgs($cfgs) {
	global $html, $gCfgName, $gCfgUpdated, $gCfgVals, $gCfgDef, $user; 
	$hdrCols = ['ID', 'Name', 'Value', 'Default Value', 'Last Updated'];
	$out = $html->tableOpen($hdrCols, null, 'favs', null);
	$nullVal = '-';
	foreach($cfgs as $id => $val) {
		$name = $gCfgName[$id];
		$updated = $gCfgUpdated[$id] ?? null;
		if($updated) {
			$updated = getTimestamp($updated, $user->timezone_id);
		} else {
			$updated = $nullVal;
		}
		$def = $gCfgDef[$id];
		if($gCfgVals[$id]) {
			$val = $gCfgVals[$id][$val];
			$def = $gCfgVals[$id][$def];
		} elseif(is_array($val)) {
			$val = implode(', ', $val);
			$def = implode(', ', $def);
		}
		if($val === $def)
			$val = '[Default]';
		if(empty($def))
			$def = '-';
		$row = [$id, $name, $val, $def, $updated];
		$out .= $html->tableRow($row, null);
	}
	$out .= $html->tableClose();
	//$out = $html->div($out, 'center');
	echo $out . BR;
}

function showForms($cfg) {
	global $html, $user, $gCfgName, $gCfgVals;
	$form = new stdClass();
	if($cfg !== null) {
		$id = $cfg->cfg_id;
		$val = $cfg->val;
		// Show Edit form
		$form->fieldsetLegend = EDIT_CFG;
		$form->submit = [EDIT_CFG, CANCEL];
		if($gCfgVals[$id]) {
			$ctrl = ['select' => ['val', $gCfgVals[$id], $val]];
		} else {
			if(is_array($val))
				$val = implode(', ', $val);
			$ctrl = ['text' => ['val', $val]];
		}
		$form->fields = [
			'Cfg Name' => ['r' => ['name', $gCfgName[$id]]],
			'Value' => $ctrl];
		$form->id = 'editCfgForm';
		$form->hiddenFields['cfg_id'] = $id;
		echo htmlForm($form) . BR;
	} else {
		// Show Edit request form
		$list = [];
		foreach($gCfgName as $k => $name)
			$list[$k] = $name;
		$form->fieldsetLegend = EDIT_CFG;
		$form->submit = [EDIT_CFG, DEFAULT_CFG];
		$form->id = 'editCfgForm';
		$form->fields = ['Cfg Name' => ['select'=> ['cfg_id', $list]]];
		echo htmlForm($form);
	}
	return;
}

}
