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
//define('', '');

// Global Cfgs Default Values
$defCfgVal = [
	publicPermission => PERMISSION_READ_ONLY,
	favsIniLoc => ['favorites.ini', '../supermon/favorites.ini']
];

// Global Cfgs structure
$gCfg = $defCfgVal;
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
	global $gCfg, $defCfgVal;
	$ids = implode(',', array_keys($gCfg));
	$where = "cfg_id IN($ids)";
	$cfgs = $this->getCfgs($where);
	if(empty($cfgs))
		return;
	foreach($cfgs as $c) {
		$k = $c->cfg_id;
		$gCfg[$k] = is_array($defCfgVal[$k]) ? explode(',', $c->val) : $c->val;
		$gCfgUpdated[$k] = $c->updated;
	}
}

// Save global cfgs. Caller will have updated $gCfg. Loop through cfgs, compare vals to DB and Def Vals
function saveCfgs() {
	global $gCfg, $defCfgVal;
	$ids = implode(',', array_keys($gCfg));
	$where = "cfg_id IN($ids)";
	$cfgs = $this->getCfgs($where);
	foreach($ids as $k) {
		// If val=DBval nothing to be done
		$val = is_array($defCfgVal[$k]) ? implode(',', $gCfg[$k]) : $gCfg[$k];
		$dbVal = isset($cfgs[$k]) ? (is_array($defCfgVal[$k]) ? implode(',', $cfgs[$k]) : $cfgs[$k]) : null;
		if($val === $dbVal)
			continue;
		// If val=DefVal delete from DB, else write to DB
		$defVal = is_array($defCfgVal[$k]) ? implode(',', $defCfgVal[$k]) : $defCfgVal[$k];
		if($val === $defVal) {
			$this->delete($k);
		} else {
			// Add if not in DB / Update otherwise
			$c = (object)['cfg_id'=>$k, 'val'=>$val];
			$this->update($c, isset($cfgs[$k]));
		}
	}
	if(!empty($cfgs)) {
		foreach($cfgs as $c) {
			$k = $c->cfg_id;
			$gCfg[$k] = is_array($defCfgVal[$k]) ? explode(',', $c->val) : $c->val;
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
		if($c->update < 1672964000 || !is_numeric($c->update))
			$c->update = 0;
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
