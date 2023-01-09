<?php
require_once('commonForms.php');
define('CREATE_GLOBALINC_FILE', 'Create global.inc File');
define('EDIT_GLOBALINC_FILE', 'Edit global.inc File');
define('CREATE_FAVORITESINI_FILE', 'Create Favorites.ini File');

// AllScan should be able be installed to dirs other than /allscan/, including to multiple dirs with different cfgs for different nodes on a server. This gives a lot of flexibility and supports multiple independent nodes on one server. This requires that the main index file detect its dir path when first init'ing and opening the DB in a corresponding subdir of etc/allscan/. The base web path then gets saved in the DB and all script files reference that base path in any urls, file IO, redirects, etc.

function showGlobalIncForm($parms=null) {
	$form = new stdClass();
	$form->id = 'editGlobalIncForm';
	if($parms === null)
		$parms = (object)['call'=>'', 'location'=>'', 'title2'=>''];
	$form->fields = [
		'CALL'		=> ['text' => ['call', $parms->call]],
		'LOCATION' 	=> ['text' => ['location', $parms->location]],
		'TITLE2'  	=> ['text' => ['title2', $parms->title2]]];
	if(isset($parms->edit)) {
		$form->submit = EDIT_GLOBALINC_FILE;
	} else {
		$form->submit = CREATE_GLOBALINC_FILE;
	}
	$form->fieldsetLegend = '';
	echo htmlForm($form) . BR;
}

function showFavsIniForm() {
	echo '<form id="nodeForm" method="post" action="/allscan/"><fieldset>' . NL
		.'<input type=submit name="Submit" value="' . CREATE_FAVORITESINI_FILE . '">' . NL
		.'</fieldset></form>' . BR;
}

function checkboxControl($url, $parms, $name, $title, $isChecked) {
	global $html;
	$out = $html->checkbox($name, $title, $isChecked, null, 'bottom', true);
	if($parms !== null && is_array($parms)) {
		foreach($parms as $key=>$val)
			$out .= $html->hiddenField($key, $val);
	}
	$out = $html->formOpen($url ? $url : getScriptName()) . $out . $html->formClose();
	return $html->div($out, 'cbwrap');
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

function nullsToHyphens($plist, $parms) {
	$cols = [];
	foreach($plist as $key=>$val) {
		if(is_array($val)) {
			$set = false;
			foreach($val as $pid) {
				if(isset($parms[$pid])) {
					$cols[] = $parms[$pid];
					$set = true;
					break;
				}
			}
			if(!$set)
				$cols[] = '-';
		} else {
			if(isset($parms[$val]))
				$cols[] = $parms[$val];
			else
				$cols[] = '-';
		}
	}
	return $cols;
}
