<?php
// Html.php class
// Author: David Gleason
define('NL', "\n");
define('BR', "<br>\n");
define('NBSP', '&nbsp;');
define('EMSP', '&emsp;');
define('ENSP', '&ensp;');
function nbsp($count=1) { $s=''; for($n=0;$n<$count;$n++) $s.= NBSP; return $s; }
function htmlspecial($txt) { // Does not escape quotes. Do not use for attributes
	return htmlspecialchars($txt, ENT_NOQUOTES | ENT_HTML5 | ENT_IGNORE, 'UTF-8', false);
}
function htmlattr($txt) { // Escape an attribute (no double quotes)
	return htmlspecialchars($txt, ENT_COMPAT | ENT_HTML5 | ENT_IGNORE, 'UTF-8', false);
}

class Html
{
var $checkBoxId=1;
function tag($tag, $txt, $class=null, $escapeTxt=false, $style=null, $id=null) {
	$out = "<$tag";
	if($id)
		$out .= " id=\"$id\"";
	if($class)
		$out .= " class=\"$class\"";
	if($style)
		$out .= " style=\"$style\"";
	$out .= '>';
	$out .= ($escapeTxt ? htmlspecial($txt) : $txt);
	$out .= "</$tag>";
	return $out;
}
function span($txt, $class=null, $escapeTxt=false, $style=null) {
	return $this->tag('span', $txt, $class, $escapeTxt, $style);
}
function h($num, $txt, $class=null, $escapeTxt=true) {
	return $this->tag("h$num", $txt, $class, $escapeTxt) . NL;
}
function pre($txt, $class=null, $escapeTxt=false, $style=null) {
	return $this->tag('pre', $txt, $class, $escapeTxt, $style) . NL;
}
function div($txt, $class=null, $id=null, $style=null) {
	return $this->tag('div', $txt, $class, false, $style, $id) . NL;
}
function header($txt, $class=null) {
	return $this->tag('header', $txt, $class) . NL;
}
function article($txt, $class=null) {
	return $this->tag('article', $txt, $class) . NL;
}
function p($txt, $class=null, $escapeTxt=true, $id=null) {
 	return $this->tag('p', $txt, $class, $escapeTxt, null, $id) . NL;
}
function a($url, $parms=null, $title=null, $class=null, $target=null, $escapeTitle=true, $onclick=null, $style=null) {
	$props = [];
	$props[] = "<a";
	if(isset($url) && strlen($url)) {
		$url = str_replace(' ', '+', $url);
		if($parms !== null && is_array($parms))
			$url .= "?". http_build_query($parms, '', '&amp;');
		$props[] = "href=\"$url\"";
	}
	if(isset($class)) $props[] = "class=\"$class\"";
	if(isset($target)) $props[] = "target=\"$target\"";
	if(isset($onclick)) {
		if($onclick === 'download')
			$props[] = "download";
		else
			$props[] = "onclick=\"$onclick\"";
	}
	if(isset($style)) $props[] = "style=\"$style\"";
	if($escapeTitle)
		$title = htmlspecial($title);
	$out = implode(' ', $props) .">$title</a>";
	return $out;
}
// build a url from base url and parms array, format for JS ('&' not escaped)
function buildUrl($url, $parms) {
	if(isset($url) && strlen($url)) {
		if(is_array($parms))
			$url .= "?". http_build_query($parms);
	}
	return $url;
}
function br($num=1) {
	for($i=0; $i < $num; $i++)
		$out .= BR;
	return $out;
}
function nbsp($num=1) {
	for($i=0; $i < $num; $i++)
		$out .= NBSP;
	return $out;
}
function img($url, $alt='', $class=null, $width=null, $height=null) {
	$out = "<img src=\"$url\" alt=\"$alt\"";
	if($class)
		$out .= " class=\"$class\"";
	if($width)
		$out .= " width=\"$width\"";
	if($height)
		$out .= " height=\"$height\"";
	$out .= '>';
	return $out;
}
function iframe($url, $class=null, $text='') {
	$props = [];
	$props[] = "<iframe";
	$props[] = "src=\"$url\"";
	if($class) $props[] = "class=\"$class\"";
	$out = implode(' ', $props) .">$text</iframe>\n";
	return $out;
}
// FORMS
function formOpen($scriptName=null, $method='post', $id=null, $class=null, $target=null) {
	if(!$scriptName)
		$scriptName = getScriptName();
	if($id)
		$id = " id=\"$id\"";
	if($class)
		$class = " class=\"$class\"";
	if($target)
		$target = " target=\"$target\"";
	return "<form$target action=\"$scriptName\"$id method=\"$method\" ".
		"enctype=\"multipart/form-data\"$class AUTOCOMPLETE=\"off\">\n";
}
function textField($name, $label, $len=null, $val=null, $class=null, $readonly=null) {
	$out = strlen($label) ? ("<label for=\"$id\">" . htmlspecial($label) . "</label>\n") : '';
	$out .= '<input ';
	if($readonly)
		$out .= 'readonly ';
	$out .= "type=text name=\"$name\" AUTOCOMPLETE=\"off\"";
	if($len)
		$out .= " maxlength=\"$len\"";
	if($class !== null && strlen($class))
		$out .= " class=\"$class\"";
	if($val)
		$out .= " value=\"" . htmlattr($val) . '"';
	$out .= ">\n";
	return $out;
}
function passwordField($name, $label, $len=null, $val=null, $class=null) {
	$size = ceil($len/1.6);
	if(strlen($val) > $size)
		$size = strlen($val);
	$out = strlen($label) ? ("<label for=\"$id\">" . htmlspecial($label) . "</label>\n") : '';
	$out .= "<input type=password name=\"$name\"";
	if($len)
		$out .= " size=\"$size\" maxlength=\"$len\"";
	if($class !== null && strlen($class))
		$out .= " class=\"$class\"";
	if($val)
		$out .= " value=\"" . htmlattr($val) . '"';
	$out .= " AUTOCOMPLETE=\"off\">\n";
	return $out;
}
function checkbox($name, $label, $isChecked=false, $class=null) {
	global $checkBoxId;
	$id = 'cb' . $checkBoxId++;
	$out = strlen($label) ? ("<label for=\"$id\">" . htmlspecial($label) . "</label>") : '';
	$out .= "<input type=checkbox name=\"$name\" id=\"$id\"";
	if(strlen($class))
		$out .= " class=\"$class\"";
	if($isChecked)
		$out .= " checked";
	$out .= ">\n";
	return $out;
}
function fileUpload($name, $label, $multiple=false, $class=null) {
	$out = strlen($label) ? ("<label for=\"$id\">" . htmlspecial($label) . "</label>") : '';
	$out .= "<input type=file name=\"$name\"";
	if(strlen($class))
		$out .= " class=\"$class\"";
	if($multiple)
		$out .= " multiple";
	$out .= ">\n";
	return $out;
}
function hiddenField($name, $val) {
	$name = htmlattr($name);
	$val = htmlattr($val);
	return "<input type=hidden name=\"$name\" value=\"$val\">\n";
}
function select($name, $label, $list, $selected=null, $class=null, $escapeTxt=true, $submitOnChange=false) {
	$out = $label ? $this->getLabelHtml($label, $name) : '';
	$out .= '<select ';
	if($label)
		$out .= 'id="' . $name . '" ';
	$multiple = is_array($selected);
	if($multiple) {
		$out .= 'multiple ';
		// Make list larger (up to 10 rows) if list is large
		$count = count($list) / 5;
		if($count > 10)
			$count = 10;
		if($count > 4)
			$out .= "size=\"$count\" ";
	}
	if($class)
		$out .= "class=\"$class\" ";
	if($submitOnChange)
		$out .= "onchange=\"this.form.submit()\" ";
	$out .= "name=\"$name\">\n";
	$key = array_keys($list);
	for($i=0; $i < count($list); $i++) {
		$txt = $escapeTxt ? htmlspecialchars($list[$key[$i]]) : $list[$key[$i]];
		$out .= " <option value=\"$key[$i]\"";
		if($multiple) {
			if(in_array($key[$i], $selected))
				$out .= " selected";
		} else {
			if($selected !== null) {
				if(is_numeric($selected) && is_numeric($key[$i])) {
					if($selected == $key[$i])
						$out .= " selected";
				} elseif($selected === $key[$i]) {
					$out .= " selected";
				}
			} elseif(!$i) {
				$out .= " selected";
			}
		}
		$out .= ">$txt</option>\n";
	}
	$out .= "</select>";
	return $out;
}
function submitButton($name, $val, $class='') {
	if($class !== '')
		$class = 'class="' . $class . '" ';
	$out = '<input type=submit name="'. $name . '" '. $class . 'value="' . htmlattr($val) . '">' . NL;
	return $out;
}
function textArea($name, $label, $text, $class=null, $rows=3, $cols=40) {
	$len = strlen($text);
	//Expand size if necessary
	if($rows < 2) $rows = 2;
	if($cols < 15) $cols = 20;
	while($len > ($rows * $cols)) {
		$rows++; $cols += 5;
	}
	$params[] = "<textarea name=\"$name\"";
	if($class !== null)
		$params[] = "class=\"$class\"";
	$params[] = "rows=\"$rows\" cols=\"$cols\">";
	$out = implode(' ', $params) . htmlspecial($text) ."</textarea><br>\n";
	if(strlen($label))
		$out = "<label>$label</label>\n" . $out;
	return $out;
}
function dateRange($date0, $date1) {
	$out = $this->textField('date0', 'From', 10, $date0) . $this->nbsp(2)
		. $this->textField('date1', 'To', 10, $date1);
	return $out;
}
function dateField($name, $ts) {
	return $this->textField($name, null, 10, $date);
}
function datetimeField($name, $ts) {
	$y = (int)substr($ts, 0, 4);
	$M = (int)substr($ts, 5, 2);
	$d = (int)substr($ts, 8, 2);
	$h = (int)substr($ts, 11, 2);
	$m = (int)substr($ts, 14, 2);
	$MList = [1=>'Jan', 2=>'Feb', 3=>'Mar', 4=>'Apr', 5=>'May', 6=>'Jun',
		7=>'Jul', 8=>'Aug', 9=>'Sep', 10=>'Oct', 11=>'Nov', 12=>'Dec'];
	$dList = [];
	for($n=1; $n <= 31; $n++)
		$dList[$n] = $n;
	$hList = [];
	for($n=1; $n <= 12; $n++)
		$hList[$n] = $n;
	$mList = [];
	for($n=0; $n <= 59; $n++)
		$mList[$n] = sprintf('%02u', $n);
	$apList = ['AM', 'PM'];
	$ap = ($h >= 12) ? 1 : 0;
	if($h == 0)
		$h == 12;
	elseif($h > 12)
		$h -= 12;
	$s = '<b>&ndash;</b>';
	$out = '<input type="text" name="'.$name.'[0]" autocomplete="off" maxlength="4" value="'.$y.'" size="2">' . $s
		. $this->select($name . '[1]', null, $MList, $M, null, false) . $s
		. $this->select($name . '[2]', null, $dList, $d, null, false) . NBSP
		. $this->select($name . '[3]', null, $hList, $h, null, false) . '<b>:</b>'
		. $this->select($name . '[4]', null, $mList, $m, null, false) . NBSP
		. $this->select($name . '[5]', null, $apList, $ap, null, false);
	return $out;
}
function linkButton($title, $url, $class=null, $titleTag=null, $onClick=null, $target=null) {
	if($class)
		$class = " class=\"$class\"";
	if($titleTag)
		$titleTag = " title=\"$titleTag\"";
	if($onClick === null) {
		if($target) {
			$onClick = "let a=document.createElement('a'); a.href='$url'; a.target='$target'; a.click();";
		} else {
			$onClick = "window.location.href='$url';";
		}
	}
	return "<input type=button$class$titleTag value=\"$title\" onClick=\"$onClick\">\n";
}
function cmdButton($title, $parms, $class=null, $url=null, $titleTag=null) {
	if(!$url)
		$url = getScriptName();
	if($parms)
		$url .= '?' . http_build_query($parms, '', '&amp;');
	return $this->linkButton($title, $url, $class, $titleTag);
}

function fieldsetOpen($legend=null) {
	$out = "<fieldset>\n";
	if($legend)
		$out .= "<legend>" . htmlspecial($legend) . "</legend>\n";
	return $out;
}
function fieldsetClose() {
	return "</fieldset>\n";
}
function formClose() {
	return "</form>";
}
// TABLES
function tableOpen($hdrCols=null, $caption=null, $class=null, $escFunc=0, $id=null) {
	$class = $class ? " class=\"$class\"" : '';
	$id = $id ? " id=\"$id\"" : '';
	$out = "<table$id$class>";
	if($caption)
		$out .= "<caption>" . htmlspecial($caption) . "</caption>\n";
	if($hdrCols)
		$out .= $this->tableHeader($hdrCols, $escFunc);
	return $out;
}
// escFunc=0 => htmlspecial()
function tableHeader($cols, $escFunc=0) {
	$out = "<thead><tr>";
	foreach($cols as $col)
		$out .= "<th>" . $this->escape($col, $escFunc) . "</th>";
	$out .= "</tr></thead>\n";
	return $out;
}
// escFunc=0 => htmlspecial()
// cellAttr = Attributes indexed by column eg. [4=>'class="nodeNum" onClick="..."', ...]
function tableRow($cols, $escFunc=0, $colWidth=null, $useTh=false, $cellAttr=null) {
	$cellTag = $useTh ? 'th' : 'td';
	$out = "<tr>";
	$i=0;
	foreach($cols as $n => $col) {
		unset($width);
		if(is_array($colWidth)) {
			if($colWidth[$i])
				$width = $colWidth[$i];
			$i++;
		}
		elseif($colWidth)
			$width = $colWidth;
		if(isset($width))
			$width = " style=\"min-width:$width;\"";
		else
			$width = '';
		$attr = ($cellAttr && array_key_exists($n, $cellAttr)) ? (' ' . $cellAttr[$n]) : '';
		if($escFunc === null)
			$out .= "<$cellTag$width$attr>$col</$cellTag>";
		else
			$out .= "<$cellTag$width$attr>" . $this->escape($col, $escFunc) . "</$cellTag>";
	}
	$out .= "</tr>\n";
	return $out;
}
function tableClose() {
	return "</table>\n";
}
// LISTS
function ul($list) {
	$out = "<ul>";
	foreach($list as $li)
		$out .= "<li>$li\n";
	$out .= "</ul>\n";
	return $out;
}

// HEADERS
function htmlOpen($title, $style=null) {
	$title = htmlspecial($title);
	$style = $style ? " style=\"$style\"" : '';
	$out = "<!DOCTYPE html>\n"
		.	"<html lang=\"en\" dir=\"ltr\"$style>\n"
		.	"<head>\n"
		.	"<meta charset=\"utf-8\">\n"
		.	"<title>$title</title>\n";
	return $out;
}
function htmlClose() {
	return "</html>\n";
}
// Specify background color for html tag to prevent 'flash of white' before page rendered
function head($title, $cssFiles=null, $bgcolor=null, $scripts=null, $extraLines=null) {
	$style = ($bgcolor !== null) ? "background-color:$bgcolor;" : null;
	$out = $this->htmlOpen($title, $style);
	if($cssFiles === null)
		$cssFiles = ['/css/main.css'];
	if(is_array($cssFiles)) {
		foreach($cssFiles as $file)
			$out .= "<link href=\"$file\" rel=\"stylesheet\" type=\"text/css\">\n";
	}
	$out .= '<link href="/favicon.ico" rel="icon" type="image/x-icon">' . NL;
	$out .= '<meta name="viewport" content="width=device-width, initial-scale=1">' . NL;
	if($scripts !== null) {
		$out .= $scripts;
	} else {
		// Fix firefox FOUC bug
		$out .= '<script> </script>' . NL;
	}
	if($extraLines !== null)
		$out .= $extraLines;
	$out .= "</head>\n";
	return $out;
}
// MISCELLANEOUS
/* Escape contexts:
- Within a tag e.g. <p>xxx</p>: htmlspecial() for non-html contents, none otherwise
- Within a property e.g. alt="xxx": htmlattr(). Properties cannot contain html or double-quotes
- Within a url: rawurlencode().
*/
function escape($txt, $escFunc=null) {
	static $func = ['htmlspecial', 'htmlentities', 'urlencode', 'rawurlencode'];
	if($escFunc === null or $escFunc >= count($func))
		return $txt;
	return $func[$escFunc]($txt);
}
function hr($width="100%") {
	return "<hr width=\"$width\">\n";
}

function audio($url, $class=null, $preload=false, $center=false) {
	$class = $class ? " class=\"$class\"" : '';
	$preload = $preload ? '' : ' preload="none"';
	$out = "<audio controls$class$preload><source src=\"$url\" type=\"audio/mpeg\"></audio>\n";
	if($center)
		$out = $this->div($out, 'ac');
	return $out;
}

function convertToHtml($txt) {
	$txt = htmlspecial($txt);
	$txt = preg_replace("/\n/", "<br>", $txt);
	return txt;
}
function cleanHtml($var) {
	if(is_array($var)) {
		foreach($var as $k => $v) {
			$newk = $this->cleanHtml($k);
			unset($var[$k]);
			$var[$newk] = $this->cleanHtml($v);
		}
	} elseif(is_object($var)) {
		$var = (object)$this->cleanHtml((array)$var);
	} elseif($var) {
		$var = str_replace(['<br>', '&nbsp;'], ' ', $var);
		$var = str_replace('&deg;', 'deg', $var);
		$var = str_replace('&amp;', '&', $var);
	}
	return $var;
}

function setLabelClass($class) {
	$this->labelClass = $class;
}
private function getLabelHtml($text, $id) {
	if($this->labelClass)
		$lc = ' class="' . $this->labelClass . '"';
	return "<label for=\"$id\"$lc>" . htmlspecialchars($text) . "</label>\n";
}

}
