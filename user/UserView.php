<?php
define('ADD_USER', 'Add User');
define('EDIT_USER', 'Edit User');
define('DELETE_USER', 'Delete User');
define('RESET_PASSWORD', 'Reset User Password');

class UserView {

function showUsers($users, $nameHtml, $emailHtml, $locHtml, $llHtml, $nodenumsHtml, $permHtml, $tzHtml) {
	global $user, $html, $userModel, $timezoneDef;
	$hdrCols = [$nameHtml, $emailHtml, $locHtml, $llHtml, $nodenumsHtml, $permHtml, $tzHtml];
	$out = $html->tableOpen($hdrCols, null, 'results', null);
	$nullVal = '-';
	foreach($users as $u) {
		if(isset($u->last_login) && $u->last_login) {
			$lastLogin = getTimestamp($u->last_login, $user->timezone_id, 'Y-m-d');
		} else {
			$lastLogin = $nullVal;
		}
		$loc = $u->location ? $u->location : $nullVal;
		$nn = $u->nodenums ? $u->nodenums : $nullVal;
		$perm = $userModel->getPermissionName($u->permission);
		$row = [$u->name, $u->email, $loc, $lastLogin, $nn,	$perm, $timezoneDef[$tzid]];
		$out .= $html->tableRow($row, null);
	}
	$out .= $html->tableClose();
	$out = $html->div($out, 'center') . BR;
	echo $out;
}

// Called only if user permissions >= READ_MODIFY
function showForms($newUser) {
	global $html, $user, $userModel, $timezoneDef;
	$form = new stdClass();
	if(strpos(getScriptName(), '/user/') === false)
		$form->url = 'user/';
	$form->class = 'left';
	if(isset($newUser->errMsg))
		$form->errMsg = $newUser->errMsg;
	// Get user from DB if edit request
	if(isset($newUser->edit)) {
		$newUser = $userModel->getUserById($newUser->user_id);
		if($userModel->error)
			errMsg($userModel->error);
		$newUser->edit = true; // Restore attr cleared by above call
	}
	// Users may not assign permissions > their own
	$maxPerm = $user->permission;
	if($maxPerm > PERMISSION_SUPERUSER)
		$maxPerm = PERMISSION_SUPERUSER;
	// Get permissions list
	$permList = $userModel->getPermissionList($maxPerm, PERMISSION_READ_ONLY);
	// Do not allow new user permissions to be set > max permission level defined above. Default to Read Only
	if(!isset($newUser->permission) || $newUser->permission < 0 || $newUser->permission > $maxPerm)
		$newUser->permission = PERMISSION_READ_ONLY;
	// Fill in form fields array
	//$cols = ['name', 'hash', 'email', 'location', 'nodenums', 'permission', 'timezone_id'];
	$form->fields = [
		'Name'		=> ['text'	=> ['name', $newUser->name]],
		'Password'  => ['text'	=> ['pass']],
		'Email' 	=> ['text' 	=> ['email', $newUser->email]],
		'Location'	=> ['text' =>  ['location', $newUser->location]],
		'Node #s'	=> ['text' => ['nodenums', $newUser->nodenums]],
		'Permission' => ['select' => ['permission', $permList, $newUser->permission]],
		'Time Zone' => ['select' => ['timezone_id', $timezoneDef, $newUser->timezone_id]]];
	// If an edit request make appropriate adjustments to the form
	if(isset($newUser->edit)) {
		$form->submit = $form->fieldsetLegend = EDIT_USER;
		$form->id = 'editUserForm';
		unset($form->fields['Password']);
		$form->hiddenFields['user_id'] = $newUser->user_id;
		echo htmlForm($form) . BR;
	} else {
		// Display Add User Form
		if($user->permission >= PERMISSION_FULL) {
			$form->submit = $form->fieldsetLegend = ADD_USER;
			$form->id = 'addUserForm';
			echo htmlForm($form);
		}
		// Display Edit request form, showing only users who can be edited by this user
		$where = "permission < $user->permission";
		$users = $userModel->getUsers($where, null, PERMISSION_NONE);
		if(isset($userModel->error))
			errMsg($userModel->error);
		// If there are users that can be edited display edit/delete/reset password form
		if(_count($users)) {
			unset($form->errMsg, $form->note);
			$userList = [];
			foreach($users as $u) {
				$userList[$u->user_id] = "$u->name <$u->email>";
				if($newUser->user_id == $u->user_id)
					$newUser = $u;
			}
			// Order user list alphabetically
			asort($userList);
			$form->fieldsetLegend = EDIT_USER .' / '. RESET_PASSWORD;
			if($user->permission >= PERMISSION_FULL)
				$form->submit = [EDIT_USER, DELETE_USER, RESET_PASSWORD];
			else
				$form->submit = [EDIT_USER, RESET_PASSWORD];
			$form->id = 'editUserForm';
			$label = htmlspecialchars('Name <Email>');
			$form->fields = [$label => ['select'=> ['user_id', $userList]]];
			echo htmlForm($form);
		}
	}
	return;
}

}
