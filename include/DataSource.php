<?php
// DataSource.php class for use with SQLite
// Author: David Gleason - AllScan.info

class DataSource {

private $db;
private $lastQueryCount = 0;
private $dbName;
public  $error;

function connectToDb($dbName) {
	$this->dbName = $dbName;
	$this->db = new SQLite3($dbName);
	if(!$this->db) {
		$this->setError('Connect');
		return false;
	}
	// Wait up to 2 secs for DB file to be unlocked if in use by another client
	if(!$this->db->busyTimeout(2000)) {
		$this->setError('setBusyTimout');
		return false;
	}
	return true;
}
function closeDb() {
	if(isset($this->db)) {
		$this->db->close();
		unset($this->db);
		$this->lastQueryCount = 0;
	}
}
function getRecordSet($sql) {
	if(!isset($this->db))
		return null;
	$recordSet = $this->db->query($sql);
	if(!$recordSet) {
		$this->setError("Query ($sql)");
		$this->lastQueryCount = 0;
		return null;
	}
	if(preg_match('/^(SELECT|SHOW|EXPLAIN)/', $sql, $matches) == 1) {
		// Apparently there's no way to get the returned row count from SQLite3
		// other than loop through results or do separate COUNT query
		//$this->lastQueryCount = $recordSet->num_rows;
	} else {
		$this->lastQueryCount = $this->db->changes();
	}	
	return $recordSet;
}
function exec($sql) {
	if(!isset($this->db))
		return null;
	$res = $this->db->exec($sql);
	if(!$res) {
		$this->setError("Exec ($sql)");
		$this->lastQueryCount = 0;
		return false;
	}
	$this->lastQueryCount = $this->db->changes();
	return true;
}
/* function getLastQueryCount() {
	return $this->lastQueryCount;
} */
function getRowsAffected() {
	return $this->lastQueryCount;
}
function getLastInsertId() {
	return $this->db->lastInsertRowID();
}

function escapeString($string) {
	$escaped = $this->db->escapeString($string);
	if($this->db->error)
		$this->setError(__METHOD__);
	if(!$escaped)
		return $string;
	return $escaped;
}
function escapeArray($arr) {
	foreach($arr as &$string)
		$string = $this->escapeString($string);
	return $arr;
}

private function setError($errTypeStr) {
	$this->error = "Error($errTypeStr) errno " . $this->db->lastErrorCode() . ", desc " . $this->db->lastErrorMsg();
}

}
