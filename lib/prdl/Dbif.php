<?
/*

USAGE
=====
	require_once('Dbif.php');

	// This is not essential - Dbif will use a default (silent) error handler if none is supplied
	class MyErrorHandler extends DbifDefaultErrorHandler {
		function handleError($code, $basicMessage, $detailedMessage) {
			// Log error message and redirect user to error page
			echo $basicMessage." - ".$detailedMessage;
		}
	}

	$errorHandler = new MyErrorHandler();

	$dbName = 'test';
	$dbUsername = '';
	$dbPassword = '';
	$dbHost = '';

	// Connect to the database
	$db = new Dbif( $dbName, $dbUsername, $dbPassword, $dbHost, $errorHandler );

	// Check we managed to connect
	if (!$db->connected()) die("Couldn't connect to database");

	// Use exec to run arbitrary update SQL
	$db->exec('CREATE TEMPORARY TABLE theTable (col1 int unsigned not null primary key auto_increment, col2 int unsigned not null, col3 varchar(255))');

	// Insert some rows into the table
	for( $i=10; $i; $i-- ) {
		$db->insert('theTable',array(
			'col1'	=> '',
			'col2'	=> 123+$i,
			'col3'	=> 'wibble'.$i
		));
	}

	$whereValue = 5;
	$col3Update = "new 'value'";

	// run a query and get the results one row at a time
	$query = $db->query('SELECT col1, col2, col3 FROM theTable WHERE col1>?',$whereValue);
	while( $query->fetchInto($row) ) {
		echo "$row[0],$row[1],$row[2]<br />\n";
	}
	$query->free();


	// Perform an update using exec
	$rowsAffected = $db->exec('UPDATE theTable SET col3=? where col1=?',$col3Update,$whereValue);
	echo "Update affected $rowsAffected rows<br />\n";

	// Get a single value back
	$value = $db->getValue('SELECT col2 FROM theTable WHERE col1=?',$whereValue);
	echo "Got back value $value<br />\n";

	// Do another insert
	// The fourth parameter here details which values are SQL functions and thus should not be quoted or escaped
	$insert_id = $db->insert( 'theTable', array( '','UNIX_TIMESTAMP()','value3' ), 'col1,col2,col3', 'col2' );

	// Get back a column
	$col = $db->getColumn('SELECT col3 FROM theTable');
	echo "Col3 contains: ".implode(',',$col)."<br />";

	// Get a hash
	$hash = $db->getHash('SELECT col3,col1 FROM theTable WHERE col1>?',0);
	foreach( $hash as $key=>$value ) {
		echo "$key = $value<br />\n";
	}

	// Get a hash of arrays...
	$hash = $db->getHash('SELECT col3,col1,col2 FROM theTable WHERE col1>?',0);
	foreach( $hash as $key=>$value ) {
		echo "$key = $value<br />\n";
	}

	// Close the connection to the database
	$db->close();

*/

/*
DbifDefaultErrorHandler
=======================================================================================================
	 The main dbif object can be passed an error handler object
	 The error handle must have a handle method

	 If no error handler is provided then an instance of this default error handler will be used
=======================================================================================================

*/
class DbifDefaultErrorHandler {
	function __construct() {
	}

	/*
		The error will be one of these values
		basicMessage contains an error message with a description of the type of error but no details
		detailedMessage contains the details of the error
		1 => No query handle defined to execute query
		2 => Error establishing connection to database
		3 => Database reported SQL error in query
		4 => Error retreiving table meta data
		5 => Number of placeholders doesn't macth number of value supplied
	*/
	function handleError($errorCode, $basicMessage, $detailedMessage) {
		// Ideally the programmer will have defined their own error handler
		// this function should only be used as a worst-case fallback
		// in which case the safest thing to do is probably just to exit here
		exit;
	}
}

/*
DbifQuery
=======================================================================================================
	This object handles more complex queries where there is a requirement to be able to pull
	the results back row by row.

	This object offers methods to return a row, find meta data about the result set and manipulate
	the row pointer (i.e. skip rows)

	This type of object is retruned by Dbif->query()
=======================================================================================================

*/
class DbifQuery {

	var $queryHandle = 0;
	var $creator = 0;
	var $resultType;

	// Constructor
	function __construct( $queryHandle, $creator ) {
		$this->queryHandle = $queryHandle;
		$this->creator = $creator;
		$this->resultType = $creator->getResultType();
	}

	function numCols( ) {
		if (!$this->queryHandle) {
			$this->creator->errorHandler->handleError(1,"System error - no query handle","No query handle for numCols");
			return false;
		}
		return mysqli_num_fields( $this->queryHandle );
	}

	function numRows( ) {
		if (!$this->queryHandle) {
			$this->creator->errorHandler->handleError(1,"System error - no query handle","No query handle for numRows");
			return false;
		}
		return mysqli_num_rows( $this->queryHandle );
	}

    function rewind( ) {
        return $this->seek( 0 );
    }

    function seek( $offset ) {
        return mysqli_data_seek( $this->queryHandle, $offset );
    }

	function skipRows( $rows ) {
		if (!$this->queryHandle) {
			$this->creator->errorHandler->handleError(1,"System error - no query handle","No query handle for skipRows");
			return false;
		}
		mysqli_data_seek( $this->queryHandle,  $rows );
	}

	function fetchInto( &$rowData, $resultType = false ) {
		if ($resultType===false) $resultType = $this->resultType;
		if (!is_array( $rowData )) $rowData = array();
		if (!$this->queryHandle) {
			$this->creator->errorHandler->handleError(1,"System error - no query handle","No query handle for fetchInto");
			return false;
		}
		$rowData = mysqli_fetch_array( $this->queryHandle,  $resultType );
		return( $rowData );
	}

	function getColumns() {
		if (!$this->queryHandle) {
			$this->creator->errorHandler->handleError(1,"System error - no query handle","No query handle for getColumns");
			return false;
		}
		$numFields = (($___mysqli_tmp = mysqli_num_fields($this->queryHandle)) ? $___mysqli_tmp : false);
		$idx = 0;
		$columns=array();
		while ( $idx < $numFields ) {
			$colData = mysqli_fetch_field_direct( $this->queryHandle, $idx);
			if (!is_object($colData) || !isset($colData->name) ) {
				$columns[$idx] = '';
			} else {
				$columns[$idx] = $colData->name;
			}
			$idx++;
		}
		return $columns;

	}

	function getColumnNames( ) {
		return $this->creator->getColumnData( $this->queryHandle, 0 );
	}

	function getColumnLengths( ) {
		return $this->creator->getColumnData( $this->queryHandle, 1 );
	}

	function getColumnTypes( ) {
		return $this->creator->getColumnData( $this->queryHandle, 2 );
	}

	function getColumnFlags( ) {
		return $this->creator->getColumnData( $this->queryHandle, 3 );
	}

	function free( ) {
		mysqli_free_result( $this->queryHandle ); /* No return value */
		return (((is_object( $this->queryHandle ) && (get_class( $this->queryHandle ) == "mysqli_result"))) ? true : false);
	}

}


/*
Dbif
=======================================================================================================
	This is the main database interface object this provides methods for
		connnecting to the database
		queryinig the database with simple queries to get values, rows, columns and hashes back
		executing inserts
		running arbitrary update SQL
=======================================================================================================

*/

class Dbif {

    var $errorHandler;

    var $dbHandle = false;

	// this stores the current mode for pulling back row arrays
    var $resultType = MYSQLI_BOTH;
    // this allows for temporarily overriding the result type
	var $nextResultType=false;

    // dbName is required for calls to mysql_list_fields
    var $dbName;

	var $lastError = '';

	var $queryMonitor = null;

	// Constructor
	function __construct( $dbName, $username='', $password='', $host='', $errorHandler='' ) {

		// Set up the error handler if none is provided
		if (!is_object($errorHandler)) {
			$errorHandler = new DbifDefaultErrorHandler();
		}
		$this->errorHandler = $errorHandler;

		// Defaul the host
		if ($host=='') $host='localhost';
		$this->dbHandle = false;
		$this->dbName = false;

		// Connect to the DB
		if ( !($this->dbHandle = mysqli_connect($host, $username, $password))) {
			$this->lastError = "Couldn't connect to host '$host' using username '$username'";
			$this->errorHandler->handleError(2,"Error connecting to database",$this->lastError);
		} else if ( !mysqli_select_db($this->dbHandle, $dbName) ) {
			$this->lastError = "Couldn't select database '$dbName'";
			$this->errorHandler->handleError(2,"Error selecting database",$this->lastError);
			((is_null($___mysqli_res = mysqli_close($this->dbHandle))) ? false : $___mysqli_res);
			$this->dbHandle = false;
		} else {
			$this->dbName = $dbName;
            // make sure we use UTF8
            ((bool)mysqli_set_charset( $this->dbHandle, "utf8"));
		}

        if (!defined('DB_LOG_ONE_IN')) define('DB_LOG_ONE_IN', 0); // Disable. Set to X in config to randomly log 1 in X queries
        if (!defined('DB_LOG_SLOW_THRESHOLD')) define('DB_LOG_SLOW_THRESHOLD', 0); // Disable. Set to X seconds in config to log anything slower
	}

	function getErrorHandler() {
		return $this->errorHandler;
	}

	function connected() {
		return $this->dbHandle;
	}

	function returnArray($nextQueryOnly=true) {
		if ($nextQueryOnly) return( $this->nextResultType= MYSQLI_NUM );
		return( $this->resultType = MYSQLI_NUM );
	}

	function returnHash($nextQueryOnly=true) {
		if ($nextQueryOnly) return( $this->nextResultType= MYSQLI_ASSOC );
		return( $this->resultType = MYSQLI_ASSOC );
	}

	function returnBoth($nextQueryOnly=true) {
		if ($nextQueryOnly) return( $this->nextResultType= MYSQLI_BOTH );
		return( $this->resultType = MYSQLI_BOTH );
	}

	function getResultType($resetNext = true) {
		if ($this->nextResultType !== false) {
			$resultType = $this->nextResultType;
			if($resetNext) $this->nextResultType = false;
		} else $resultType = $this->resultType;
		return $resultType;
	}

    function setQueryMonitor($queryMonitor) {
        $oldQM = $this->queryMonitor;
        $this->queryMonitor = $queryMonitor;
        return $oldQM;
    }

	function lastError() {
		return $this->lastError;
	}

	// This is an internal function used to replace placeholders with corresponding fields
	// and escape data in the process
	function buildSql( $fields ) {
		global $WS;

		$sqlStr = array_shift($fields);

		// the sql string and fields are sometimes passed as a single array
		// we detect this and unpack it here
		while (is_array($sqlStr)) {
			$fields = $sqlStr;
			$sqlStr = array_shift($fields);
		}

		// substitute workspace fields in place of @@x@@ tags
		$bits = explode('@@',$sqlStr);
		$sqlStr = '';
		while( count($bits) ) {
			$sqlStr .= array_shift( $bits );
			if (count($bits)) {
				$thisBit = array_shift( $bits );
				$needsQuotes = !strpos(' \'"',substr($sqlStr,-1));

				if ($needsQuotes) $sqlStr .= '"';
				if (isset($WS[$thisBit])) {
                    $thisWSBit = $WS[$thisBit]; // Assign first as forceArray() works on a reference and we don't want to alter $WS
                    $thisWSBit = forceArray($thisWSBit);
                    foreach($thisWSBit as $key => $val) $thisWSBit[$key] = mysqli_real_escape_string($this->dbHandle, $val);
                    $quoteChar = $needsQuotes ? '"' : substr($sqlStr,-1);
                    $sqlStr .= implode($quoteChar.','.$quoteChar, $thisWSBit);
                }
				if ($needsQuotes) $sqlStr .= '"';
			}
		}

		// substitute in the fields if some have been supplied
		if (count($fields)) {
			$bits = explode('?',$sqlStr);
			$newSql = array_shift( $bits );
			foreach( $bits as $idx => $bit ) {
				if (!isset($fields[$idx])) {
					// Flag an error if there is no matching record for this field
					$this->errorHandler->handleError(5,"Number of placeholders doesn't match number of values supplied",$sqlStr);
					return false;
				}
				$data = $fields[$idx];

				if (is_array($data)) {
					# if this is an empty array then just use the mysql value FALSE
					# if "x IN (FALSE)" will usually return false and will not error
                    # 20190703 actually.... that's not true - in (false) will return rows if the column in question does have rows containing false values
                    # better to use IN (NULL)
                    # 20250729 Arghh!!! There is another problem. If we use NULL this works fine for "WHERE column IN (NULL)"
                    # BUT it breaks when we use "WHERE column NOT IN (NULL)" - since this also returns no rows (where we want it to return ALL rows)
                    # So.... we need a value that is basicaly guaranteed NOT to occur in any column
                    # Best solution I can think of it changing this to "___ IMPOSSIBLE VALUE ___".md5(uniqid())
                    # Arghh!! BUT THAT DOESN'T WORK for integer columns - since the value will be cast to zero
                    # On reflection I can't think of a solution - so I think leaving it as NULL is best
					if (!count($data)) $newSql .= 'NULL';
					else {
						foreach( $data as $subIdx=>$value ) {
							$newSql .=  "'".mysqli_real_escape_string($this->dbHandle, $value)."',";
						}
						# take the final comma of the end
						$newSql = substr($newSql,0,-1);
					}
				} else {
					# now for ordinary values...
					$newSql .= "'".mysqli_real_escape_string($this->dbHandle, $data)."'";
				}

				$newSql .= $bit;
			}
			$sqlStr = $newSql;
		}

		return $sqlStr;
	}

	# This is for UDPATE, INSERT AND DELETE queries
	# it returns the number of rows affected by the query
	# OR the insert ID if the sql starts with the word 'INSERT' and the table has an auto-increment field
	function exec() {

		$sqlStr = $this->buildSql( func_get_args() );

        $queryStart = getUSTime();
		$queryHandle = mysqli_query($this->dbHandle, $sqlStr);
        $execTime = getUSTime() - $queryStart;

		if (!$queryHandle) {
			$this->lastError = 'Error ('.mysqli_errno($this->dbHandle).': '.mysqli_error($this->dbHandle).") executing SQL '$sqlStr'";
			$this->errorHandler->handleError(3,"Error in SQL",$this->lastError);
			return false;
		}

		$insertCheckRegexp = '/^([\s\r\n]|\s*#[^\r\n]*[\r\n])*INSERT/si';

        $retVal = $rowCount = mysqli_affected_rows( $this->dbHandle );
        if(preg_match($insertCheckRegexp, $sqlStr)) {
			$insertId = ((is_null($___mysqli_res = mysqli_insert_id( $this->dbHandle ))) ? false : $___mysqli_res);
            if($insertId) {
                $retVal = $insertId;
                $rowCount = 1;
            }
		}

        if(is_a($this->queryMonitor, 'DbifQueryMonitorInterface')) {
			$this->queryMonitor->execNotify($this, $rowCount, $execTime, ...func_get_args());
		}

        return $retVal;
	}

	function query() {
		$sqlStr = $this->buildSql( func_get_args() );

        $queryStart = getUSTime();
		$queryHandle = mysqli_query($this->dbHandle, $sqlStr);
        $execTime = getUSTime() - $queryStart;

		if (!$queryHandle) {
			$this->lastError = 'Error ('.mysqli_errno($this->dbHandle).': '.mysqli_error($this->dbHandle).") executing SQL '$sqlStr'";
			$this->errorHandler->handleError(3,"Error in SQL",$this->lastError);
            return FALSE;
		}

        $rowCount = mysqli_num_rows($queryHandle);

        if(is_a($this->queryMonitor, 'DbifQueryMonitorInterface')) {
			$this->queryMonitor->selectNotify($this, $rowCount, $execTime, ...func_get_args());
		}

		return new DbifQuery($queryHandle, $this);
	}

	// This function builds and exequtes and INSERT statement
	// $columnNames should be a comma separated list of column names or an array of column names
	// If $values is a hash and $columns is empty then the column names will be taken from the hash keys
	// $allowUnquoted can be used to specify columns whose value should not be quoted
	// $allowUnquoted can be an array of a comma separated list
	// i.e. if one of the values is a mysql function like UNIX_TIMESTAMP();
	// N.B. Column names must not consist entirely of numbers
	function insert( $table, $values, $columnNames='', $allowUnquoted='', $insertIgnore = false ) {
		$valueData = array();

		if ($columnNames=='') $columnNames = array_keys($values);
		if (!is_array($columnNames)) $columnNames = explode(',',$columnNames);

		if ($allowUnquoted=='') $allowUnquoted = array();
		else if (!is_array($allowUnquoted)) $allowUnquoted = explode(',',$allowUnquoted);
		$allowUnquoted = array_flip($allowUnquoted);

		$idx=-1;
		foreach( $values as $key=>$value) {
			$idx++;
			$columnName = $columnNames[$idx];

			if ( count($allowUnquoted) && isset($allowUnquoted[$columnName])) {
				$valueData[] = $value;
			} else {
				if (is_array($value)) $value = implode(',',$value);
				$valueData[] = "'".mysqli_real_escape_string($this->dbHandle, $value)."'";
			}
		}
		$sqlStr = "INSERT ";
		if($insertIgnore) {
			$sqlStr .= " IGNORE ";
		}
		$sqlStr .= " INTO `$table` ";
		$columnList = '`'.implode('`,`',$columnNames).'`';
		if (preg_match('/[^\\d,]/',$columnList)) {
			$sqlStr .= "(".$columnList.") ";
		}
        $logData = [$sqlStr, ...$valueData];
		$sqlStr .= "VALUES (".implode(",",$valueData).")";

        $queryStart = getUSTime();
		$queryHandle = mysqli_query($this->dbHandle, $sqlStr);
        $execTime = getUSTime() - $queryStart;

		if (!$queryHandle) {
			$this->lastError = 'Error ('.mysqli_errno($this->dbHandle).': '.mysqli_error($this->dbHandle).") executing SQL '$sqlStr'";
			$this->errorHandler->handleError(3,"Error in SQL",$this->lastError);
            return false;
		} else {
            $rowCount = mysqli_affected_rows($this->dbHandle);

            if(is_a($this->queryMonitor, 'DbifQueryMonitorInterface')) {
                $this->queryMonitor->execNotify($this, $rowCount, $execTime, ...func_get_args());
            }
		}
		return( ((is_null($___mysqli_res = mysqli_insert_id( $this->dbHandle ))) ? false : $___mysqli_res) );
	}

	function autoInsert( $tableName, $columnSpec='', $extras=null, $defaults=null, $duplicateCheck=false ) {

		$insertData = $this->buildColumnHash( $tableName, $columnSpec, $extras, $defaults );
		if ($duplicateCheck && count($insertData)) {
			$checkArgs = array();
			$checkSql = "SELECT 1 FROM `$tableName` WHERE ";
			foreach( $insertData as $columnName=>$value ) {
				$checkSql .= "$columnName=? AND ";
				$checkArgs[] = $value;
			}
			array_unshift($checkArgs, substr($checkSql,0,-4));
			$checkResult = $this->getValue( $checkArgs );
			if ($checkResult) return 0;
		}

		return( $this->insert( $tableName, $insertData ) );
	}

	function conditionsFromHash( $where_data ) {
		if ( is_null( $where_data) || !is_array($where_data) || !count($where_data) ) return '1=1';

		$values = array('');
		$sql = "";

		foreach( $where_data as $key=>$value ) {
			if (is_array($value)) {
				$sql .= "`$key` IN (?) AND ";
			} else {
				$sql .= "`$key` = ? AND ";
			}
			$values[] = $value;
		}

		$values[0] = substr($sql,0,-4);
		return $values;
	}

	function duplicateData( $table_name, $where_data, $update_data, $dest_table=null, $callback=null, $destDb=null ) {
		if (is_null($dest_table)) $dest_table = $table_name;
		$queryData = $this->conditionsFromHash( $where_data );
		$queryData[0] = "SELECT * FROM `$table_name` WHERE ".$queryData[0];
		$query = $this->query( $queryData );
		$idx = 0;
		$row = []; //Just adding this for PHPStan
		while ( $query->fetchInto( $row, MYSQLI_ASSOC ) ) {
			$newRow = array_merge($row, $update_data);
			if (is_null($destDb)) $destDb=$this;
			$id = $destDb->insert($dest_table,$newRow);
			if (is_callable($callback)) $callback( $row, $id, $idx );
			$idx++;
		}
	}

	function map( $sql, $function, $noReturn=false ) {
		if (!is_callable($function)) return array();
		$query = $this->query($sql);
		$return = array();
		$idx = 0;
		$row = []; //Just adding this for PHPStan
		while ( $query->fetchInto( $row, MYSQLI_ASSOC ) ) {
			$idx++;
			if ($noReturn) $function($row,$idx);
			else $return[] = $function($row,$idx);
		}
		return $return;
	}

    function delete( $table_name, $where_data ) {

        $sql = "DELETE FROM `$table_name` WHERE ";
        $values = array('');
        $where_data = $this->conditionsFromHash( $where_data );
        $sql .= array_shift($where_data);
        $values = array_merge($values, $where_data);
        $values[0] = $sql;
        return $this->exec( $values );
    }

	function update( $table_name, $where_data, $update_data ) {

		if (!count($where_data) || !count($update_data)) return false;

		$sql = "UPDATE `$table_name` SET ";
		$values = array('');
		foreach( $update_data as $key=>$value ) {
			$sql .= "`$key` = ?, ";
			$values[] = $value;
		}

		$sql = substr($sql,0,-2)." WHERE ";

		$where_data = $this->conditionsFromHash( $where_data );
		$sql .= array_shift($where_data);
		$values = array_merge($values, $where_data);
		$values[0] = $sql;

		return $this->exec(...$values);
	}

    function select( $table_name, $where_data, $columns='*', $orderBy='', $orderDir='ASC', $limit=0 ) {

        if (!is_array($columns) && strpos($columns,',')) $columns = explode(',',$columns);
        if (!is_array($columns)) $columns = array($columns);
        $limit = (int)$limit;
        if ($orderDir !== 'ASC') $orderDir !== 'DESC';

        $sql = 'SELECT ';
        foreach( $columns as $column) {
            $column = trim($column);
            if ($column==='*') $sql .= '*,';
            else $sql .= '`'.str_replace('`','',$column).'`,';
        }
        $sql = substr($sql,0,-1);

        $sql .= " FROM `$table_name` WHERE ";

        $where_data = $this->conditionsFromHash( $where_data );
        $sql .= array_shift($where_data);
        if ($limit>1 && strlen($orderBy)) $sql .= ' ORDER BY `'.str_replace('`','',$orderBy).'` '.$orderDir;
        if ($limit>0) $sql .= " LIMIT $limit";

        array_unshift($where_data,$sql);

        if (count($columns)==1 && trim($columns[0])!=='*') return $this->getValue( $where_data );
        if ($limit==1) return $this->getRow( $where_data );
        return $this->getRows( $where_data );
    }

	function autoUpdate( $table_name, $column_spec='', $where_field='id', $where_value=null, $extras=null, $defaults=null ) {
		global $WS;
		if ($where_value===null && isset($WS[$where_field])) $where_value=$WS[$where_field];
		$update_data = $this->buildColumnHash( $table_name, $column_spec, $extras, $defaults );
		if (!count($update_data)) return false;

		return $this->update( $table_name, array($where_field => $where_value), $update_data );
	}

    // For two-column key/value tables
    // Does a REPLACE INTO so $keyCol should be UNIQUE to avoid duplicates
    function keyValueUpdate($tableName, $keyCol, $valCol, $prefix) {
        global $WS;
        $keyCol = self::sanitizeColumnName($keyCol);
        $valCol = self::sanitizeColumnName($valCol);
        $prefixLen = strlen($prefix);
        $affectedRows = 0;

        foreach($WS as $key => $val) {
            if(strpos($key, $prefix) === 0 && strlen($key) > $prefixLen) {
                $key = substr($key, $prefixLen);
                $affectedRows += $this->exec(array("REPLACE INTO `$tableName` (`$keyCol`, `$valCol`) VALUES(?, ?)", $key, $val));
            }
        }

        return $affectedRows;
    }

#	columnspec provides details of what data to insert into the database
#		This can either be a field prefix which will identify CGI fields (the prefix is stripped off)
#			e.g. if column_spec=new_row_ and CGI fields include new_row_foo and new_row_blah then the SQL will look something like
#			INSERT INTO table_name ( foo, blah ) VALUES ( <value of CGI['new_row_foo']>, <value of CGI['new_row_blah']> );
#		Othewise this can be an ordinary array specify names of fields to be added
#			(it is assumed that the field and column names are identical) e.g. column_spec=array('foo','blah') gives
#			INSERT INTO table_name ( foo, blah ) VALUES ( <value of CGI['foo']>, <value of CGI['blah']> );
#		Or... it can be a hash mapping CGI field names to column names
#			e.g. column_spec=array( 'wibble' => 'foo', 'blort' => 'blah ) gives
#			INSERT INTO table_name ( foo, blah ) VALUES ( <value of CGI['wibble']>, <value of CGI['blort']> );
#			N.B. entirely numeric CGI field names are NOT ALLOWED e.g. column_spec=array( '1' => 'foo', 'wibble' => 'blah )
#		Or... if it is not given at all or an empty string is passed ('') then it looks for all cgi fields that start "<table_name>_"
#			and uses these.
#
#	extras is a hash keyed on column name that defines extra data to be inserted
#		- this is ignored if a non-array value is passed
#		- it is also ignored if column_spec is not specified
#	defaults is a hash keyed on column name that provides default values should the appropriate CGI fields not exist.
#	BEWARE: input taken from $WS or $defaults is screened to check if it starts '%' - if it is this is escaped with a second '%'
#		this stops users entering strings starting with '%' and having them run as arbitrary SQL
#		if you want to use a function like UNIX_TIMESTAMP then put that in $extra - this is not filtered in this way
#		by the same virtue take care when putting things in $extra that start with '%'!

	function buildColumnHash( $table_name, $column_spec='', $extras=null, $defaults=null, $allow_unquoted='' ) {
		global $WS, $SEARCH_SPEC_LOOKUP_TABLE;
		if ( !is_array($defaults) ) $defaults = array();
		# extra fields are a nonsense if we are going to interogate the database for ALL columns
		if ( !is_array($extras) || $column_spec=='' ) $extras=array();
		$data = $extras;
		if ( $column_spec == '' ) {
			$column_spec = $table_name.'_';
		}

		if ( !is_array($column_spec) ) {
#			echo "column_spec = $column_spec<BR />\n";
			# column_spec must be a field name prefix
			reset( $WS );
			foreach ( $WS as $key=>$value ) {
				if ( strpos($key,$column_spec)===0 && !(substr( $key, -3, 1) == '_' && isset($SEARCH_SPEC_LOOKUP_TABLE[substr( $key, -2)]) ) ) {
					$col = substr($key,strlen($column_spec));
#					echo "inserting data into column $col<BR />\n";
					if ( $allow_unquoted && substr( $value, 0, 1 ) == '%' ) $value = '%'.$value;
                    $col = self::sanitizeColumnName($col);
					$data[$col] = $value;
				}
			}
		} else {
			# column spec is some kind of array/hash
			reset( $column_spec );
            foreach($column_spec as $key => $value) {
				if ( preg_match('/^[0-9]+$/',$key) ) {
					# this is part of a plane old array
					# there is no mapping between CGI field name and column name to be done
					$key = $value;
				}
				if ( isset($WS[$key]) ) {
					$data[$value] = $WS[$key];
				} elseif ( isset( $defaults[$key] ) ) {
					$data[$value] = $defaults[$key];
				} else {
					$data[$value] = '';
				}
				if ( substr( $data[$value], 0, 1 ) == '%' ) $data[$value] = '%'.$data[$value];
			}
		}
		return($data);
	}

    static function sanitizeColumnName($col) {
        $col = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
        $col = substr($col, 0, 64);
        return $col;
    }

	function tableExists( $tableName ) {
		$tableLookup = array_flip( $this->getTables() );
		return isset( $tableLookup[ $tableName ] );
	}

	function getTempTableName( $suggestion = '' ) {
		if ( $suggestion == '' ) $suggestion = 'tmp_'.time();
		$tableLookup = array_flip( $this->getTables() );
		$idx = 0;
		while ( isset( $tableLookup[ $suggestion.'_'.$idx ] ) ) { $idx++; }
		return $suggestion.'_'.$idx;
	}

	function lookup( $table, $value, $lookupColumn='description', $keyColumn='id' ) {
		list( $value ) = $this->getRow("SELECT `$lookupColumn` FROM `$table` WHERE `$keyColumn`='".mysqli_real_escape_string($this->dbHandle, $value)."' LIMIT 1");
		return $value;
	}

	function close( ) {
		if (is_resource($this->dbHandle)) ((is_null($___mysqli_res = mysqli_close($this->dbHandle))) ? false : $___mysqli_res);
	}

	# if the SQL returns one column this returns a hash in the form col1 => col1
	# if the SQL returns two columns this returns a hash in the form col1 => col2
	# if the SQL returns more than two columns this returns a hash in the form col1 => array(col2,col3...)

	function getHash( ) {
		$resultType = $this->getResultType(false); // Get this now as if it's using nextResultType it'll be blanked when the query runs
		$queryh = $this->query(...func_get_args());
		$numCols = $queryh->numCols();

		$hash = array();
		$row = array();
		$setKeys = true;
		while( $queryh->fetchInto($row) ) {
			if($setKeys) {
				$key0 = 0;
				$key1 = 1;
				// Using numerical keys doesn't work if returnHash() has been called before this
				if($resultType === MYSQLI_ASSOC) {
					$rowKeys = array_keys($row);
					$key0 = $rowKeys[0];
					$key1 = isset($rowKeys[1]) ? $rowKeys[1] : null;
				}
				$setKeys = false;
			}
			if ( $numCols > 2 ) {
				$key = $row[$key0];
				unset( $row[$key0] ); /* @phpstan-ignore-line */
				$hash[$key] = $row;
			} else if ( $numCols==2 ) {
				$hash[$row[$key0]] = $row[$key1];
			} else {
				$hash[$row[$key0]] = $row[$key0];
			}
		}
		$queryh->free();
		reset($hash);
		return $hash;
	}

	function getTableRows( $tableName ) {
		$tableName = str_replace('`','',$tableName);
		return $this->getValue("SELECT COUNT(*) FROM `$tableName`");
	}

	function getColumnNames( $tableName ) {
		return $this->getColumnData( $tableName, 0 );
	}

	function getColumnLengths( $tableName ) {
		return $this->getColumnData( $tableName, 1 );
	}

	function getColumnTypes( $tableName ) {
		return $this->getColumnData( $tableName, 2 );
	}

	function getColumnFlags( $tableName ) {
		return $this->getColumnData( $tableName, 3 );
	}

	function getColumnData( $tableName, $which ) {
		// see if the "tableName" is in fact a query handle
		if ( !is_resource($tableName) ) {
			$sqlStr = "SELECT EXTRA, COLUMN_NAME, COLUMN_TYPE, COLLATION_NAME, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, DATA_TYPE, COLUMN_KEY, IS_NULLABLE FROM `information_schema`.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION ASC";
			$columnDataQuery = $this->query($sqlStr,$this->dbName, $tableName);

			$columnSpec = array();
			$colData = [];
			while ($columnDataQuery->fetchInto($colData)) {
				switch ($which) {
					case 0:
							$columnSpec[] = $colData['COLUMN_NAME'];
							break;
					case 1:
							$columnSpec[] = $colData['CHARACTER_MAXIMUM_LENGTH'] + $colData['NUMERIC_PRECISION'];
							break;
					case 2:
							$columnSpec[] = $colData['DATA_TYPE'];
							break;
					case 3:
							$flags = '';
							if ($colData['COLUMN_KEY']=='MUL') $flags .= 'multiple_key';
							else if ($colData['COLUMN_KEY']=='PRI') $flags .= 'primary_key';
							else if ($colData['COLUMN_KEY']=='UNI') $flags .= 'unique_key';
							if ($colData['DATA_TYPE']=='blob') $flags .= 'blob';
							else if ($colData['DATA_TYPE']=='enum') $flags .= 'enum';
							else if ($colData['DATA_TYPE']=='set') $flags .= 'set';
							else if ($colData['DATA_TYPE']=='timestamp') $flags .= 'timestamp';
							if (strpos($colData['COLUMN_TYPE'],'unsigned')!==false) $flags .= 'unsigned';
							if (strpos($colData['COLUMN_TYPE'],'zerofill')!==false) $flags .= 'zerofill';
							if (strpos($colData['EXTRA'],'auto_increment')!==false) $flags .= 'auto_increment';
							if (strpos(strrev($colData['COLLATION_NAME']),'nib_')===0) $flags .= 'binary';
							if (!$colData['IS_NULLABLE']) $flags .= 'not_null';
							$columnSpec[] = $flags;
				}
			}
			return($columnSpec);
		}

		$handle = $tableName;

		if (!is_object($handle)) {
			$dbError = mysqli_error( $this->dbHandle );
			$this->lastError="Error getting table column names in Dbif->getColumnNames probably table '$tableName' doesn't exist. DB error message: $dbError";
			$this->errorHandler->handleError(4,"System error communicating with database",$this->lastError);
			return(0);
		}

		$numFields = mysqli_num_fields( $handle );

		$columnSpec = array();
		$idx = 0;
		while ( $idx < $numFields ) {
			$colData = mysqli_fetch_field_direct( $handle,  $idx );
			if (!is_object($colData)) {
				$columnSpec[] = false;
			} else {
				switch ($which) {
					case 0:
							$columnSpec[] = isset($colData->name) ? $colData->name : false;
							break;
					case 1:
							$columnSpec[] = isset($colData->length) ? $colData->length : 0;
							break;
					case 2:
							if (!isset($colData->type) || is_null($colData->type)) {
								$type = false;
							} else {
								$type = '';
								if ( $colData->type == MYSQLI_TYPE_STRING || $colData->type == MYSQLI_TYPE_VAR_STRING ) $type='string';
								if ( in_array($colData->type, array(MYSQLI_TYPE_TINY, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_LONG, MYSQLI_TYPE_LONGLONG, MYSQLI_TYPE_INT24))) $type .= "int ";
								if ( in_array($colData->type, array(MYSQLI_TYPE_FLOAT, MYSQLI_TYPE_DOUBLE, MYSQLI_TYPE_DECIMAL, defined("MYSQLI_TYPE_NEWDECIMAL") ? constant("MYSQLI_TYPE_NEWDECIMAL") : -1))) $type .= "real ";
								if ( $colData->type == MYSQLI_TYPE_TIMESTAMP) $type .= "timestamp ";
								if ( $colData->type == MYSQLI_TYPE_YEAR) $type .= "year ";
								if ( $colData->type == MYSQLI_TYPE_DATE || $colData->type == MYSQLI_TYPE_NEWDATE) $type .= "date ";
								if ( $colData->type == MYSQLI_TYPE_TIME) $type .= "time ";
								if ( $colData->type == MYSQLI_TYPE_SET) $type .= "set ";
								if ( $colData->type == MYSQLI_TYPE_ENUM) $type .= "enum ";
								if ( $colData->type == MYSQLI_TYPE_GEOMETRY) $type .= "geometry ";
								if ( $colData->type == MYSQLI_TYPE_DATETIME) $type .= "datetime ";
								if ( in_array($colData->type, array(MYSQLI_TYPE_TINY_BLOB, MYSQLI_TYPE_BLOB, MYSQLI_TYPE_MEDIUM_BLOB, MYSQLI_TYPE_LONG_BLOB))) $type .= "blob ";
								if ( $colData->type == MYSQLI_TYPE_NULL) $type .= "null ";
								$type = substr((string)$type, 0, -1);
								if ($type== "") $type="unknown";
							}
							$columnSpec[] = $type;
							break;
					case 3:
							if (!isset($colData->flags)) $flags = false;
							else {
								if ( $colData->flags & MYSQLI_NOT_NULL_FLAG)       $flags .= "not_null ";
								if ( $colData->flags & MYSQLI_PRI_KEY_FLAG)        $flags .= "primary_key ";
								if ( $colData->flags & MYSQLI_UNIQUE_KEY_FLAG)     $flags .= "unique_key ";
								if ( $colData->flags & MYSQLI_MULTIPLE_KEY_FLAG)   $flags .= "unique_key ";
								if ( $colData->flags & MYSQLI_BLOB_FLAG)           $flags .= "blob ";
								if ( $colData->flags & MYSQLI_UNSIGNED_FLAG)       $flags .= "unsigned ";
								if ( $colData->flags & MYSQLI_ZEROFILL_FLAG)       $flags .= "zerofill ";
								if ( $colData->flags & 128)                        $flags .= "binary ";
								if ( $colData->flags & 256)                        $flags .= "enum ";
								if ( $colData->flags & MYSQLI_AUTO_INCREMENT_FLAG) $flags .= "auto_increment ";
								if ( $colData->flags & MYSQLI_TIMESTAMP_FLAG)      $flags .= "timestamp ";
								if ( $colData->flags & MYSQLI_SET_FLAG)            $flags .= "set ";
								$flags = substr((string)$flags, 0, -1);
							}

							$columnSpec[] = $flags;
							break;
				}
			}
			$idx++;
		}
		return($columnSpec);
	}

	function count() {
		$sqlStr = $this->buildSql( func_get_args() );
		$sqlStr = "SELECT COUNT(*) FROM (\n".$sqlStr."\n /* */ ) countTable";
		return $this->getValue($sqlStr);
	}

	function getColumn( ) {
		$data = array();
		$row = array();
		$query = $this->query(...func_get_args());
		while( $query->fetchInto($row, MYSQLI_NUM) ) {
			$data[] = $row[0];
		}
		$query->free();
		return $data;
	}

	function getValue( ) {
		$this->nextResultType = MYSQLI_NUM;
		$return = $this->getRow(...func_get_args());

		if (!is_array($return)) return false;

		return $return[0];
	}

	function getRow( ) {
		if ($this->nextResultType !== false) {
			$resultType = $this->nextResultType;
			$this->nextResultType = false;
		} else $resultType = $this->resultType;

		$data = array();
		$query = $this->query(...func_get_args());
		if (!$query) return false;

		$query->fetchInto($data, $resultType);
		$query->free();
		return $data;
	}

	/*
		Syntax:
		loadRow( <sql> )
		loadRow( <sql>, <prefix> )
		loadRow( <sql>, <mergefield1>, ..., <mergefieldN>, <prefix> );
	*/

	function loadRow( ) {
		global $WS;
		$args = func_get_args();
		$prefix = '';
		if (count($args) > 1) $prefix = array_pop( $args );

		$this->nextResultType = MYSQLI_ASSOC;
		$values = $this->getRow(...$args);

		if (is_array($values)) {
			foreach ($values as $key=>$value) {
				$WS[$prefix.$key]=$value;
			}
		}
	}

    function loadHash( ) {
        global $WS;
        $args = func_get_args();
        $prefix = '';
        if (count($args) > 1) $prefix = array_pop( $args );

        $this->nextResultType = MYSQLI_NUM;
        $values = $this->getHash(...$args);

        if (is_array($values)) {
            foreach ($values as $key=>$value) {
                $WS[$prefix.$key]=$value;
            }
        }
    }

	function getRows( ) {
		if ($this->nextResultType !== false) {
			$resultType = $this->nextResultType;
			$this->nextResultType = false;
		} else $resultType = $this->resultType;

		$data = array();
		$return = array();
		$query = $this->query(...func_get_args());

		while( $query->fetchInto($data, $resultType) ) {
			$return[]=$data;
		}
		$query->free();
		return $return;
	}

	function getTables($filterRegex = null) {
		$queryh = mysqli_query( $this->dbHandle , "SHOW TABLES FROM `$this->dbName`");
		$tables = array();
		$i = mysqli_num_rows($queryh);
		while ($i-- > 0) {
			$tables[$i] = ((mysqli_data_seek($queryh,  $i) && (($___mysqli_tmp = mysqli_fetch_row($queryh)) !== NULL)) ? array_shift($___mysqli_tmp) : false);
		}
        if($filterRegex) {
            foreach($tables as $idx => $tableName) {
                if(!preg_match("/$filterRegex/", $tableName)) unset($tables[$idx]);
            }
        }
		return $tables;
	}

	// $sql should be something like 'SELECT 1 FROM table WHERE id='

	function findAvailableId( $id, $sql, $charset='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ' ) {
		$count = 1;
		$base = strlen($charset);
		$idLength = strlen($id);
		$maxCount = pow($base,$idLength);

		while ($count <= $maxCount) {
			list($exists) = $this->getValue($sql."'".mysqli_real_escape_string($this->dbHandle, $id)."' LIMIT 1");
			if (!isset($exists) or !strlen($exists)) return $id;

			$test = 0;
			while ( $count >= pow($base,$test)  ) {
				if (($count % pow($base,$test)) == 0) {
					$charPos = (strpos($charset,substr($id,$idLength-$test-1,1))+1) % $base;
					$id = substr($id,0,$idLength-$test-1).substr($charset,$charPos,1).substr($id,$idLength-$test);
				}
				$test++;
			}
			$count++;
		}
		return '';
	}

	function replace( $tableName, $keyData, $otherData, $returnId=false, $idColumn='id' ) {
		$insertCols = [];
		$insertPlaceHolders = [];
		$insertVals = [];
		foreach($keyData as $col => $val) {
			$insertCols[] = '`'.$col.'`';
			$insertPlaceHolders[] = '?';
			$insertVals[] = $val;
		}

		$updateCols = [];
		$updateVals = [];
		foreach($otherData as $col => $val) {
			$insertCols[] = '`'.$col.'`';
			$insertPlaceHolders[] = '?';
			$insertVals[] = $val;
			$updateCols[] = "`$col` = ?";
			$updateVals[] = $val;
		}

		$replaceSql = "
			INSERT INTO `$tableName` (".implode(',', $insertCols).")
			VALUES(".implode(',', $insertPlaceHolders).")
			ON DUPLICATE KEY
			UPDATE ".implode(',', $updateCols)."
		";
		$replaceQuery = array_merge([$replaceSql], $insertVals, $updateVals);

		$this->exec($replaceQuery);
		$replaceResult = mysqli_affected_rows($this->dbHandle);

		if($replaceResult == 0 || $replaceResult == 2) { // matched but nothing to change or matched and updated
			if ($returnId) {
				$updateIdQuery = $this->conditionsFromHash($keyData, "SELECT `$idColumn` FROM `$tableName` WHERE ");
				$updateIdQuery[0] .= ' LIMIT 1';
				return $this->getValue($updateIdQuery);
			} else return 'updated';
		} else if($replaceResult == 1) { // inserted
			return $returnId ? mysqli_insert_id($this->dbHandle) : 'inserted';
		} else {
			return false; // shouldn't happen
		}
	}

	// this function can be called in 2 ways
	// getEntityId( created, tableName, array( testColumn => testValue, etc.)[, idColumn][, extra] )
	// or
	// getEntityId( created, tableName, testColumn, testValue[, idColumn][, extra] )
	// "created" is passed by reference and used to let the caller know if the row had to be created or not.

	function getEntityId( &$created, $tableName, $testColumn, $testValue='', $idColumn='', $extra='' ) {
		$queryData = array(" FROM `$tableName` WHERE ");

		if (is_array($testColumn)) {
			foreach( $testColumn as $key=>$value ) {
				$queryData[0] .= "`$key`=? AND ";
				$queryData[] = $value;
			}
			$queryData[0] = substr( $queryData[0], 0, -4);
			// shuffle the inputs along
			$extra = $idColumn;
			$idColumn = $testValue;
		} else {
			$queryData[0] .= "`$testColumn`=? ";
			$queryData[] = $testValue;
		}

		if (!$idColumn) $idColumn = 'id';
		$queryData[0] = "SELECT `$idColumn` ".$queryData[0];

		$id = $this->getValue($queryData);
		if (!$id) {
			$created=true;
			if (!is_array($extra)) $extra = array();
			if (is_array($testColumn)) {
				$extra = array_merge( $extra, $testColumn );
			} else {
				$extra[$testColumn] = $testValue;
			}

			$id = $this->insert($tableName, $extra);
		} else {
			$created=false;
		}

		return $id;
	}

	// This table relies on the fact that the associated table has the following rows
	// lockTs
	// lockId
	// In order to prevent a row from being locked in future set the lockId to 0
	// but leave the lockTs set to something greater than 0
	// If only one column is requested (by passing cols as a string) then the values of this column from all rows are returned as an array
	// If more than one column is requested (by passing an array of cols) then an array of arrays is returned.
	function lockRows( $tableName, $cols='', $numRows=100, $lockTimeout=3600 ) {

		$lockId = getmypid()*10000+mt_rand(0,9999);

		// unlock any rows for which the lock has timed out
		$this->exec("UPDATE `$tableName` SET lockId=0, lockTs=0 WHERE lockId>0 AND lockTs<(UNIX_TIMESTAMP()-".((int)$lockTimeout).")");

		// attempt to lock some new rows
		$numLocked = $this->exec("UPDATE `$tableName` SET lockTs=UNIX_TIMESTAMP(), lockId=? WHERE lockId=0 AND lockTs=0 LIMIT ".((int)$numRows),$lockId);
		// if there were no rows locked then return an ampty array
		if (!$numLocked) return( array() );

		$cols = trim($cols);
		if ($cols == '') $cols = '*';

		if (is_array($cols) or $cols=='*') {
			if (is_array($cols)) {
				$cols = implode('`,`',$cols);
			}
			$rows = $this->getRows("SELECT `$cols` FROM $tableName WHERE lockId=?",$lockId);
		} else {
			// return the ids that we got a lock on
			$rows = $this->getColumn("SELECT `$cols` FROM $tableName WHERE lockId=?",$lockId);
		}

		return $rows;
	}

	function escape( $str ) {
		return mysqli_real_escape_string($this->dbHandle, $str);
	}

	function oneToManyUpdate( $table, $lookupColumn, $lookupValue, $dataColumn, $dataValues, $removeExisting = true, $extraLookupHash = null ) {
        $changed = false;

        if (!is_array($extraLookupHash)) $extraLookupHash=[];
        $extraWheres = $this->conditionsFromHash( $extraLookupHash );
		if(is_array($extraWheres)) {
			$extraWheresSql = array_shift($extraWheres);
        	$extraWheresValues = array_shift($extraWheres);
			if(!is_array($extraWheresValues)) $extraWheresValues = array($extraWheresValues);
		} else {
			$extraWheresSql = $extraWheres; // 1=1
			$extraWheresValues = array();
		}

		if (!is_array($dataValues)) $dataValues=array($dataValues);
        # remove any entries where the datavalue is an empty string
        pruneHash( $dataValues, array(''),0,1,0);

		$currentValues = $this->getColumn("SELECT $dataColumn FROM $table WHERE $lookupColumn=? AND $extraWheresSql",$lookupValue, ...$extraWheresValues);
        if($removeExisting) {
            # Delete any existing records that are no longer required
            if(count($dataValues)) {
                $changed = $this->exec("DELETE FROM $table WHERE $lookupColumn=? AND $dataColumn NOT IN (?) AND $extraWheresSql",$lookupValue,$dataValues, ...$extraWheresValues);
            } else {
                $changed = $this->exec("DELETE FROM $table WHERE $lookupColumn=? AND $extraWheresSql",$lookupValue, ...$extraWheresValues); // NOT IN doesn't work with no values due to substituting in 'NULL'
            }
        }

		foreach ($dataValues as $dataValue) {
			if (!in_array($dataValue, $currentValues)) {
                $changed = true;
				$this->insert($table,array_merge($extraLookupHash,array(
					$lookupColumn	=> $lookupValue,
					$dataColumn		=> $dataValue
				)), '', '', true);
			}
		}

		return $changed;
	}

    function getEnumValues($table, $column) {
        $info = $this->getRow('SHOW COLUMNS FROM `'.$this->escape($table).'` WHERE FIELD = ?', $column);
        if(!isset($info['Type'])) return array();
        $default = $info['Default'];
        $info = $info['Type'];
        if(strpos($info, 'enum(') !== 0) return array();

        $info = str_getcsv(substr($info, 5, -1), ',', "'");
        $values = array();

        // put the default at the start of the return array
        foreach($info as $value) {
            if ($value === '') continue;
            if ($value === $default) {
                $values[ucwords($value)] = $value;
                break;
            }
        }

        foreach($info as $value) {
            if ($value === '') continue;
            if ($value === $default) continue;
            $values[ucwords($value)] = $value;
        }

        return $values;
    }
}

interface DbifQueryMonitorInterface {
    /**
     * $execTime - in uSecs, see getUSTime()
     * Query vars should be passed after $query
     */
    public function execNotify($db, $rowCount, $execTime, $query);
    public function selectNotify($db, $rowCount, $execTime, $query);
}

class DefaultQueryMonitor implements DbifQueryMonitorInterface {
    private $canLog = true;
    private $baseDirLen = 0;

    public function __construct() {
        $this->baseDirLen = strlen(realpath(SITE_BASE_DIR."/www")) - 3; // account for the symlinking when we strip the paths later
    }

    /**
     * Query vars should be passed after $query
     */
    public function execNotify($db, $rowCount, $execTime, $query) {
        global $DB_LOG_ALL;
        if(
            $this->canLog && (
                (DB_LOG_ONE_IN && rand(1, DB_LOG_ONE_IN) == DB_LOG_ONE_IN) ||
                (DB_LOG_SLOW_THRESHOLD && $execTime > DB_LOG_SLOW_THRESHOLD * 1000000) ||
                $DB_LOG_ALL
            )
        ) {
            $this->writeLog('exec', ...func_get_args());
        }
    }

    /**
     * Query vars should be passed after $query
     */
    public function selectNotify($db, $rowCount, $execTime, $query) {
        global $DB_LOG_ALL;
        if(
            $this->canLog && (
                (DB_LOG_ONE_IN && rand(1, DB_LOG_ONE_IN) == DB_LOG_ONE_IN) ||
                (DB_LOG_SLOW_THRESHOLD && $execTime > DB_LOG_SLOW_THRESHOLD * 1000000) ||
                $DB_LOG_ALL
            )
        ) {
            $this->writeLog('select', ...func_get_args());
        }
    }

    private function writeLog($type, $db, $rowCount, $execTime, $query) {
        if(!$this->canLog) {
            return;
        }

        $logData = [
            'runAt' => time(),
            'file' => '',
            'line' => 0,
            'type' => $type,
            'slowQuery' => $execTime > DB_LOG_SLOW_THRESHOLD * 1000000 ? 1 : 0,
            'rowCount' => $rowCount,
            'execTime' => $execTime,
            'vars' => json_encode(array_slice(func_get_args(), 5)),
            'query' => trim(preg_replace('/\s+/m', ' ', $query)),
        ];

        [$logData['file'], $logData['line']] = $this->getNonPrdlCallSite();

        $this->canLog = false;
        $db->insert('dbLog', $logData);
        $this->canLog = true;
    }

    private function getNonPrdlCallSite() {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $file = '';
        $line = 0;
        foreach($backtrace as $debugData) {
            if(strpos($debugData['file'], 'lib/prdl') === false) {
                $file = substr($debugData['file'], $this->baseDirLen);
                $line = $debugData['line'];
                break;
            }
        }

        return [
            0 => $file,
            1 => $line,
            'file' => $file,
            'line' => $line,
        ];
    }
}
