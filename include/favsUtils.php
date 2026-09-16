<?php

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

function validateFavsFile($file, &$msg, $checkExists=true) {
	// Allow A-Z a-z 0-9 . _ - [ ] chars in name / suffix
	if(!preg_match('/^[\/\w\-.\[\]]+$/', $file)) {
		$msg[] = error("Invalid filename. May contain only 'A-Z a-z 0-9 . _ - [ ]' chars");
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

// Determine favorites file location(s). Check gCfg[favsIniLoc] locations and any files
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
	if(!$activeFile && count($vfiles))
		$activeFile = $vfiles[0];
	return $vfiles;
}

function getFavsData($favsFile, $node, $astdb, &$msg) {
	$favs = [];
	$favcmds = [];
	$favList = [];
	$error = '';
	if(!isset($favsFile) || !$favsFile) {
		return ['favs' => $favs, 'favcmds' => $favcmds, 'favList' => $favList, 'error' => $error];
	}
	$favsIni = parse_ini_file($favsFile, true);
	if($favsIni === false) {
		$error = "Error parsing $favsFile. Check file format/permissions or create file with www-data writeable permissions.";
		return ['favs' => $favs, 'favcmds' => $favcmds, 'favList' => $favList, 'error' => $error];
	}
	$favsCfg = ['label' => [], 'cmd' => []];
	if(isset($favsIni['general']))
		$favsCfg = array_merge($favsCfg, $favsIni['general']);
	if(isset($favsIni[$node])) {
		foreach($favsIni[$node] as $type => $arr) {
			if($type == 'label') {
				foreach($arr as $label)
					$favsCfg['label'][] = $label;
			} elseif($type == 'cmd') {
				foreach($arr as $cmd)
					$favsCfg['cmd'][] = $cmd;
			}
		}
	}
	$favsCfg['label'] = array_map('trim', $favsCfg['label'] ?? []);
	$favsCfg['cmd'] = array_map('trim', $favsCfg['cmd'] ?? []);
	foreach($favsCfg['cmd'] as $i => $c) {
		$label = $favsCfg['label'][$i] ?? '';
		if(!$c) {
			unset($favsCfg['cmd'][$i], $favsCfg['label'][$i]);
		} else {
			if(preg_match('/[0-9]{4,8}/', $c, $m) == 1)
				$favs[$i] = (object)['node'=>$m[0], 'label'=>$label, 'cmd'=>$c];
			else
				$favcmds[$i] = (object)['label'=>$label, 'cmd'=>$c];
		}
	}
	$msg[] = _count($favs) . " favorites read from $favsFile";
	$favList = buildFavList($favs, $astdb);
	return ['favs' => $favs, 'favcmds' => $favcmds, 'favList' => $favList, 'error' => $error];
}

function buildFavList($favs, $astdb) {
	$favList = [];
	$trimchars = " .,;\n\r\t\v\x00";
	foreach($favs as $n => $f) {
		if(array_key_exists($f->node, $astdb)) {
			list($x, $call, $desc, $loc) = $astdb[$f->node];
		} else {
			if($f->node < 3000000) {
				list($x, $call, $desc, $loc) = [$n, '-', '[Not in ASL DB]', '[Check Node Number]'];
			} else {
				$info = getELInfo($f->node);
				if(empty($info))
					list($x, $call, $desc, $loc) = [$n, '-', '[EchoLink Node]', '-'];
				else {
					if(preg_match('/(.*) (\[.*\])/', $info, $m) != 1)
						$m = [1=>'-', 2=>"[EchoLink $f->node]"];
					list($x, $call, $desc, $loc) = [$n, $m[1], $m[2], '-'];
				}
			}
		}
		$name = str_replace([$f->node, $call, $desc, $loc, ' ,'], ' ', $f->label);
		foreach(['call', 'name', 'desc', 'loc'] as $var)
			$$var = trim(str_replace('  ', ' ', $$var), $trimchars);
		foreach(['name', 'desc'] as $var)
			$$var = str_replace(['mhz', 'MHZ', 'hz'], ['MHz', 'MHz', 'Hz'], $$var);
		if(!$name)
			$name = $call;
		elseif(strpos($name, $call) === false && $call !== '-')
			$name = $call . ' ' . $name;
		if(empty($desc) && strlen($name) > strlen($call) && strlen($call) > 2) {
			if(strpos($name, $call) === 0) {
				$desc = trim(substr($name, strlen($call)), $trimchars);
				$name = $call;
			}
		}
		if(strpos($name, $call) !== false && strpos($desc, "$call ") !== false)
			$desc = trim(str_replace($call, '', $desc), $trimchars);
		$favList[] = [$n, $f->node, $name, $desc, $loc, NBSP, NBSP];
	}
	return $favList;
}

function getELInfo($n) {
	global $amicfg, $ami;
	static $fp;
	if( empty($amicfg->host) || empty($amicfg->port) ||
		empty($amicfg->user) || empty($amicfg->pass) || $fp === false ) {
		return;
	}
	// Login to AMI
	if(empty($ami)) {
		$ami = new AMI();
	}
	if(!isset($fp)) {
		$fp = $ami->connect($amicfg->host, $amicfg->port);
		if($fp === false) {
			return;
		}
		if($ami->login($fp, $amicfg->user, $amicfg->pass) === false) {
			unset($fp);
			return;
		}
	}
	return getAstInfo($fp, $n);
}
