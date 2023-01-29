<?php
// UserView.php class
// Author: David Gleason - AllScan.info
define('ADD_USER', 'Add User');
define('EDIT_USER', 'Edit User');
define('DELETE_USER', 'Delete User');

class UserView {

function showUsers($users, $nameHtml, $emailHtml, $locHtml, $nodenumsHtml, $llHtml, $permHtml, $tzHtml) {
	global $user, $html, $userModel, $timezoneDef;
	$hdrCols = [$nameHtml, $emailHtml, $locHtml, $nodenumsHtml, $llHtml, $permHtml, $tzHtml, 'ID'];
	$out = $html->tableOpen($hdrCols, null, 'favs', null);
	$nullVal = '-';
	foreach($users as $u) {
		if(isset($u->last_login) && $u->last_login) {
			$lastLogin = getTimestamp($u->last_login, $user->timezone_id, 'Y-m-d');
		} else {
			$lastLogin = $nullVal;
		}
		$loc = $u->location ? $u->location : $nullVal;
		// TBI: link node#'s to ASL stats
		$nn = empty($u->nodenums) ? $nullVal : implode(' ', $u->nodenums);
		$perm = $userModel->getPermissionName($u->permission);
		$row = [$u->name, $u->email, $loc, $nn, $lastLogin, $perm, $timezoneDef[$u->timezone_id], $u->user_id];
		$out .= $html->tableRow($row, null);
	}
	$out .= $html->tableClose();
	//$out = $html->div($out, 'center');
	echo $out . BR;
}

function showForms($newUser) {
	global $html, $user, $userModel, $timezoneDef;
	$form = new stdClass();
	if(isset($newUser->errMsg))
		$form->errMsg = $newUser->errMsg;
	// Get user from DB if edit request
	if($newUser->edit) {
		$newUser = $userModel->getUserById($newUser->user_id);
		if(isset($userModel->error))
			errMsg($userModel->error);
		$newUser->edit = true; // Restore attr
	}
	if(empty($user)) {
		h1("Welcome to AllScan - Initial Configuration");
		msg("No users have yet been created.");
		p("If you are an authorized admin of this server, create your user account now." . BR
		 ."Be sure to carefully read all Notes on this page before continuing.", null, false);
		$newUser->permission = PERMISSION_SUPERUSER;
		$permList = $userModel->getPermissionList(PERMISSION_SUPERUSER, PERMISSION_SUPERUSER);
	} else {
		// Users may not assign permissions > their own
		$maxPerm = userPermission();
		if($maxPerm > PERMISSION_SUPERUSER)
			$maxPerm = PERMISSION_SUPERUSER;
		$minPerm = PERMISSION_NONE;
		$permList = $userModel->getPermissionList($maxPerm, $minPerm);
		if(!isset($newUser->permission) || $newUser->permission < 0 || $newUser->permission > $maxPerm)
			$newUser->permission = PERMISSION_READ_ONLY;
	}
	// Fill in form fields array
	$form->fields = [
		'Name'		=> ['text'	=> ['name', $newUser->name]],
		'Email' 	=> ['text' 	=> ['email', $newUser->email]],
		'Password'  => ['text'	=> ['pass']],
		'Location'	=> ['text' =>  ['location', $newUser->location]],
		'Node #s'	=> ['text' => ['nodenums', implode(' ', $newUser->nodenums)]],
		'Permission' => ['select' => ['permission', $permList, $newUser->permission]],
		'Time Zone' => ['select' => ['timezone_id', $timezoneDef, $newUser->timezone_id]]];
	// If an edit request make appropriate adjustments to the form
	if($newUser->edit) {
		$form->submit = $form->fieldsetLegend = EDIT_USER;
		$form->id = 'editUserForm';
		$form->hiddenFields['user_id'] = $newUser->user_id;
		echo htmlForm($form) . BR;
	} else {
		// Display Add User Form
		if(userPermission() >= PERMISSION_FULL || empty($user)) {
			$form->submit = $form->fieldsetLegend = ADD_USER;
			$form->id = 'addUserForm';
			echo htmlForm($form) . BR;
		}
		// Display Edit request form, showing only users who can be edited by this user
		if(!empty($user)) {
			$where = superUser($user) ? "user_id != $user->user_id" : "permission < $user->permission";
			$users = $userModel->getUsers($where, null, PERMISSION_NONE);
			if(isset($userModel->error))
				errMsg($userModel->error);
			// If there are users that can be edited display edit/delete form
			if(_count($users)) {
				unset($form->errMsg, $form->note);
				$userList = [];
				foreach($users as $u)
					$userList[$u->user_id] = "$u->name [$u->user_id]";
				// Order user list alphabetically
				asort($userList);
				$form->fieldsetLegend = EDIT_USER;
				if(userPermission() > PERMISSION_FULL)
					$form->submit = [EDIT_USER, DELETE_USER];
				else
					$form->submit = EDIT_USER;
				$form->id = 'editUserForm';
				$form->fields = ['Name [ID]' => ['select'=> ['user_id', $userList]]];
				echo htmlForm($form);
			}
		}
	}

	h3("Form Notes");
	echo '<ul class="left w600">
	<li><b>Name</b>: Login username. Can be user\'s first name, first &amp; last name/initials, callsign,
		or other name. Must be 2-24 alphanumeric characters. May contain spaces
	<li><b>Email</b>: Optional. Not currently used but email features may be supported in the future
	<li><b>Password</b>: Must be 6-16 printable ASCII characters
	<li><b>Location</b>: Optional. User location eg. "Chicago, IL" or "Nottingham, UK"
	<li><b>Node #s</b>: Optional. Space separated list of AllStar node numbers user owns or manages
	<li><b>Permission</b>:<br>
		&ensp; &bullet; Read-Only: Cannot connect/disconnect nodes or add/delete favorites<br>
		&ensp; &bullet; Read/Modify: Can execute above functions only<br>
		&ensp; &bullet; Full: Can view Cfgs and add/edit &lt; Full permission user accounts<br>
		&ensp; &bullet; Admin: Can edit Cfgs and add/edit &lt; Admin permission user accounts<br>
		&ensp; &bullet; Superuser: Full access to all functions
	<li><b>Time Zone</b>: User\'s Time Zone
	</ul><br>
	';

	if(!empty($user))
		return;

	h3("Initial Configuration Notes");
	echo '<ul class="left w600">
	<li>AllScan defaults public (not logged-in) users to Read-Only access. This can be changed to
		None (no public access), Read/Modify, or to Full (no logins needed). To change
		this setting, Login, click the "Cfgs" link, and edit the "Public Permission" parameter.
	<li>If there will be more than one administrator it is recommended that each have a
		separate account with their name or callsign for the Name.
	<li>Additional user accounts can be created and edited on the "Users" page.
	<li>Your user account settings can be changed on the "Settings" page.
	<li>Do not lose your Login Name and Password. Admin passwords can be changed only by another
		Superuser or via SSH.
	</ul><br>
	';

	showFooterLinks();
}

}
