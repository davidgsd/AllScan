<?php
// Common form actions
define('CANCEL', 'Cancel');
define('ACTION_ADD_REQUEST', 1);
define('ACTION_ADD', 2);
define('ACTION_EDIT_REQUEST', 3);
define('ACTION_EDIT', 4);
define('ACTION_DELETE_REQUEST', 5);
define('ACTION_DELETE', 6);
define('ACTION_EDIT_FIELD', 7);

function htmlForm($form, $horizontal=false) {
	global $html;
	if(!isset($form->url))
		$form->url = getScriptName();
	if(!isset($form->method))
		$form->method = 'post';
	if(!isset($form->id))
		$form->id = null;
	if(!isset($form->class))
		$form->class = null;
	if(!isset($form->target))
		$form->target = null;
	$out = $html->formOpen($form->url, $form->method, $form->id, $form->class, $form->target);
	if(isset($form->fieldsetLegend))
		$out .= $html->fieldsetOpen($form->fieldsetLegend);
	if(isset($form->hdr))
		$out .= $form->hdr . NL;
	// Open table ($hdrCols=null, $caption=null, $class=null, $escFunc=0)
	if(!isset($form->tableClass))
		$form->tableClass = 'noborder';
	$table = $html->tableOpen(null, null, $form->tableClass);
	// Input fields
	$colwidths = ['5em', '', ''];
	// Submit button(s)
	$span = '';
	if(!is_array($form->submit))
		$form->submit = [$form->submit];
	$style = (count($form->submit) > 3) ? 'small' : null;
	foreach($form->submit as $s)
		$span .= ' ' . $html->submitButton('Submit', $s, $style);
	$submit = $html->span($span, 'floatright');
	// Form fields
	$count = $form->fields ? count($form->fields) : 0;
	if($count) {
		$fields = htmlFormGetFields($form->fields);
		$i = 0;
		foreach($fields as $title => $f) {
			if(strlen($title) <= 1)
				$title = '';
			if($horizontal and ++$i == $count)
				$lastcol = $submit;
			else
				$lastcol = '&nbsp;';
			$table .= $html->tableRow([$title, $f, $lastcol], null, $colwidths);
			if($i)
				unset($colwidths);
		}
	}
	// formErrors
	if(isset($form->errMsg))
		$table .= '<tr><td style="height:2em;">Error</td><td colspan="2" class="error">'
			. $form->errMsg . '</td></tr>'."\n";
	if(!$horizontal)
		$table .= $html->tableRow(['', $submit, ''], null, false);
	if(isset($form->notes))
		$table .= "<tr><td colspan=\"3\" class=\"info\">$form->notes</td></tr>\n";
	if(isset($form->floatright))
		$class = 'floatright';
	$table .= $html->tableClose();
	$out .= $table;
	if(isset($form->note))
		$out .= $html->span($form->note, 'gray');
	if(isset($form->hiddenFields)) {
		foreach($form->hiddenFields as $k=>$v)
			$out .= $html->hiddenField($k, $v);
	}
	if(isset($form->fieldsetLegend))
		$out .= $html->fieldsetClose();
	$out .= $html->formClose();
	if(isset($class))
		$out = $html->div($out, $class);
	return $out;
}

function htmlSimpleForm($form, $labelOnTop=true) {
	global $html;
	if(!$form->method)
		$form->method = 'post';
	if(!$form->url)
		$form->url = getScriptName();
	if($form->floatright)
		$class = 'searchbar';
	// Open form
	$out = $html->formOpen($form->url, $form->method, $form->id, $class);
	if($form->fieldsetLegend)
		$out .= $html->fieldsetOpen($form->fieldsetLegend);
	// formErrors
	if($form->errMsg)
		$out .= $html->span($form->errMsg, 'error') . BR;
	// Form fields
	$count = count($form->fields);
	if($count) {
		$fields = htmlFormGetFields($form->fields);
		foreach($fields as $title => $f) {
			if(strlen($title) <= 1)
				$title = '';
			if($labelOnTop) {
				$out .= $html->div("$title<br>$f&nbsp;", 'container');
			} else {
				$out .= "$title $f&nbsp;\n";
			}
		}
	}
	// Submit button(s)
	if(!is_array($form->submit))
		$form->submit = [$form->submit];
	foreach($form->submit as $s)
		$out .= $html->submitButton('Submit', $s) . ' ';
	if($form->fieldsetLegend)
		$out .= $html->fieldsetClose();
	$out .= $html->formClose();
	return $out;
}

function htmlFormGetFields($fields) {
	global $html;
	$outFields = [];
	foreach($fields as $title => $f) {
		$out = '';
		foreach($f as $k=>$v) {
			$v0 = $v[0] ?? null;
			$v1 = $v[1] ?? null;
			$v2 = $v[2] ?? null;
			$v3 = $v[3] ?? null;
			switch($k[0]) {
				case 't': // text
					//textField($name, $label, $len=null, $val=null, $eol=false, $class=null, $readonly=null)
					if($k === 'textarea')
						$out .= $html->textArea($v0, null, $v1, $v2);
					elseif($k === 't128')
						$out .= $html->textField($v0, null, 128, $v1, 'wide');
					elseif($k === 't64')
						$out .= $html->textField($v0, null, 64, $v1, 'wide');
					elseif($k === 't3')
						$out .= $html->textField($v0, null, 3, $v1);
					else
						$out .= $html->textField($v0, null, null, $v1);
					break;
				case 'r': // readonly text
					$out .= $html->textField($v0, null, null, $v1, false, null, true);
					break;
				case 'p': // password
					$out .= $html->passwordField($v0, null);
					break;
				case 's': // select
				case 'm': // select multiple
					if(isset($v3)) // Don't escape vals
						$out .= $html->select($v0, null, $v1, $v2, null, $v3);
					else
						$out .= $html->select($v0, null, $v1, $v2);
					break;
				case 'c': // checkbox
					$out .= $html->checkbox($v0, null, $v1);
					break;
				case 'f': // file upload
					$out .= $html->fileUpload($v0, null, false, $v1);
					break;
				case 'F': // Multiple file upload
					$out .= $html->fileUpload($v0, null, true, $v1);
					break;
				case 'h': // hidden
					$out .= $html->hiddenField($v0, $v1);
					break;
				case 'd': // date
					$out = $html->dateField($v0, $v1);
					break;
				case 'D': // date range
					$out = $html->dateRange($v0, $v1);
					break;
				case 'T': // datetime
					$out = $html->datetimeField($v0, $v1);
					break;
				case 'L': // Line break
					$out = BR;
					break;
				case 'C': // Custom
					$out = $v;
					break;
			}
		}
		$outFields[$title] = $out;
	}
	return $outFields;
}

function confirmForm($label, $text, $parms=null, $url=null, $password=false, $submit=['Confirm', 'Cancel']) {
	global $html, $user, $userModel, $customerModel;
	$form = new stdClass();
	$form->method = 'post';
	if(!$url)
		$url = getScriptName();
	// Open form
	$out = $html->formOpen($url, $form->method, $form->id);
	if($label)
		$out .= $html->fieldsetOpen($label);
	// Text
	$out .= $html->p($text, null, false);
	// Password field
	if($password)
		$out .= $html->passwordField('password', 'Password');
	// Parm Vals
	if(is_array($parms)) {
		foreach($parms as $key => $val) {
			if(is_array($val)) {
				$k = $key . '[]';
				foreach($val as $v)
					$out .= $html->hiddenField($k, $v);
			} else {
				$out .= $html->hiddenField($key, $val);
			}
		}
	}
	// Submit button(s)
	$span = '';
	if(!is_array($submit))
		$submit = [$submit];
	foreach($submit as $s)
		$span .= ' ' . $html->submitButton('Submit', $s);
	$out .= $html->span($span, 'floatright');
	if($label)
		$out .= $html->fieldsetClose();
	$out .= $html->formClose();
	$out = $html->div($out);
	return $out;
}
