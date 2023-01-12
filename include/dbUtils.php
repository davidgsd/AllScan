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
		asExit("$asdbdir/ directory not found. Unable to init DB." . BR
			. "Run Update script or create $asdbdir with web server writeable permissions.");
	}
	if(!class_exists(SQLite3)) {
		pageInit();
		asExit("SQLite3 Class not found. Try running update script, update your OS and php-sqlite3 package, " . BR
			. "and check that pdo_sqlite and sqlite3 extensions are enabled (uncommented) in php.ini.");
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
// If no admin user account has been created yet user is redirected to the User module to create an account.
// After logging in they can then change Cfg settings, User settings, and create additional user accounts.
// If any errors occur msg will be written to $db->error and false will be returned
function checkTables($db, &$msg) {
	global $createUserSql, $createCfgSql;
	// Verify users table exists, create if not
	$tables = $db->getTableList();
	if($db->error) {
		$msg[] = "getTableList Error: $db->error";
		initErrExit($msg);
	}
	$s = $tables ? implode(', ', $tables) : 'None found';
	$msg[] = "DB Tables: $s";
	if(!in_array('user', $tables)) {
		$msg[] = "Creating user DB Table";
		$ret = $db->exec($createUserSql);
		if(!$ret) {
			$msg[] = "DB Error: $db->error";
			initErrExit($msg);
		}
	} else {
		$cols = $db->getColList('user');
		if($db->error) {
			$msg[] = "getColList(user) Error: $db->error";
			initErrExit($msg);
		}
		//$s = $cols ? implode(', ', $cols) : 'None found';
		//$msg[] = "User Table cols: $s";
	}
	// Verify cfg table exists, create if not
	if(!in_array('cfg', $tables)) {
		$msg[] = "Creating cfg DB Table";
		$ret = $db->exec($createCfgSql);
		if(!$ret) {
			$msg[] = "DB Error: $db->error";
			initErrExit($msg);
		}
	} else {
		$cols = $db->getColList('cfg');
		if($db->error) {
			$msg[] = "getColList(cfg) Error: $db->error";
			initErrExit($msg);
		}
		//$s = $cols ? implode(', ', $cols) : 'None found';
		//$msg[] = "Cfg Table cols: $s";
	}
	// Return user count or false on error
	$cnt = $db->getRecordCount('user');
	if($db->error) {
		$msg[] = "getRecordCount(user) Error: $db->error";
		initErrExit($msg);
	}
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
