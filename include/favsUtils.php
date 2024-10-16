<?php
define('CREATE_FAVORITESINI_FILE', 'Create Favorites.ini File');

function showFavsSelect($files, $activeFile) {
	global $html;
	$out = $html->formOpen(getRequestUri(), 'get');
	$list = [];
	foreach($files as $f)
		$list[$f] = $f;
	$out .= $html->select('favsfile', 'Favorites File', $list, $activeFile, null, true, true);
	$out .= $html->formClose();
	echo $out . NL;
}

function showFavsIniForm() {
	echo '<form id="nodeForm" method="post" action="/allscan/"><fieldset>' . NL
		.'<input type=submit name="Submit" value="' . CREATE_FAVORITESINI_FILE . '">' . NL
		.'</fieldset></form>' . BR;
}

function validateFavsFile($file, &$msg, $checkExists=true) {
	// Allow A-Z a-z 0-9 . _ - chars in name / suffix
	if(!preg_match('/^[\/\w\-. ]+$/', $file)) {
		$msg[] = error("Invalid filename");
		return false;
	}
	if(!preg_match('/.ini$/', $file)) {
		$msg[] = error("Invalid filename suffix");
		return false;
	}
	$name = basename($file, '.ini');
	if(strpos($name, 'favorites') !== 0) {
		$msg[] = error("Invalid Favorites filename");
		return false;
	}
	if($checkExists && !file_exists($file)) {
		$msg[] = error("Requested file not found");
		return null;
	}
	return true;
}

// Determine favorites file location(s). Show all files defined in gCfg[favsIniLoc], and any files
// in the local dir or /etc/allscan/ dir named favorites*.ini
function findFavsFiles(&$activeFile) {
	global $gCfg;
	$files = [];
	// Search for files in local dir and /etc/allscan/ dir named favorites*.ini
	$ldir = asDir();
	$gdir = asDir(false);
	$lfiles = trim(shell_exec("ls {$ldir}favorites*.ini"));
	if($lfiles) {
		$files = explode(NL, $lfiles);
	}
	if($ldir !== $gdir) {
		$gfiles = trim(shell_exec("ls {$gdir}favorites*.ini"));
		if($gfiles) {
			$files = array_merge($files, explode(NL, $gfiles));
		}
	}
	// Build list, determine active file (first file found)
	$activeFile = '';
	foreach($gCfg[favsIniLoc] as $f) {
		if(!file_exists($f))
			continue;
		// convert to absolute path
		$f = realpath($f);
		if(!$activeFile)
			$activeFile = $f;
		if(!in_array($f, $files))
			$files[] = $f;
	}
	// Validate files
	$vfiles = [];
	foreach($files as $f) {
		$dbgmsg = [];
		if(validateFavsFile($f, $dbgmsg))
			$vfiles[] = $f;
	}
	return $vfiles;
}
