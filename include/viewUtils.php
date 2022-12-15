<?php

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
