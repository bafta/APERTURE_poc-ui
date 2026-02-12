<?
function writeDbPassword($filename, $extraConfig = '') {
    // Generate a random password for the web app to use
    $password = '';
    for($i=32; $i; $i--) {
        $password.=chr(mt_rand(32, 126));
    }
    $password = md5($password);

    // Store the password in the web app config
    $config = "<? \$PRDL_CONFIG['dbPassword'] = '$password';\n";
    if(count($extraConfig)) {
        foreach($extraConfig as $key => $value) {
            $config.="\$PRDL_CONFIG['".addslashes($key)."'] = '".addslashes($value)."';\n";
        }
    }
    file_put_contents($filename, $config);
    return $password;
}

function makeDirectoriesWritable() {
    // Lets check that the reference directory exists first
    if(!file_exists(PERMISSIONS_REFERENCE_DIR)) {
        die("Can't find ".PERMISSIONS_REFERENCE_DIR." - this is required to determine permissions and ownership for directories which the web server writes to");
    }
    $reference = '--reference="'.PERMISSIONS_REFERENCE_DIR.'"';
    $directories = func_get_args();
    $BASE_DIR = array_shift($directories);
    foreach($directories as $directory) {
        $dir = "\"$BASE_DIR/$directory\"";
        echo `mkdir -p $dir`;
        // Directory ownership
        echo `chown $reference $dir`;
        // Directory permissions
        echo `chmod $reference $dir`;
    }
}

function isLossyTypeConversion($oldColDef, $newColDef) {
    $aliases = [
        'INT' => 'INTEGER',
        'DEC' => 'DECIMAL',
        'FIXED' => 'DECIMAL',
        'NUMERIC' => 'DECIMAL',
        'DOUBLE' => 'DOUBLE PRECISION',
        'REAL' => 'DOUBLE PRECISION',
    ];

    $types = [
        'STRING' => [
            'CHAR' => null,
            'VARCHAR' => null,
            'BINARY' => null,
            'VARBINARY' => null,
            'TINYTEXT' => 255,
            'TEXT' => 65535,
            'MEDIUMTEXT' => 16777215,
            'LONGTEXT' => 4294967295,
            'TINYBLOB' => 255,
            'BLOB' => 65535,
            'MEDIUMBLOB' => 16777215,
            'LONGBLOB' => 4294967295,
        ],
        // STRING needs to have the size last as there are duplicates,
        // flip all the rest so they're the same way round
        'ENUM' => array_flip(['ENUM', 'SET']),
        'INTEGER' => array_flip(['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INTEGER', 'BIGINT']),
        'FLOAT' => array_flip(['FLOAT', 'DOUBLE PRECISION']),
        'FIXED' => array_flip(['DECIMAL']),
    ];

    // vvv Should get:   TYPE         SIZE        UNSIGNED
    $defRegex = '/^\s*([A-Za-z]+)(?:\((.+)\))?\s*(unsigned)?/i';
    $oldDefParts = [];
    $newDefParts = [];
    if(!preg_match($defRegex, $oldColDef, $oldDefParts) || !preg_match($defRegex, $newColDef, $newDefParts)) {
        echo "Bad definition\n    old: $oldColDef\n    new: $newColDef\nvvvv See the following line for the table & column vvvv\n";
        return true;
    }
    // Make sure each array has at least three items so we don't get warnings below        
    $oldDefParts = array_merge($oldDefParts, [null, null, null]);
    $newDefParts = array_merge($newDefParts, [null, null, null]);


    $oldType = strtoupper($oldDefParts[1]);
    $oldDefData = [
        'category' => '',
        'type' => isset($aliases[$oldType]) ? $aliases[$oldType] : $oldType,
        'size' => (int)$oldDefParts[2],
        'signed' => (strtoupper($oldDefParts[3]) != 'UNSIGNED'),
    ];
    $newType = strtoupper($newDefParts[1]);
    $newDefData = [
        'category' => '',
        'type' => isset($aliases[$newType]) ? $aliases[$newType] : $newType,
        'size' => (int)$newDefParts[2],
        'signed' => (strtoupper($newDefParts[3]) != 'UNSIGNED'),
    ];

    foreach($types as $typeCategory => $typesData) {
        if(array_key_exists($oldDefData['type'], $typesData)) {
            $oldDefData['category'] = $typeCategory;
            // if the max size in the setup array is not null, use it
            // otherwise leave it with the size from the definition (set above)
            if($typesData[$oldDefData['type']] !== null) {
                $oldDefData['size'] = $typesData[$oldDefData['type']];
            }
        }
        if(array_key_exists($newDefData['type'], $typesData)) {
            $newDefData['category'] = $typeCategory;
            if($typesData[$newDefData['type']] !== null) {
                $newDefData['size'] = $typesData[$newDefData['type']];
            }
        }
    }

    if(
        $oldDefData['category'] != 'ENUM' &&
        $newDefData['category'] == 'ENUM'
    ) { // Anything to an enum type
        return true;
    }
    if(
        in_array($oldDefData['category'], ['STRING', 'ENUM']) &&
        in_array($newDefData['category'], ['INTEGER', 'FLOAT', 'FIXED'])
    ) { // Any string or enum type to any numeric type
        return true;
    }
    if(
        in_array($oldDefData['category'], ['FLOAT', 'FIXED']) &&
        in_array($newDefData['category'], ['INTEGER'])
    ) { // A fractional numeric type to an integer numeric type
        return true;
    }
    if(
        in_array($oldDefData['category'], ['FLOAT', 'FIXED']) &&
        in_array($newDefData['category'], ['FLOAT', 'FIXED']) &&
        $oldDefData['category'] != $newDefData['category']
    ) { // Changing either way between fixed and float
        return true;
    }
    if(
        in_array($oldDefData['category'], ['INTEGER', 'FLOAT', 'FIXED']) &&
        $newDefData['category'] == $oldDefData['category'] &&
        $oldDefData['size'] > $newDefData['size']
    ) { // A numeric type to a smaller numeric type
        return true;
    }
    if(
        in_array($oldDefData['category'], ['INTEGER', 'FLOAT', 'FIXED']) &&
        in_array($newDefData['category'], ['INTEGER', 'FLOAT', 'FIXED']) &&
        $oldDefData['signed'] != $newDefData['signed']
    ) { // Changing either way between signed and unsigned
        return true;
    }
    if(
        $oldDefData['category'] == 'STRING' &&
        $newDefData['category'] == 'STRING' &&
        $oldDefData['size'] > $newDefData['size']
    ) { // String type to a smaller string type
        return true;
    }

    return false;
}

function tableErrorExit($tableName, $tempTableName) {
    global $DB;
    $DB->exec("DROP TABLE `{$tableName}`");
    $DB->exec("UNLOCK TABLES");
    $DB->exec("RENAME TABLE {$tempTableName} TO `$tableName`");
    exit(1);
}

$__upgradeInterrupted = false;
function upgradeInterrupted() {
    global $__upgradeInterrupted;
    $__upgradeInterrupted = true;
}

function upgradeDb($baseDir, $databaseName, $password, $adminPassword, $tables, $username='') {
    global $DB, $mysqlDB, $webUserDB;

    include_once("$baseDir/lib/prdl/Dbif.php");

    class installToolsDbErrorHandler {
        private $suppress = false;

        function suppress($value) {
            $oldSuppress = $this->suppress;
            $this->suppress = (bool)$value;
            return $oldSuppress;
        }

        function handleError($errorCode, $basicMessage, $detailedMessage) {
            if(!$this->suppress) echo $detailedMessage."\n";
        }
    }

    if($username=='') $username = $databaseName;

    // Create the database (and swallow any errors about it existing already)
    echo `mysql -e "CREATE DATABASE \\\`$databaseName\\\`" 2>&1 | grep -v "database exists" `;

    // Create the web app db user
    echo `mysql -e "REVOKE ALL ON *.* FROM '$username'@'localhost'" 2>&1`;
    echo `mysql -e "DROP USER '$username'@'localhost'" 2>&1`;
    echo `mysql -e "FLUSH PRIVILEGES" 2>&1`;
    echo `mysql -e "CREATE USER '$username'@'localhost' IDENTIFIED BY '$password'" 2>&1`;
    echo `mysql -e "GRANT USAGE ON *.* TO '$username'@'localhost'" 2>&1`;
    echo `mysql -e "GRANT CREATE VIEW, SELECT, INSERT, UPDATE, ALTER, CREATE, CREATE TEMPORARY TABLES, DROP, DELETE ON \\\`$databaseName\\\`.* TO '$username'@'localhost'" 2>&1`;

    // Create the admin user
    $adminUsername = substr($databaseName, 0, 10).'_admin';
    // Drop the admin user if it already exists (which it shouldn't)
    echo `mysql -e "DROP USER IF EXISTS '$adminUsername'@'localhost'" 2>&1`;
    echo `mysql -e "CREATE USER '$adminUsername'@'localhost' IDENTIFIED BY '$adminPassword'" 2>&1`;
    echo `mysql -e "GRANT USAGE ON *.* TO '$adminUsername'@'localhost'" 2>&1`;
    echo `mysql -e "GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES, CREATE VIEW ON \\\`$databaseName\\\`.* TO '{$adminUsername}'@'localhost'" 2>&1`;
    echo `mysql -e "GRANT RELOAD ON *.* TO '$adminUsername'@'localhost'" 2>&1`;
    echo `mysql -e "GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES, CREATE VIEW ON \\\`mysql\\\`.* TO '{$adminUsername}'@'localhost'" 2>&1`;

    $errorHandler = new installToolsDbErrorHandler();
    $DB = new Dbif( $databaseName, $adminUsername, $adminPassword, 'localhost', $errorHandler);
    $mysqlDB = new Dbif( 'mysql', $adminUsername, $adminPassword, 'localhost', $errorHandler);
    $webUserDB = new Dbif( $databaseName, $username, $password, 'localhost', $errorHandler);

    // Remove the CREATE VIEW permission by manipulating the mysql tables - this won't then take effect until FLUSH PRIVILEGES is called later
    $mysqlDB->exec('UPDATE db SET Create_view_priv="N" WHERE User=? AND Db=? AND Host="localhost"', $username, $databaseName);

    if(!$DB->connected()) die("Couldn't connect to database");

    # The following is no longer available as of MySQL 5.7.4 - see further comments below
    # the following is required for the "ALTER IGNORE" used when adding indexes to work
    # $DB->exec('set session old_alter_table=1');

    echo `mysql -e "REVOKE ALL ON *.* FROM '$adminUsername'@'localhost'" 2>&1`;
    echo `mysql -e "DROP USER '{$adminUsername}'@'localhost'"`;

    $existingTables = array_flip($DB->getTables());
    $pid = getmypid();

    $maxTableNameLength = max(array_map('strlen', array_keys($tables)));

    global $__upgradeInterrupted;
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, 'upgradeInterrupted');
    pcntl_signal(SIGTERM, 'upgradeInterrupted');

    foreach($tables as $tableName => $columns) {
        if($__upgradeInterrupted) break;

        $allowLossyConversionCols = [];
        if(isset($columns['_allowLossyConversion'])) {
            $allowLossyConversionCols = forceCleanArray(explode(',', $columns['_allowLossyConversion']));
            $allowLossyConversionCols = array_combine($allowLossyConversionCols, $allowLossyConversionCols);
            unset($columns['_allowLossyConversion']);
        }

        $justCreated = false;
        $status = '';
        if(!isset($existingTables[$tableName])) {
            $columnName = array_key_first($columns);
            $columnDefinition = $columns[$columnName];
            $DB->exec("CREATE TABLE `$tableName` (`$columnName` $columnDefinition) ENGINE=MyISAM DEFAULT CHARSET=utf8");
            $justCreated = true;
            $status = 'created';
        }

        $existingColumns = array_flip($DB->getColumnNames($tableName));

        // remove any existing columns that no longer exist
        foreach($existingColumns as $columnName => $notUsed) {
            if(!isset($columns[$columnName])) {
                $DB->exec("ALTER TABLE `$tableName` DROP COLUMN `$columnName`");
                if($status == '') $status = 'removed unused column(s):';
                $status .= $columnName." ";
            }
        }

        # All of the temporary table shennanigans below are required because of the removal of ALTER TABLE IGNORE in MySQL 5.7.4
        # The workaround for this is to create a new copy of the table, do the alters on this and then insert select the data over.
        $tempTableName = 'temp_'.$tableName.'_'.$pid;
        $DB->exec("RENAME TABLE `$tableName` TO `{$tempTableName}`");
        # We have to use SHOW CREATE TABLE not CREATE TABLE LIKE because the latter doesn't preserve AUTO_INCREMENT_ID
        # We wait until we have moved the table to get the create SQL because at this point no more inserts can occur
        # so we know the insert ID will be stable.
        $createSql = $DB->getRow("SHOW CREATE TABLE `$tempTableName`");
        $createSql = preg_replace('/CREATE\s+TABLE\s+`(.*?)`/', "CREATE TABLE `$tableName`", $createSql[1]);
        $DB->exec($createSql);
        # if anything queries sneak in here between the create and the lock it doesn't matter.
        $DB->exec("LOCK TABLES `$tableName` WRITE, `{$tempTableName}` WRITE");

        // Force all tables to be UTF8
        $DB->exec("ALTER TABLE `{$tableName}` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci");

        $newColumns = [];
        $indexes = [];

        $existingIndexes = [];
        $getIndexQuery = $DB->query("SHOW INDEX FROM `$tableName`");
        $row = []; //Just adding this for PHPStan
        while($getIndexQuery->fetchInto($row)) {
            $existingIndexes[$row['Key_name']] = true;
        }

        $oldColumnDefs = [];
        $rowParts = [];
        $createTableRows = forceCleanArray(explode("\n", $createSql));
        foreach($createTableRows as $tableRow) {
            if(preg_match('/^`(.+)`\s+(.+)$/', $tableRow, $rowParts)) {
                $oldColumnDefs[$rowParts[1]] = $rowParts[2];
            }
        }

        foreach($columns as $columnName => $columnDefinition) {
            if(isset($existingColumns[$columnName])) {
                // This code doesn't handle changes to autoincrement columns
                if(preg_match('/AUTO_INCREMENT/i',$columnDefinition)) continue;
                if(preg_match('/PRIMARY KEY/i',$columnDefinition)) {
                    $DB->exec("ALTER TABLE `{$tableName}` DROP PRIMARY KEY");
                }
                if(!isset($allowLossyConversionCols[$columnName]) && isLossyTypeConversion($oldColumnDefs[$columnName], $columnDefinition)) {
                    echo str_pad($tableName, $maxTableNameLength, " ", STR_PAD_RIGHT).": possible lossy conversion of column `$columnName`. Add an '_allowLossyConversion' entry containing this column name to the table definition to allow it.\n";
                    tableErrorExit($tableName, $tempTableName);
                }
                $DB->exec("ALTER TABLE `{$tableName}` MODIFY COLUMN `$columnName` $columnDefinition");
            } else {
                if(preg_match('/^(PRIMARY\s+|UNIQUE\s+|INDEX\s*\()/i',$columnDefinition)) {
                    # for indexes
                    if(preg_match('/^PRIMARY/i',$columnDefinition)) {
                        if(!$justCreated && isset($existingIndexes[$columnName])) {
                            // benj 20170926 - I don't think we need to worry about dropping the index from the original table
                            //$DB->exec("ALTER TABLE `{$tempTableName}` DROP PRIMARY KEY");
                            $DB->exec("ALTER TABLE `$tableName` DROP PRIMARY KEY");
                        }
                    } else {
                        if(!$justCreated && isset($existingIndexes[$columnName])) {
                            // benj 20170926 - I don't think we need to worry about dropping the index from the original table
                            //$DB->exec("ALTER TABLE `{$tempTableName}` DROP INDEX `$columnName`");
                            $DB->exec("ALTER TABLE `$tableName` DROP INDEX `$columnName`");
                        }
                        $columnDefinition = preg_replace('/INDEX\s*\(/',"INDEX `$columnName` (",$columnDefinition);
                    }
                    $indexes[$columnName] = $columnDefinition;
                } else {
                    # for columns - do these later after we have sorted out the indexes
                    $newColumns[$columnName] = $columnDefinition;
                }
            }
        }

        foreach($newColumns as $columnName => $columnDefinition) {
            $DB->exec("ALTER TABLE `{$tableName}` ADD COLUMN `$columnName` $columnDefinition");
            $DB->exec("ALTER TABLE `{$tempTableName}` ADD COLUMN `$columnName` $columnDefinition");
        }

        foreach($indexes as $indexName => $indexDefinition) {
            $DB->exec("ALTER TABLE `{$tableName}` ADD $indexDefinition");
        }

        // Get the new table structure to see if anything actually changed...
        $newCreateSql = $DB->getRow("SHOW CREATE TABLE `$tableName`");

        // As an extra paranoia check we make sure no inserts snook in on the new temporary table
        $sneakyInserts = $DB->getValue("SELECT count(*) FROM `{$tableName}`");

        if(!$sneakyInserts && $newCreateSql[1] === $createSql) {
            // The table hasn't changed in any way so no need to do anything - just put it all back as it was
            if($status == '') $status = "unchanged";
            // Rename the original table back to its original name
            $DB->exec("DROP TABLE `{$tableName}`");
            $DB->exec("UNLOCK TABLES");
            $DB->exec("RENAME TABLE {$tempTableName} TO `$tableName`");
        } else {
            $status = "updated";
            // echo "\n$createSql\n============\n{$newCreateSql[1]}\n\n";
            $oldSuppress = $errorHandler->suppress(true);
            try { // Some errors now throw rather than return an error code
                $DB->exec("INSERT INTO `{$tableName}` SELECT * FROM `{$tempTableName}`");
            } catch (mysqli_sql_exception $ex) {
                $DB->lastError = 'Error '.$ex->getMessage();
            }
            $errorHandler->suppress($oldSuppress);
            if($DB->lastError) {
                echo str_pad($tableName, $maxTableNameLength, " ", STR_PAD_RIGHT).': '.$DB->lastError."\n";
                tableErrorExit($tableName, $tempTableName);
            }
            $DB->exec("DROP TABLE `{$tempTableName}`");
            $DB->exec("UNLOCK TABLES");
        }

        echo str_pad($tableName, $maxTableNameLength, " ", STR_PAD_RIGHT).": $status\n";
        $existingTables[$tableName] = 1;
    }

    if($__upgradeInterrupted) {
        echo "Upgrade interrupted by signal\n";
        exit(1);
    }
}
