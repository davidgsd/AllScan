<?php
// AllScan API Init - Init cfgs & authenticate user
// For use by API files (files that do not output HTML)
// Author: David Gleason - AllScan.info
error_reporting(E_ERROR | E_WARNING | E_PARSE);
require_once('common.php');

// Authenticate user. Exits if AllScan init error or user login not valid. Otherwise call readOk(),
// modifyOk(), writeOk(), etc. to verify user permission level is sufficient for requested function

// Init base cfgs
$msg = [];
asInit($msg);
// Init DB (exits on error)
$db = dbInit();
// Validate DB tables. Returns count of users in user table (exits on error)
$userCnt = checkTables($db, $msg);
if(!$userCnt)
	die("Authentication Failed\n");
// Init gCfgs (exits on error)
$cfgModel = new CfgModel($db);
// Init Users module, validate user
$userModel = new UserModel($db);
$user =	$userModel->validate();
if(empty($user))
	die("User Authentication Failed\n");
