<?php
// CfgModel.php class
// Author: David Gleason - AllScan.info
// AllScan global Configuration Parameter definitions
// Some modules have their own DB table to store data. cfg table stores other general configs
// For simplicty and space efficiency cfgs are stored in the cfg table by a numeric cfgID
// CfgMgr manages the read/write of cfgs to the DB and updating of the $gCfg struct.
// Callers need only reference $gCfg, and call saveCfgs() if they modify a $gCfg value.
define('publicPermission', 1);
define('favsIniLoc', 2);
define('call', 3);
define('location', 4);
define('title', 5);
//define('', );

// Global Cfgs Default Values
$gCfgDef = [
	publicPermission => PERMISSION_READ_ONLY,
	favsIniLoc => ['favorites.ini', '../supermon/favorites.ini'],
	call => '',
	location => '',
	title => ''
];

$gCfgName = [
	publicPermission => 'Public Permission',
	favsIniLoc => 'Favorites.ini Locations',
	call => 'Call Sign',
	location => 'Location',
	title => 'Node Title'
];

$publicPermissionVals = [
	PERMISSION_NONE			=> 'None (No Access)',
	PERMISSION_READ_ONLY	=> 'Read Only',
	PERMISSION_READ_MODIFY	=> 'Read/Modify',
	PERMISSION_FULL			=> 'Full'];

// Value definition arrays for enumerated cfgs. Specify null for plain text/numeric cfgs
$gCfgVals = [
	publicPermission => $publicPermissionVals,
	favsIniLoc => null,
	call => null,
	location => null,
	title => null
];

// Global Cfgs structure
$gCfg = $gCfgDef;
// Last update time of each gCfg (unix tstamp)
$gCfgUpdated = [];

// Below functions used to enable/disable site functions based on user and global permission settings
// CfgModel and UserModel classes must be instantiated before below are called.
// If readOk() returns false user is not allowed access to any pages or data.
function readOk() {
	global $user, $gCfg;
	return (isset($gCfg[publicPermission]) && $gCfg[publicPermission] >= PERMISSION_READ_ONLY)
		|| (isset($user) && userPermission() >= PERMISSION_READ_ONLY);
}

function modifyOk() {
	global $user, $gCfg;
	return (isset($gCfg[publicPermission]) && $gCfg[publicPermission] >= PERMISSION_READ_MODIFY)
		|| (isset($user) && userPermission() >= PERMISSION_READ_MODIFY);
}

function writeOk() {
	global $user, $gCfg;
	return (isset($gCfg[publicPermission]) && $gCfg[publicPermission] >= PERMISSION_FULL)
		|| (isset($user) && userPermission() >= PERMISSION_FULL);
}

function adminUser() {
	global $user;
	return (isset($user) && userPermission() >= PERMISSION_ADMIN);
}

class CfgModel {
const	TABLENAME = 'cfg';
public  $db;
public  $error;

function __construct($db) {
	global $msg, $asdbfile;
	$this->db = $db;
	// Read global cfgs
	$this->readCfgs();
	if($this->error) {
		pageInit();
		if(!empty($msg) && is_array($msg))
			echo implode(BR, $msg) . BR;
		msg("Cfg Init failed. $asdbfile may be corrupted. Try copying .bak over .db if exists or delete .db file.");
		asExit($this->error);
	}
}

// Read global cfgs from DB into $gCfg
function readCfgs() {
	global $gCfg, $gCfgDef, $gCfgUpdated;
	$ids = implode(',', array_keys($gCfg));
	$where = "cfg_id IN($ids)";
	$cfgs = $this->getCfgs($where);
	if(empty($cfgs))
		return;
	foreach($cfgs as $c) {
		$k = $c->cfg_id;
		$gCfg[$k] = is_array($gCfgDef[$k]) ? explode(',', $c->val) : $c->val;
		$gCfgUpdated[$k] = $c->updated;
	}
}

// Save global cfgs. Caller will have updated $gCfg. Loop through cfgs, compare vals to DB & Def Vals
function saveCfgs() {
	global $gCfg, $gCfgDef, $gCfgUpdated;
	$ids = array_keys($gCfg);
	$where = "cfg_id IN(" . arrayToCsv($ids). ")";
	$cfgs = $this->getCfgs($where);
	foreach($ids as $k) {
		// If val=DBval nothing to be done
		$val = is_array($gCfgDef[$k]) ? arrayToCsv($gCfg[$k]) : $gCfg[$k];
		$dbVal = isset($cfgs[$k]) ? (is_array($gCfgDef[$k]) ? arrayToCsv($cfgs[$k]) : $cfgs[$k]) : null;
		if($val === $dbVal)
			continue;
		// If val=DefVal delete from DB, else write to DB
		$defVal = is_array($gCfgDef[$k]) ? arrayToCsv($gCfgDef[$k]) : $gCfgDef[$k];
		if($val === $defVal) {
			if($dbVal) {
				$this->delete($k);
				unset($gCfgUpdated[$k]);
			}
		} else {
			// Add if not in DB / Update otherwise
			$c = (object)['cfg_id'=>$k, 'val'=>$val, 'updated'=>$gCfgUpdated[$k]];
			$this->update($c, !isset($cfgs[$k]));
		}
	}
}

private function getCfgs($where=null, $orderBy=null) {
	$cfgs = $this->db->getRecords(self::TABLENAME, $where, $orderBy);
	$this->checkDbError(__METHOD__);
	if(!_count($cfgs))
		return null;
	// Index by ID
	$a = [];
	foreach($cfgs as $c) {
		// Validate update time
		if($c->updated < 1672964000 || !is_numeric($c->updated))
			$c->updated = 0;
		$a[$c->cfg_id] = $c;
	}
	return $a;
}
private function getCfg($id) {
	if(!validDbID($id))
		return null;
	$where = "cfg_id='$id'";
	$cfgs = $this->getCfgs($where);
	return empty($cfgs) ? null : $cfgs[$id];
}
private function add($c) {
	return $this->update($c, true);
}
private function update($c, $add=false) {
	if(!$add && !validDbID($c->cfg_id))
		return null;
	if(!$this->validateVal($c->val))
		return null;
	$cols = ['cfg_id', 'val', 'updated'];
	$vals = [$c->cfg_id, $c->val, time()];
	if($add) {
		$retval = $this->db->insertRow(self::TABLENAME, $cols, $vals);
	} else {
		$retval = $this->db->updateRow(self::TABLENAME, $cols, $vals, "cfg_id=$c->cfg_id");
	}
	$this->checkDbError(__METHOD__);
	return $retval;
}
private function delete($id) {
	if(!validDbID($id))
		return null;
	$retval = $this->db->deleteRows(self::TABLENAME, "cfg_id=$id");
	$this->checkDbError(__METHOD__);
	return $retval;
}

function getCount($where=null) {
	$retval = $this->db->getRecordCount(self::TABLENAME, $where);
	$this->checkDbError(__METHOD__);
	return $retval;
}

function validateVal($c) {
	if(strlen($c) > 65535) {
		$this->error = 'Invalid Cfg Val. Must be <= 64K chars';
		return false;
	}
	return true;
}

// Do not call below prior to htmlInit(), global.inc include may cause whitespace to be output
function checkGlobalInc() {
	global $gCfg, $subdir;
	if($gCfg[call] && $gCfg[location])
		return true;
	// If Call and Location cfgs not set try importing from ../supermon/global.inc
	$loc = '../supermon/global.inc';
	if($subdir)
		$loc = "../$loc";
	if(strpos($subdir, '/'))
		$loc = "../$loc";
	if(file_exists($loc)) {
		include($loc);
		if(!$CALL || !$LOCATION)
			return false;
		$gCfg[call] = $CALL;
		$gCfg[location] = $LOCATION;
		if($TITLE2)
			$gCfg[title] = $TITLE2;
		$this->saveCfgs();
		return true;
	}
	return false;
}

private function checkDbError($method, $extraTxt='') {
	if(isset($this->db->error)) {
		if($extraTxt !== '')
			$extraTxt = "($extraTxt)";
		$this->error = $method . $extraTxt . ': ' . $this->db->error;
		unset($this->db->error);
		return true;
	}
	return false;
}

}
