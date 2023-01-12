<?php 
// DB.php class
// Author: David Gleason - AllScan.info
require_once('DataSource.php');

class DB {

private $connected = false;
private $dataSrc = null;
private $dbName;
public  $error; // Set to a text message upon error

function __construct($dbName) {
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

function getRecords($table, $where=null, $orderBy=null, $limit=null, $select='*', $join=null, $groupBy=null) {
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
	while(($row = $this->fetchObject($result))) {
		$rows[] = $row;
	}
	return $rows;
}
function fetchObject($res) {
	$row = $res->fetchArray(SQLITE3_ASSOC);
	return empty($row) ? null : (object)$row;
}
function getRecord($table, $where=null, $orderBy=null, $select='*', $join=null) {
	$row = $this->getRecords($table, $where, $orderBy, 1, $select, $join);
	if(empty($row))
		return null;
	return $row[0];
}

function getRecordCount($table, $where=null) {
	if(!$this->init())
		return null;
    $query = "SELECT COUNT(*) AS cnt FROM `$table`";
	if($where)
		$query .= " WHERE $where";
	$result = $this->dataSrc->getRecordSet($query);
	if(!$result) {
		$this->error = $this->dataSrc->error;
		return null;
	}
	$row = $this->fetchObject($result);
	if(!$row)
		return null;
	return $row->cnt;
}
function getMaxFieldVal($table, $field, $where=null) {
	if(!$this->init())
		return null;
    $query = "SELECT MAX(`$field`) AS max FROM `$table`";
	if($where)
		$query .= " WHERE $where";
	$result = $this->dataSrc->getRecordSet($query);
	if(!$result) {
		$this->error = $this->dataSrc->error;
		return null;
	}
	$row = $this->fetchObject($result);
	if(!$row)
		return null;
	return $row->max;
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

function exec($sql) {
	if(!$this->init())
		return null;
	if(!$this->dataSrc->exec($sql)) {
		$this->error = $this->dataSrc->error;
		return false;
	}
	return true;
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
	if(!$this->dataSrc->getRecordSet($query)) {
		$this->error = $this->dataSrc->error;
		return null;
	}
	return $this->dataSrc->getRowsAffected();
}
function deleteRows($table, $where) {
	if(!$this->init())
		return null;
	$query = "DELETE FROM `$table` WHERE $where";
	if(!$this->dataSrc->getRecordSet($query)) {
		$this->error = $this->dataSrc->error;
		return null;
	}
	return $this->dataSrc->getRowsAffected();
}
function getRecordSet($sql) {
	if(!$this->init())
		return null;
	$result = $this->dataSrc->getRecordSet($sql);
	if($this->dataSrc->error) {
		$this->error = $this->dataSrc->error;
		return null;
	}
	$rows = [];
	while(($row = $this->fetchObject($result))) {
		$rows[] = $row;
	}
	return $rows;
}

function getTableList($colName=null) {
	$rows = $this->getRecordSet("SELECT name FROM sqlite_master WHERE type='table'");
	if($this->error)
		return null;
	$a = [];
	foreach($rows as $r) {
		// If colName set, check if table contains specified col and return only those tables
		if($colName) {
			$colList = $this->getColList($r->name);
			if($this->error)
				return null;
			if(!in_array($colName, $colList))
				continue;
		}
		$a[] = $r->name;
	}
	return $a;
}
function getColList($table) {
	// Below fails on old SQLite versions
	//$rows = $this->getRecordSet("SELECT name FROM PRAGMA_TABLE_INFO('$table')");
	$rows = $this->getRecordSet("PRAGMA table_info('$table')");
	if($this->error)
		return null;
	$a = [];
	foreach($rows as $r)
		$a[] = $r->name;
	return $a;
}

function getValCount($table, $col, $val) {
	$where = "`$col`='$val'";
	$select = "COUNT(*)";
	$res = $this->getRecord($table, $where, $select);
	if($this->error)
		return null;
	return $res;
}

/* function getLastQueryCount() {
	if(!$this->init())
		return null;
	return $this->dataSrc->getLastQueryCount();
} */
function getRowsAffected() {
	if(!$this->init())
		return null;
	return $this->dataSrc->getRowsAffected();
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

function close() {
	if($this->connected) {
		$this->dataSrc->closeDb();
		$this->connected = false;
	}
	if($this->dataSrc)
		unset($this->dataSrc);
}

function __destruct() {
	$this->close();
}

}
