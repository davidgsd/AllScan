<?php
// Scheduled connect/disconnect management API. Returns JSON.
require_once('../include/apiInit.php');
require_once('../include/scheduleUtils.php');

header('Content-Type: application/json');
function reply($ok, $msg, $extra=[]) {
	exit(json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra)));
}

if(!schedEnabled())
	reply(false, 'Scheduled Connects is disabled. An Admin can enable it on the Cfgs page');
if(!modifyOk())
	reply(false, 'Insufficient user permission');

schedSetTimezone();
$action = isset($_POST['action']) ? $_POST['action'] : 'list';
$entries = schedLoad();
$err = '';

switch($action) {
	case 'list':
		break;
	case 'add':
		if(count($entries) >= SCHED_MAX_ENTRIES)
			reply(false, 'Maximum of ' . SCHED_MAX_ENTRIES . ' schedule entries reached');
		$in = [];
		foreach(['action2', 'node', 'time', 'days', 'perm', 'autodisc'] as $f)
			$in[$f] = isset($_POST[$f]) ? trim(strip_tags($_POST[$f])) : '';
		$in['action'] = $in['action2'];
		if(($e = schedValidate($in, $err)) === false)
			reply(false, $err);
		$entries[] = $e;
		if(!schedSave($entries, $err))
			reply(false, $err);
		break;
	case 'delete':
	case 'toggle':
		$id = isset($_POST['id']) ? $_POST['id'] : '';
		$found = false;
		foreach($entries as $i => $e) {
			if($e['id'] !== $id)
				continue;
			$found = true;
			if($action === 'delete')
				unset($entries[$i]);
			else
				$entries[$i]['enabled'] = empty($e['enabled']);
		}
		if(!$found)
			reply(false, 'Schedule entry not found');
		if(!schedSave($entries, $err))
			reply(false, $err);
		$entries = array_values($entries);
		break;
	default:
		reply(false, 'Invalid action');
}

foreach($entries as &$e)
	$e['desc'] = schedDescribe($e);
unset($e);
$hb = file_exists(SCHED_HEARTBEAT) ? filemtime(SCHED_HEARTBEAT) : 0;
reply(true, 'OK', [
	'entries' => $entries,
	'servertime' => date('D H:i T'),
	'runner' => ($hb && time() - $hb < 180)
]);
