<?php
// JSON API for SkywarnPlus-NG alert line refresh (browser polling)
error_reporting(E_ERROR | E_WARNING | E_PARSE);
require_once('../include/apiInit.php');

header('Content-Type: application/json; charset=UTF-8');

if(!readOk()) {
	echo json_encode(['error' => 'forbidden']);
	exit;
}

if(strtolower($gCfg[skywarn_master_enable] ?? '') !== 'yes') {
	echo json_encode(['error' => 'skywarn_disabled']);
	exit;
}

echo json_encode(['html' => getSkywarnAlertHtml()]);
