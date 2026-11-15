<?php
// User Module Settings controller
// Author: David Gleason - AllScan.info
require_once('../../include/common.php');
require_once('../../include/scheduleUtils.php');
$html = new Html();
$msg = [];

// Init base cfgs
asInit($msg);
// Init DB (exits on error)
$db = dbInit();
// Validate DB tables. Returns count of users in user table (exits on error)
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

define('UPDATE_SETTINGS', 'Update Settings');
define('CHANGE_PASSWORD', 'Change Password');
define('ENABLE_SCHEDULER', 'Enable Scheduler');
define('DISABLE_SCHEDULER', 'Disable Scheduler');

$post = arrayToObj($_POST, ['name', 'email', 'location', 'nodenums', 'timezone_id', 'pass', 'confirm']);
$post->nodenums = isset($post->nodenums) ? parseIntList($post->nodenums) : [];
$Submit = $_POST['Submit'] ?? null;

pageInit();

h1('User Account Settings');

if(isset($userModel->error))
	errMsg($userModel->error);

switch($Submit) {
	case UPDATE_SETTINGS:
		if(!$userModel->validateFields($post)) {
			$post->errMsg = $userModel->error;
			unset($userModel->error);
		} else {
			$newUser = $user;
			$newUser->name = $post->name;
			$newUser->email = $post->email;
			$newUser->location = $post->location;
			$newUser->nodenums = $post->nodenums;
			$newUser->timezone_id = $post->timezone_id;
			// Below can return 0 [rows affected] if user submits form with no changes
			if($userModel->update($newUser) === null) {
				errMsg('Edit User Failed: ' . $userModel->error);
				unset($userModel->error);
			} else {
				okMsg('Edit User Successful');
				unset($post);
				// Read in user from DB
				$user = $userModel->getUserById($user->user_id);
			}
		}
		break;
	case ENABLE_SCHEDULER:
	case DISABLE_SCHEDULER:
		if(!adminUser()) {
			errMsg('Admin permission is required.');
			break;
		}
		$err = '';
		if($Submit === ENABLE_SCHEDULER ? schedCronEnable($err) : schedCronDisable($err))
			okMsg($Submit === ENABLE_SCHEDULER ? 'Scheduler enabled' : 'Scheduler disabled');
		else
			errMsg(htmlspecialchars($err));
		break;
	case CHANGE_PASSWORD:
		if(!$userModel->validatePassword($post->pass)) {
			$post->errMsg2 = $userModel->error;
			unset($userModel->error);
		} elseif(!$userModel->changePassword($_POST)) {
			$post->errMsg2 = $userModel->error;
		} else {
			okMsg("Change Password Successful");
		}
		break;
}

$form = new stdClass();
$form->class = 'left';
if(isset($post->errMsg))
	$form->errMsg = $post->errMsg;
$form->id = 'editUserForm';
$permList = $userModel->getPermissionList(userPermission(), userPermission());
$form->fields = [
	'Name'		=> ['text'	=> ['name', $user->name ?? '']],
	'Email' 	=> ['text' 	=> ['email', $user->email ?? '']],
	'Location'	=> ['text' =>  ['location', $user->location ?? '']],
	'Node #s'	=> ['text' => ['nodenums', implode(' ', $user->nodenums ?? '')]],
	'Permission' => ['select' => ['permission', $permList, userPermission()]],
	'Time Zone' => ['select' => ['timezone_id', $timezoneDef, $user->timezone_id]]];
$form->submit = $form->fieldsetLegend = UPDATE_SETTINGS;
echo htmlForm($form) . BR;

unset($form->errMsg);
if(isset($post->errMsg2))
	$form->errMsg = $post->errMsg2;
$form->id = 'changePassForm';
$form->fields = [
	'New Password'	=> ['text' => ['pass']],
	'Confirm New Password' => ['text' => ['confirm']]];
$form->submit = $form->fieldsetLegend = CHANGE_PASSWORD;
echo htmlForm($form) . BR;

h3("Form Notes:");
echo '<ul class="left">
	<li><b>Name</b>: Login username. Can be your first name, first &amp; last name/initials, your callsign,<br>
	or other name. Must be 2-24 alphanumeric characters. May contain spaces 
	<li><b>Email</b>: Optional. Not currently used but email features may be supported in the future
	<li><b>Location</b>: Optional. Your location eg. "Chicago, IL" or "Nottingham, UK"
	<li><b>Node #s</b>: Optional. Space separated list of AllStar node numbers you own or manage
	<li><b>Time Zone</b>: Default is UTC
	<li><b>Password</b>: Must be 6-16 printable ASCII characters
	</ul><br>
';

// Scheduled Connects (admin only). Same layout as the forms above, shown below the Form Notes
if(adminUser()) {
	$cron = schedCronRead();
	$mine = schedCronHasEntry($cron);
	$active = schedRunnerActive();
	if($mine)
		$st = 'Enabled. The cron entry is installed for the web server user.';
	elseif($active)
		$st = 'The scheduler last ran ' . (time() - filemtime(SCHED_HEARTBEAT)) . ' seconds ago, but it is not in this user\'s crontab.<br>'
			. 'It is running from another crontab (eg. root\'s or /etc/cron.d). If you just removed it, wait 3 minutes and reload.';
	elseif($cron === false)
		$st = 'Unavailable. The web server user cannot read or edit its crontab.<br>'
			. 'Check /etc/cron.allow and /etc/cron.deny, or add the line below<br>to root\'s crontab with "sudo crontab -e".';
	else
		$st = 'Disabled. The Schedule button is hidden on the main page.';
	$sform = new stdClass();
	$sform->class = 'left';
	$sform->id = 'schedulerForm';
	$sform->fieldsetLegend = 'Scheduled Connects';
	$sform->hdr = $st . '<br><small>Cron line: <code>' . htmlspecialchars(schedCronLine()) . '</code></small>';
	$sform->fields = [];
	$sform->submit = $mine ? DISABLE_SCHEDULER : (($cron !== false && !$active) ? ENABLE_SCHEDULER : []);
	echo htmlForm($sform) . BR;
}

asExit();
