<?php
// UserModel.php class
// Author: David Gleason - AllScan.info

// Permissions definitions
// Read-Only users cannot execute any form actions but can see the Connection Status and Favorites Tables.
// Read-Modify users can execute all AllScan functions other than adding/editing additional user accounts.
// Full permission users can execute all AllScan functions and can add/edit Read-Only additional user accounts.
// Admin users can execute all AllScan functions but can add/edit only <= Full permission additional users.
// Superusers can create additional users with any permission level.
define('PERMISSION_NONE', 0);
define('PERMISSION_READ_ONLY', 2);
define('PERMISSION_READ_MODIFY', 4);
define('PERMISSION_FULL', 6);
define('PERMISSION_ADMIN', 10);
define('PERMISSION_SUPERUSER', 14);

$cookieUseRootPath = false;
$cookieSameSiteOpt = 'Lax'; // None/Lax/Strict

function userPermission($u=null) {
	global $user;
	if(!$u) {
		if(empty($user))
			return PERMISSION_NONE;
		$u = $user;
	}
	return $u->permission ?? PERMISSION_NONE;
}

class UserModel {
const	TABLENAME = 'user';
public  $db;
public  $error;
private $permissionList = [
	PERMISSION_NONE			=> 'None (Login Disabled)',
	PERMISSION_READ_ONLY	=> 'Read Only',
	PERMISSION_READ_MODIFY	=> 'Read/Modify',
	PERMISSION_FULL			=> 'Full',
	PERMISSION_ADMIN		=> 'Admin',
	PERMISSION_SUPERUSER	=> 'Superuser'];

function __construct($db) {
	$this->db = $db;
}

function getUsers($where=null, $orderBy=null, $minPermission=PERMISSION_NONE) {
	if($minPermission > PERMISSION_NONE) {
		if($where)
			$where = "permission >= $minPermission AND ($where)";
		else
			$where = "permission >= $minPermission";
	}
	$users = $this->db->getRecords(self::TABLENAME, $where, $orderBy);
	$this->checkDbError(__METHOD__);
	if(!_count($users))
		return null;
	foreach($users as &$u) {
		// Validate last login date
		if($u->last_login < 1672964000) // Jan. 5, 23
			$u->last_login = 0;
		// Convert array parms
		$u->nodenums = csvToArray($u->nodenums);
	}
	unset($u);
	return $users;
}

function getUser($email, $minPermission=PERMISSION_NONE) {
	if(!$this->validateEmailAddress($email))
		return null;
	$where = "`email`='$email'";
	$users = $this->getUsers($where, null, $minPermission);
	if(is_array($users) && count($users))
		return($users[0]);
	return null;
}
function getUserById($user_id, $minPermission=PERMISSION_NONE) {
	if(!validDbID($user_id))
		return null;
	$where = "user.user_id=$user_id";
	$users = $this->getUsers($where, null, $minPermission);
	if(is_array($users) && count($users))
		return($users[0]);
	return null;
}
function add($u) {
	if(!$this->validateFields($u))
		return null;
	if($u->pass) {
		if(!$this->validatePassword($u->pass))
			return null;
	} else {
		// Generate passwd if none specified
		$u->pass = $this->generatePassword();
		msg("Generated password for user \"$u->name\": \"$u->pass\". User will not be able to login without this.");
	}
	$u->hash = password_hash($u->pass, PASSWORD_BCRYPT);
	checkIntVals($u, ['timezone_id', 'last_login']);
	// Convert arrays to csv
	$u->nodenums = arrayToCsv($u->nodenums);
	$cols = ['name', 'hash', 'email', 'location', 'nodenums', 'permission', 'timezone_id'];
	$vals = [$u->name, $u->hash, $u->email, $u->location, $u->nodenums, $u->permission, $u->timezone_id];
	$retval = $this->db->insertRow(self::TABLENAME, $cols, $vals);
	$this->checkDbError(__METHOD__);
	return $retval;
}
function update($u) {
	if(!validDbID($u->user_id))
		return null;
	if(!$this->validateFields($u))
		return null;
	checkIntVals($u, ['timezone_id', 'last_login']);
	// Convert arrays to csv
	$u->nodenums = arrayToCsv($u->nodenums);
	$cols = ['name', 'email', 'location', 'nodenums', 'permission', 'timezone_id'];
	$vals = [$u->name, $u->email, $u->location, $u->nodenums, $u->permission, $u->timezone_id];
	if($u->last_login) {
		$cols[] = 'last_login';
		$vals[] = $u->last_login;
	}
	if(!empty($u->pass)) {
		if($this->validatePassword($u->pass)) {
			$cols[] = 'hash';
			$vals[] = password_hash($u->pass, PASSWORD_BCRYPT);
		} else {
			unset($this->error);
		}
	}
	$retval = $this->db->updateRow(self::TABLENAME, $cols, $vals, "user_id=$u->user_id");
	$this->checkDbError(__METHOD__);
	return $retval;
}
function validateFields($u) {
	if(!$this->validateName($u->name))
		return null;
	if($u->email && !$this->validateEmailAddress($u->email))
		return null;
	if($u->location && !$this->validateLocation($u->location))
		return null;
	if(!empty($u->nodenums) && !$this->validateNodenums($u->nodenums))
		return null;
	return true;
}
function delete($u) {
	if(!validDbID($u->user_id))
		return null;
	$retval = $this->db->deleteRows(self::TABLENAME, "user_id=$u->user_id");
	if($retval)
		okMsg("Deleted user OK");
	$this->checkDbError(__METHOD__);
	return $retval;
}

function getCount($where=null) {
	$retval = $this->db->getRecordCount(self::TABLENAME, $where);
	if(isset($this->db->error)) {
		$this->error = __METHOD__ .': '. $this->db->error;
		unset($this->db->error);
	}
	return $retval;
}

// Validate user login cookie. Not for processing login forms
function validate() {
	$u = arrayToObj($_COOKIE, ['name', 'cpass', 'lexp']);
	if(!isset($u->name) || !isset($u->cpass) || !$u->name || !$u->cpass)
		return null;
	if(!$this->validateName($u->name))
		return null;
	$users = $this->getUsers("name='$u->name'");
	if(!$users || $this->error)
		return null;
	foreach($users as $user) {
		if($user->permission <= PERMISSION_NONE) {
			$this->error = 'Access Denied (account is currently disabled)';
		} elseif($u->cpass === $this->cpass($user->hash)) {
			unset($this->error);
			break;
		}
		$this->error = 'Access Denied';
	}
	if(isset($this->error))
		return null;
	if(!isset($_GET['export']) && isset($_COOKIE['name']) && isset($_COOKIE['cpass'])) {
		// Periodically refresh cookies if expire in 15-35 days
		$age = ($u->lexp > 0) ? ($u->lexp - time()) / 86400.0 : 0;
		if($u->lexp <= 0 || ($age >= 15 && $age <= 35)) {
			$this->setLoginCookies($u->name, $u->cpass, true);
		}
	}
	$user->ip_addr = $_SERVER['REMOTE_ADDR'];
	if(!validIpAddr($user->ip_addr))
		$user->ip_addr = 'Unknown';
	// Update last login time / last IP Addr if changed
	$this->updateUserStats($user);
	return $user;
}

// Process login form submission
function validateLogin($name, $pass, $remember) {
	usleep(rand(20000,500000)); // Prevent response time hack
	if(!$this->validateName($name)) {
		$this->error = 'Invalid Name / Call Sign';
		return false;
	}
	if(!$this->validatePassword($pass)) {
		$this->error = 'Invalid Password';
		return false;
	}
	$users = $this->getUsers("name='$name'");
	if($this->error)
		return false;
	if(!$users) {
		$this->error = 'Invalid Login Name';
		return false;
	}
	foreach($users as $u) {
		if($u->permission <= PERMISSION_NONE) {
			$this->error = 'Access Denied (account is currently disabled)';
		} elseif(password_verify($pass, $u->hash)) {
			unset($this->error);
			$user = $u;
			break;
		}
		$this->error = 'Access Denied';
	}
	if(isset($this->error))
		return false;
	// Send cookies
	$this->setLoginCookies($user->name, $this->cpass($user->hash), $remember);
	return true;
}

function setLoginCookies($name, $cpass, $remember) {
	global $urlbase, $cookieSameSiteOpt, $cookieUseRootPath;
	// If 'remember me' set, set cookie for 45 days, otherwise 8 hours
	$exp = time() + ($remember ? 45*86400 : 8*3600);
	$path = $cookieUseRootPath ? '/' : "$urlbase/"; // eg. /allscan/
	// PHP setcookie only supports opts array in >= v7.3.0
	if(PHP_VERSION_ID >= 70300) {
		$opts = ['expires'=>$exp, 'path'=>$path, 'samesite'=>$cookieSameSiteOpt];
		setcookie("name", $name, $opts);
		setcookie("cpass", $cpass, $opts);
		setcookie("lexp", $exp, $opts);
	} else {
		// For < 7.3 PHP versions SameSite parm can be appended to the path parm
		$path .= '; SameSite=' . $cookieSameSiteOpt;
		setcookie("name", $name, $exp, $path);
		setcookie("cpass", $cpass, $exp, $path);
		setcookie("lexp", $exp, $exp, $path);
    }
}

function logout() {
	global $urlbase;
	setcookie("name", '', 1, "$urlbase/");
	setcookie("cpass", '', 1, "$urlbase/");
	setcookie("lexp", '', 1, "$urlbase/");
	// Temp TBR: Also clear cookies at old path (pre-v0.52)
	if($urlbase) {
		setcookie("name", '', 1, "/");
		setcookie("cpass", '', 1, "/");
		setcookie("lexp", '', 1, "/");
	}
}

private function updateUserStats($user) {
	if(!isset($user) || !validDbID($user->user_id))
		return;
	$llChanged = (diffSecs($user->last_login) >= 3600);
	$ipChanged = ($user->ip_addr !== $user->last_ip_addr);
	if(!$llChanged && !$ipChanged)
		return true;

	$cols = ['last_login'];
	$vals = [time()];
	if($ipChanged) {
		$cols[] = 'last_ip_addr';
		$vals[] = $user->ip_addr;
	}
	$ret = $this->db->updateRow(self::TABLENAME, $cols, $vals, "user_id=$user->user_id");
	$this->checkDbError(__METHOD__);
	return $ret;
}

function getPermissionList($maxPermission=PERMISSION_READ_ONLY, $minPermission=PERMISSION_NONE) {
	$val = [];
	for($i=$minPermission; $i <= $maxPermission; $i++) {
		if(isset($this->permissionList[$i]))
			$val[$i] = $this->permissionList[$i];
	}
	return $val;
}
function getPermissionName($pval) {
	if($pval < PERMISSION_NONE)
		return null;
	if($pval > PERMISSION_SUPERUSER)
		$pval = PERMISSION_SUPERUSER;
	return $this->permissionList[$pval];
}
// Below called from Settings page
function changePassword($post) {
	global $user;
	if($post['pass'] !== $post['confirm']) {
		$this->error = 'Passwords do not match';
		return false;
	}
	if(!$this->validatePassword($post['pass']))
		return false;
	$user->pass = $post['pass'];
	$this->update($user);
	return !$this->checkDbError(__METHOD__);
}

function validateEmailAddress($email) {
	if(!validEmail($email)) {
		$this->error = 'Improperly formatted Email address';
		return false;
	}
	return true;
}
function validateName($name) {
	if(preg_match('/^[a-zA-Z0-9-. ]{2,24}$/', $name) != 1) {
		$this->error = 'Improperly formatted Name. Must be 2-24 Alphanumeric characters';
		return false;
	}
	return true;
}
function validateLocation($loc) {
	if(preg_match('|^[a-zA-Z0-9-.,/#& ]{2,64}$|', $loc) != 1) {
		$this->error = 'Improperly formatted Location. Must be 2-64 Alphanumeric characters';
		return false;
	}
	return true;
}
function validateNodenums($nodenums) {
	foreach($nodenums as $n) {
		if($n < 1 || $n >= 5000000) {
			$this->error = "Improperly formatted node list. '$n' is not a valid node number";
			return false;
		}
	}
	return true;
}
function validatePassword($p) {
	if(!$p || strlen($p) < 6 || preg_match('/^[a-zA-Z0-9~!@#$^&*,._-]{6,16}$/', $p) != 1) {
		$this->error = 'Invalid Password. Must be 6-16 printable ASCII characters';
		return false;
	}
	return true;
}
private function generatePassword($length=10) {
	$letters = '1234567890abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ._-';
	$randomString = '';
	$len = strlen($letters);
	for($i=0; $i < $length; $i++)
		$randomString .= $letters[rand(0, $len-1)];
	return $randomString;
}
private function cpass($p) {
	return hash('sha256', $p);
}
function checkDbError($method, $extraTxt='') {
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
