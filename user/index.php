<?php
// User Module controller
// Author: David Gleason - AllScan.info
require_once('../include/common.php');
require_once('UserView.php');
$html = new Html();
$msg = [];

// Init base cfgs
asInit($msg);
// Init DB (exits on error)
$db = dbInit();
// Validate/create required DB tables. Returns count of users in user table (exits on error)
$userCnt = checkTables($db, $msg);
// Init gCfgs (exits on error)
$cfgModel = new CfgModel($db);

// Get request parms. Make sure email is lower case
$parms = getRequestParms();
if(isset($parms['email']))
	$parms['email'] = strtolower($parms['email']);

$view = new UserView();
$userModel = new UserModel($db);

// Handle Login
if(isset($_POST['name']) && isset($_POST['pass']) && $_POST['pass'] && $_POST['Submit'] !== ADD_USER) {
	processLogin($_POST); // Does not return. Redirects to main page if login OK, or displays login failed message
}

// Validate user
if($userCnt) {
	$user =	$userModel->validate();
	// If user not logged in, show Login Form
	if(empty($user) || !writeOk())
		showLoginForm($_GET); // Does not return
	// Handle Logout
	if($user && isset($_GET['logout']))
		processLogout($_GET); // Does not return
}

pageInit();
if($userCnt)
	h1("User Administration");

// Process Add/Edit/Delete requests
if(isset($_POST['Submit']) && (writeOk() || !$userCnt)) {
	$newUser = processForm($_POST);
	$userCnt = $userModel->getCount();
}
if(empty($user) && $userCnt)
	redirect('user/');

if(!isset($newUser)) {
	$newUser = (object)['name'=>'', 'email'=>'', 'location'=>'', 'nodenums'=>[], 'timezone_id'=>0,
		'permission'=>PERMISSION_READ_ONLY, 'edit'=>false];
}

if(isset($newUser->confirmDelete)) {
	echo confirmForm('Confirm Delete User',
			htmlspecialchars("Permanently delete user $newUser->name [UserID $newUser->user_id]?"),
			['user_id'=>$newUser->user_id, 'confirm'=>'1'], null, false, [DELETE_USER, 'Cancel']);
	asExit();
}

// Show Users List
if(!$newUser->edit && $userCnt) {
	h2('User Accounts');
	showUsers($parms);
}

// Show Add / Edit forms
if(!isset($userModel->error) && (writeOk() || !$userCnt))
	$view->showForms($newUser);

asExit();

//-------- functions --------
function showUsers($parms) {
	global $userModel, $view;
	// If Submit set clear parms to prevent sort links acting as forms
	if(isset($parms['Submit'])) {
		unset($parms);
	}
	// Sort by specified column & direction or default to last login DESC
	if(isset($parms['sortCol']) && strlen($parms['sortCol']) == 1)
		$sortCol = $parms['sortCol'];
	else
		$sortCol = 'l';

	if( (isset($parms['sortOrd']) && $parms['sortOrd'] === 'd') ||
		(!isset($parms['sortOrd']) && $sortCol === 'l') )
		$sortAsc = false;
	else
		$sortAsc = true;

	// Build ORDER BY clause. Use array to support ordering by multiple cols
	switch($sortCol) {
		case 'n': $order = ['n']; break;
		case 'e': $order = ['e']; break;
		case 'L': $order = ['L']; break;
		case 'p': $order = ['p']; break;
		case 't': $order = ['t']; break;
		default: $order = ['l']; break;
	}
	$ord = [];
	$n = 0;
	foreach($order as $n => $sc) {
		switch($sc) {
			case 'n': $ord[$n] = 'name'; break;
			case 'e': $ord[$n] = 'email'; break;
			case 'L': $ord[$n] = 'location'; break;
			case 'p': $ord[$n] = 'permission'; break;
			case 't': $ord[$n] = 'timezone_id'; break;
			default: $ord[$n] = 'last_login'; break;
		}
		if(!$sortAsc)
			$ord[$n] .= ' DESC';
		$n++;
	}
	$orderBy = implode(',', $ord);

	$where = null;
	$users = $userModel->getUsers($where, $orderBy, PERMISSION_NONE);
	if(isset($userModel->error)) {
		errMsg($userModel->error);
		return;
	}
	if(!_count($users)) {
		h3("No Users found");
		echo BR;
		return;
	}

	// Sort by name [n], email [e], location [L], last login [l] (default), timezone [t]
	$hdrCols = ['Name', 'Email', 'Location', 'Last Login', 'Permission', 'Time Zone'];
	if(count($users) > 1) {
		$colKey = ['n', 'e', 'L', 'l', 'p', 't'];
		foreach($hdrCols as $key => &$col) {
			$ck = $colKey[$key];
			if($sortCol === $ck) {
				// Link to sort in opposite order
				$col = getSortLink($parms, $sortCol, !$sortAsc, $col);
				// Show an arrow indicating current sort col and direction
				$col .= upDownArrow($sortAsc);
			} else {
				// Link to sort in ASC order (or DESC order for login time)
				$col = getSortLink($parms, $ck, ($ck !== 'l'), $col);
			}
		}
	}
	$view->showUsers($users, $hdrCols[0], $hdrCols[1], $hdrCols[2], 'Node #s', $hdrCols[3], $hdrCols[4], $hdrCols[5]);
}

function processForm($parms) {
	global $userModel, $userCnt;
	$Submit = $parms['Submit'];
	$newUser = arrayToObj($parms, ['user_id', 'name', 'email', 'location', 'nodenums', 'pass', 'permission', 'timezone_id']);
	$confirm = $_POST['confirm'] ?? null;
	$newUser->edit = ($Submit === EDIT_USER);
	$newUser->nodenums = isset($newUser->nodenums) ? parseIntList($newUser->nodenums) : [];
	if($Submit === DELETE_USER && $newUser->user_id) {
		$newUser = $userModel->getUserById($newUser->user_id, PERMISSION_NONE);
		if(!$newUser) {
			errMsg('User not found');
		} elseif($confirm == 1) {
			$userLevel = userPermission();
			$newUserLevel = userPermission($newUser);
			if($userLevel >= PERMISSION_ADMIN && $userLevel > $newUserLevel) {
				// Delete User
				if($userModel->delete($newUser))
					return null;
				errMsg('Delete User Failed: ' . $userModel->error);
				unset($userModel->error);
			} else {
				p("Invalid permissions [$userLevel/$newUserLevel]", 'error');
			}
		} else {
			$newUser->confirmDelete = true;
		}
	}
	// Process Add/Edit Request
	if($Submit === ADD_USER || ($newUser->edit && !empty($newUser->name))) {
		// Get existing user data if edit request
		if($newUser->edit) {
			$user0 = $userModel->getUserById($newUser->user_id, PERMISSION_DELETED);
			if(!isset($newUser->permission))
				$newUserLevel = userPermission($user0);
		}
		// Verify user has permission to add/edit this user. To prevent there being too many Full permission users,
		// require that user can only add users with lower permissions or user class than their own
		$userLevel = userPermission();
		if(!isset($newUserLevel)) {
			$newUserLevel = userPermission($newUser);
		}
		$ulDiff = $userLevel - $newUserLevel;
		// Check permissions for branch users and for Step 2 or 3 of the Add/Edit User form
		if( $userCnt && userPermission() < PERMISSION_SUPERUSER &&
			($ulDiff < 0 || $newUser->permission < PERMISSION_NONE || $newUser->permission > userPermission()))
			$newUser->errMsg = "Invalid permissions [$userLevel/$newUserLevel]";
		elseif(!$newUser->edit && $newUser->permission <= PERMISSION_NONE)
			$newUser->errMsg = 'Cannot create new user with no permissions';
		elseif($newUser->edit && !$user0)
			$newUser->errMsg = 'User not found';
		elseif(!$userModel->validateFields($newUser)) {
			$newUser->errMsg = $userModel->error;
			unset($userModel->error);
		} elseif(!$newUser->edit && !$userModel->validatePassword($newUser->pass)) {
			$newUser->errMsg = $userModel->error;
			unset($userModel->error);
		} elseif($newUser->edit) {
			// Below can return 0 [rows affected] if user submits form with no changes
			if($userModel->update($newUser) === null) {
				errMsg('Edit User Failed: ' . $userModel->error);
				unset($userModel->error);
			} else {
				okMsg('Edit User Successful');
				unset($newUser);
			}
		} else {
			$ret = $userModel->add($newUser);
			if(!$ret) {
				errMsg('Add User Failed: '  . $userModel->error);
				unset($userModel->error);
			} else {
				okMsg('Add User Successful');
				unset($newUser);
			}
		}
	}
	return $newUser;
}

function showLoginForm($parms) {
	global $html;
	pageInit('', false);
	$form = new stdClass();
	$form->submit = $form->fieldsetLegend = 'Log In';
	$form->fields = [];
	$form->fields['User Name / Call Sign'] = ['text' => ['name', $parms->name ?? '']];
	$form->fields['Password'] = ['password' => ['pass']];
	$form->fields['Remember Me'] = ['checkbox' => ['remember']];
	echo htmlForm($form) . BR;
	asExit();
}

function processLogin($parms) {
	global $html, $userModel, $asdir;
	$loginOK = $userModel->validateLogin($parms['name'], $parms['pass'], $parms['remember']);
	if($loginOK && !isset($userModel->error))
		redirect();
	// else login failed
	sleep(2); // Slow crack attempts
	if(isset($userModel->error)) {
		$msg = 'Login Error';
		$desc = $userModel->error;
	} else {
		$msg = 'Login Failed';
		$desc = 'Invalid Name / Call Sign / Password';
	}
	pageInit('', false);
	h1('Login Results');
	p($desc, 'error');
	asExit();
}

function processLogout($parms) {
	global $userModel, $asdir;
	$userModel->logout();
	redirect();
}
