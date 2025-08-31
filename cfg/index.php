<?php
// Cfg Module controller
// Author: David Gleason - AllScan.info
require_once('../include/common.php');
require_once('CfgView.php');
$html = new Html();
$msg = [];

// Init base cfgs
asInit($msg);
// Init DB (exits on error)
$db = dbInit();
// Validate/create required DB tables. Returns count of users in user table (exits on error)
$userCnt = checkTables($db, $msg);
if(!$userCnt)
	redirect('user/');
// Init gCfgs (exits on error)
$cfgModel = new CfgModel($db);
// Init Users module, validate user
$userModel = new UserModel($db);
$user =	$userModel->validate();
if(!adminUser())
	redirect('user/');

$msg = [];
$parms = getRequestParms();
// Ignore Add/Edit requests if not Admin user
if(isset($parms['Submit']) && adminUser() && $parms['Submit'] !== CANCEL) {
	$formvars = ['cfg_id', 'val', 'file', 'dir', 'suffix', 'confirm'];
	$cfg = processForm($parms['Submit'], arrayToObj($parms, $formvars), $msg);
}

pageInit();
$view = new CfgView();

if(!empty($msg)) {
	h3("Process Form Results:");
	echo implode(BR, $msg) . BR;
}

// Show Cfgs
echo '<div class="greenborder">' . NL;
h1("Manage Cfgs");
h2("Configuration Parameters");
$view->showCfgs($gCfg);
p("Node Number and AMI Cfgs default to values in /etc/asterisk/ rpt.conf and manager.conf if not set here.");
$view->showForms($cfg ?? null);
echo '</div>' . BR. NL;

echo '<div class="greenborder">' . NL;
h1("Manage Favorites");
h2("View/Download/Delete Favorites Files");
chdir('..'); // NOTE: chdir back to pwd later if needed for other file system operations
$activeFile = '';
$files = findFavsFiles($activeFile);
$cnt = 0;
$fl = [];
foreach($files as $f) {
	$r = new stdClass();
	$r->name = $f;
	$r->size = filesize($f);
	$r->mtime = getTimestamp(filemtime($f));
	$fl[] = $r;
	$cnt++;
}
if($cnt) {
	$view->showFiles($fl, $activeFile);
}

if(!$cnt) {
	p("No Favorites files found.");
} else {
	h2("Copy/Backup Favorites Files");

	$view->showFavsCopyForm($files);

	p('The Favorites file select control on the main page enables easy switching between files, supporting grouping of favorites by location, type, interests, etc. A new favorites file can be created by copying and then editing an existing file, or uploading a new file. Favorites files can be stored in the AllScan web folder or in /etc/allscan/. If you have multiple AllScan instances installed (eg. for different node #s) files in /etc/allscan/ can be used by all instances.', 'w800', false);
}

h2("Upload Favorites File");

$view->showFavsUploadForm();

p('Favorites file names must be in the format favorites[-*].ini, ie. with an optional suffix before the .ini extension. Example valid filenames: favorites.ini, favorites-WestCoast.ini, favorites-UK.ini, favorites-nets.ini, etc.', 'w800', false);

echo '</div>' . BR . NL;

asExit();

//-------- functions --------
function processForm($Submit, $cfg, &$msg) {
	global $cfgModel, $gCfg, $gCfgDef, $gCfgUpdated, $gCfgVals;

	if($Submit === DELETE_FILE || $Submit === CONFIRM_DELETE_FILE) {
		if(!isset($cfg->file))
			return null;
		$file = trim($cfg->file);
		if(!validateFavsFile($file, $msg))
			return null;
		if($Submit !== CONFIRM_DELETE_FILE) {
			pageInit();
			echo confirmForm("Confirm Delete", "Permanently delete \"$file\"?",
				(array)$cfg, null, false, [CONFIRM_DELETE_FILE, 'Cancel']);
			exit();
		}
		if(unlink($file))
			$msg[] = ok("Deleted $file OK");
		else
			$msg[] = error("Delete $file Failed, check directory permissions");
		return;
	}

	if($Submit === COPY_FILE) {
		if(!isset($cfg->file) || !isset($cfg->dir))
			return null;
		$file = trim($cfg->file);
		if(!validateFavsFile($file, $msg))
			return null;
		$dir = trim($cfg->dir);
		if(!preg_match('/^[\/\w\-. ]+$/', $dir)) {
			$msg[] = error("Invalid directory name");
			return null;
		}
		$suffix = $cfg->suffix ? trim($cfg->suffix, " \n\r\t\v\x00-") : '';
		if($suffix && !preg_match('/^[\w\-. ]+$/', $suffix)) {
			$msg[] = error("Invalid characters in suffix");
			return null;
		}
		//$name = basename($file, '.ini');
		//$new = $dir . trim($name);
		$new = $dir . 'favorites';
		if($suffix)
			$new .= '-' . $suffix;
		$new .= '.ini';
		if(file_exists($new))
			$msg[] = error("File $new already exists. Use Delete option to remove existing file before copying, or add a suffix to the name");
		elseif(copy($file, $new))
			$msg[] = ok("Copied $file to $new OK");
		else
			$msg[] = error("Copy to $new Failed, check directory permissions");
		return;
	}

	if($Submit === DOWNLOAD_FILE) {
		if(!isset($cfg->file))
			return null;
		$file = trim($cfg->file);
		if(!validateFavsFile($file, $msg))
			return null;
		$name = basename($cfg->file);
		outputTxtHeader($name);
		readfile($file);
		exit();
	}

	if($Submit === UPLOAD_FILE) {
		if(!isset($_FILES['fileupload'])) {
			return null;
		}
		$f = (object)$_FILES['fileupload'];
		/*	[name] => favorites.ini
			[full_path] => favorites.ini
			[type] => application/octet-stream
			[tmp_name] => /tmp/phpCdcA9I
			[error] => 0
			[size] => 3107 */
		if($f->error || !$f->size) {
			//$msg[] = varDump($f, true);
			$msg[] = error("File upload error $f->error on \"$f->name\"");
			return null;
		}
		if(!validateFavsFile($f->name, $msg, false)) {
			unlink($f->tmp_name);
			return null;
		}
		if(!isset($cfg->dir)) {
			unlink($f->tmp_name);
			return null;
		}
		$dir = trim($cfg->dir);
		if(!preg_match('/^[\/\w\-. ]+$/', $dir)) {
			$msg[] = error("Invalid directory name");
			unlink($f->tmp_name);
			return null;
		}
		$new = $dir . $f->name;
		if(file_exists($new))
			$msg[] = error("File $new already exists. Use Delete option to remove existing file before uploading");
		elseif(copy($f->tmp_name, $new))
			$msg[] = ok("Uploaded $new OK");
		else
			$msg[] = error("Upload $new Failed, check directory permissions");
		unlink($f->tmp_name);
		return;
	}

	$id = $cfg->cfg_id;
	$val = $cfg->val ?? null;
	if(!array_key_exists($id, $gCfg)) {
		$msg[] = error('Cfg not found');
		return;
	}
	if($Submit === EDIT_CFG) {
		if($val === null) {
			$cfg->val = $gCfg[$cfg->cfg_id];
			return $cfg;
		} else {
			// Convert array cfgs from csv / validate enumerated cfgs
			if(is_array($gCfgDef[$id])) {
				$val = csvToArray($val);
			} elseif($gCfgVals[$id] !== null && !array_key_exists($val, $gCfgVals[$id])) {
				$msg[] = error('Invalid Cfg value');
				return;
			}
			// Return now if cfg val did not change
			if(cfgCompare($val, $gCfg[$id])) {
				$msg[] = 'Cfg val unchanged';
				return null;
			} else {
				//$msg[] = "cfg={$gCfg[$id]} val=$val";
			}
			// Set Cfg
			$msg[] = 'Setting Cfg';
			$gCfg[$id] = $val;
			$gCfgUpdated[$id] = time();
		}
	} elseif($Submit === DEFAULT_CFG) {
		// Return now if cfg val is already the default
		if(cfgCompare($gCfg[$id], $gCfgDef[$id])) {
			$msg[] = 'Cfg val already = default val';
			return null;
		} else {
			//$msg[] = "cfg={$gCfg[$id]} Defval={$gCfgDef[$id]}";
		}
		// Set Cfg to default val
		$msg[] = 'Defaulting Cfg';
		$gCfg[$id] = $gCfgDef[$id];
		unset($gCfgUpdated[$id]);
	} else {
		return null;
	}
	$msg[] = 'Saving Cfgs';
	$cfgModel->saveCfgs();
	if($cfgModel->error)
		$msg[] = error($cfgModel->error);
	return null;
}
