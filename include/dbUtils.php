<?php
// dbUtils.php
// Author: David Gleason - AllScan.info

$asdbdir = '/etc/allscan';
$asdbfile = '';

function dbInit() {
	global $asdir, $asdbdir, $asdbfile;
	$asdbfile = "$asdbdir/$asdir.db";
	if(!is_dir($asdbdir)) {
		pageInit();
		asExit("$asdbdir/ directory not found. Unable to init DB." . NL
			. "Run Update script or create $asdbdir with web server writeable permissions.");
	}
	$db = new DB($asdbfile);
	if(!$db) {
		pageInit();
		msg("DB open failed. $asdbfile may be corrupted. Try copying .bak over .db if exists or delete .db file.");
		asExit($db->error);
	}
	return $db;
}

function initErrExit($msg) {
	global $asdbfile;
	pageInit();
	echo implode(BR, $msg) . BR;
	msg("DB validation failed. $asdbfile may be corrupted. Try copying .bak over .db if exists or delete .db file.");
	asExit($db->error);
}

// checkTables() is called during page init prior to any headers or html output.
// Verifies necessary DB Tables exist and have correct columns needed for current AllScan version.
// Creates/updates any tables as needed.
// If users table did not exist or does not contain a valid admin account, default admin user is 
// created (name 'admin', pass 'allstarlink'). In that case user is redirected to the login page.
// After logging in they can then visit the users page and change any user settings or create 
// additional user accounts.
// If any errors occur msg will be written to $db->error and false will be returned
function checkTables($db, &$msg) {
	global $createUserSql, $createCfgSql;
	// Verify users table exists, create if not
	$tables = $db->getTableList();
	if($db->error)
		initErrExit($msg);
	$s = $tables ? implode(', ', $tables) : 'None found';
	$msg[] = "DB Tables: $s";
	if(!in_array('user', $tables)) {
		$msg[] = "Creating user DB Table";
		$ret = $db->exec($createUserSql);
		if(!$ret)
			initErrExit($msg);
	} else {
		$cols = $db->getColList('user');
		if($db->error)
			initErrExit($msg);
		//$s = $cols ? implode(', ', $cols) : 'None found';
		//$msg[] = "User Table cols: $s";
	}
	// Verify cfg table exists, create if not
	if(!in_array('cfg', $tables)) {
		$msg[] = "Creating cfg DB Table";
		$ret = $db->exec($createCfgSql);
		if(!$ret)
			initErrExit($msg);
	} else {
		$cols = $db->getColList('cfg');
		if($db->error)
			initErrExit($msg);
		//$s = $cols ? implode(', ', $cols) : 'None found';
		//$msg[] = "Cfg Table cols: $s";
	}
	// Return user count or false on error
	$cnt = $db->getRecordCount('user');
	if($db->error)
		initErrExit($msg);
	return $cnt;
}

$createUserSql = 'CREATE TABLE user (
user_id INTEGER PRIMARY KEY,
name TEXT NOT NULL,
hash TEXT NOT NULL,
email TEXT,
location TEXT,
nodenums TEXT,
permission INTEGER NOT NULL DEFAULT 1,
timezone_id INTEGER NOT NULL DEFAULT 0,
last_login INTEGER,
last_ip_addr TEXT);';

$createCfgSql = 'CREATE TABLE cfg (
cfg_id INTEGER PRIMARY KEY,
val TEXT NOT NULL,
updated INTEGER NOT NULL);';
