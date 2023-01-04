<?php 
// DB.php class
// Author: David Gleason
require_once('DataSource.php');

class DB {

private $connected = false;
private $dataSrc = null;
private $dbName;
public  $error; // Set to a text message upon error

// constructor
function DB($dbName) {
	$this->dbName = $dbName;
}

// init() is called automatically the first time a query is done
function init() {
	if(!$this->dataSrc) {
		$this->dataSrc = new DataSource();
		if($this->dataSrc->connectToDb($this->dbName))
			$this->connected = true;
		else
			$this->error = $this->dataSrc->error;
	}
	return $this->connected;
}

function getRecordCount($table, $where=null) {
	if(!$this->init())
		return null;
    $query = "SELECT COUNT(*) FROM `$table`";
	if($where)
		$query .= " WHERE $where";
	$result = $this->dataSrc->getRecordSet($query);
	if(!$result) {
		$this->error = $this->dataSrc->error;
		return null;
	}
	$row = $result->fetch_row();
	if(!$row)
		return null;
	return $row[0];
}
function getMaxFieldVal($table, $field, $where=null) {
	if(!$this->init())
		return null;
    $query = "SELECT MAX(`$field`) FROM `$table`";
	if($where)
		$query .= " WHERE $where";
	$result = $this->dataSrc->getRecordSet($query);
	if(!$result) {
		$this->error = $this->dataSrc->error;
		return null;
	}
	$row = $result->fetch_row();
	if(!$row)
		return null;
	return $row[0];
}
function getDistinctFieldList($table, $field, $where=null, $orderBy=null) {
	$select = "DISTINCT `$field`";
	if($orderBy === null)
		$orderBy = "`$field` ASC";
	$rows = $this->getRecords($table, $where, $orderBy, null, $select);
	if($rows === null)
		return null;
	$list = [];
	foreach($rows as $li) {
		$list[$li->$field] = $li->$field;
	}
	return $list;
}
function getRecords($table, $where=null, $orderBy=null, $limit=null, $select='*',
					$join=null, $groupBy=null) {
	if(!$this->init())
		return null;
	$query = "SELECT $select FROM `$table`";
	if($join)
		$query .= " $join";
	if($where)
		$query .= " WHERE $where";
	if($groupBy)
		$query .= " GROUP BY $groupBy";
	if($orderBy)
		$query .= " ORDER BY $orderBy";
	if($limit)
		$query .= " LIMIT $limit";
	$result = $this->dataSrc->getRecordSet($query);
	if(!$result) {
		$this->error = $this->dataSrc->error;
		return null;
	}
	$rows = [];
	while(($row = $result->fetch_object())) {
		$rows[] = $row;
	}
	return $rows;
}
function getRecord($table, $where=null, $orderBy=null, $select='*', $join=null) {
	$row = $this->getRecords($table, $where, $orderBy, 1, $select, $join);
	if(empty($row))
		return null;
	return $row[0];
}
// Returns id of the new object or null on error
// (Check retval with '=== null' in case a new table's first id is 0)
function insertRow($table, $cols, $vals) {
	if(!$this->init())
		return null;
	$vals = $this->dataSrc->escapeArray($vals);
	$cols = "`" . implode("`, `", $cols) . "`"; 
	$vals = "'" . implode("', '", $vals) . "'";
	$vals = $this->unquoteSqlFunctions($vals);
	$query = "INSERT into `$table` ($cols) values ($vals)";
	if(!$this->dataSrc->getRecordSet($query)) {
		$this->error = $this->dataSrc->error;
		return null;
	}
	return $this->getLastInsertId();
}
function updateRow($table, $cols, $vals, $where) {
	if(!$this->init())
		return null;
	$vals = $this->dataSrc->escapeArray($vals);
	for($i=0; $i < count($cols); $i++)
		$parms[] = "`{$cols[$i]}`='{$vals[$i]}'";
	$parms = implode(", ", $parms); 
	$parms = $this->unquoteSqlFunctions($parms);
	$query = "UPDATE `$table` SET $parms WHERE $where";
	$result = $this->dataSrc->getRecordSet($query);
	if($this->dataSrc->error)
		$this->error = $this->dataSrc->error . $query;
	return $result;
}
function deleteRows($table, $where) {
	if(!$this->init())
		return null;
	$query = "DELETE FROM `$table` WHERE $where";
	$result = $this->dataSrc->getRecordSet($query);
	if($this->dataSrc->error)
		$this->error = $this->dataSrc->error;
	return $result;
}
function getRecordSet($strSql) {
	if(!$this->init())
		return null;
	$result = $this->dataSrc->getRecordSet($strSql);
	if($this->dataSrc->error)
		$this->error = $this->dataSrc->error;
	return $result;
}

function getLastQueryCount() {
	if(!$this->init())
		return null;
	return $this->dataSrc->getLastQueryCount();
}
function getLastInsertId() {
	if(!$this->init())
		return null;
	return $this->dataSrc->getLastInsertId();
}

function unquoteSqlFunctions($vals) {
	$search = array("'NOW()'", "'NULL'");
	$replace = array('NOW()', 'NULL');
	$vals = str_replace($search, $replace, $vals);
	return $vals;
}

function __destruct() {
	if(!$this->dataSrc)
		return;
	$this->dataSrc->closeDb();
	unset($this->dataSrc);
	$this->connected = false;
}

}
