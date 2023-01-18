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
if(!readOk())
	redirect('user/');

$msg = [];
// Ignore Add/Edit requests if not Admin user
if(isset($_POST['Submit']) && adminUser()) {
	$cfg = processForm($_POST['Submit'], arrayToObj($_POST, ['cfg_id', 'val']), $msg);
}

pageInit();
h1("Manage Cfgs");
$view = new CfgView();

if(!empty($msg)) {
	h3("Process Form Results:");
	echo implode(BR, $msg) . BR;
}

// Show Cfgs
h2("Configuration Parameters");
$view->showCfgs($gCfg);

// Show Edit forms
if(adminUser())
	$view->showForms($cfg ?? null);

asExit();

//-------- functions --------
function processForm($Submit, $cfg, &$msg) {
	global $cfgModel, $gCfg, $gCfgDef, $gCfgUpdated, $gCfgVals;
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
