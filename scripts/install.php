<?

function usage() {
    global $argv;
    echo "Usage: ".$argv[0]." <config_filename.php>\n";
    echo "The config file must exist in the 'config' directory\n";
    exit;
}

if(!isset($argv[1])) {
    usage();
}

$config = $argv[1];
if(strpos($config,'/') > 0) {
    $config = $_SERVER['PWD'].'/'.$config;
}

if(!isset($_SERVER['PWD'])) {
    $_SERVER['PWD'] = dirname(__FILE__).'/..';
}
$BASE_DIR = dirname(dirname($_SERVER['PWD'].'/'.$_SERVER['SCRIPT_NAME']));

define('SITE_BASE_DIR',$BASE_DIR);

echo "Working in base directory: $BASE_DIR\n";

echo "Reading config from $config\n";
if(!file_exists($config)) {
    usage();
}

require_once($BASE_DIR.'/lib/prdl/tools.php'); // used by some functions in installTools.php
require_once($BASE_DIR.'/lib/prdl/installTools.php');
require_once($BASE_DIR.'/lib/prdl/Dbif.php');
if(file_exists(dirname($config).'/common.php')) {
    include_once(dirname($config).'/common.php');
}
require($config);
if(!defined('PRODUCT') || strtoupper(PRODUCT) != 'APERTURE') {
    die('ERROR: This is the Aperture installer. The provided config file '.(
        defined('PRODUCT') ?
        'is for '.PRODUCT :
        'does not have a PRODUCT define'
    ).".\n");
}

// We use /www/logs as the reference to get permissions and ownership from
define('PERMISSIONS_REFERENCE_DIR', '/www/logs');

if(defined('SITE_DIR')) {
    $siteDir = SITE_DIR;
} else {
    $siteDir = SITE_NAME;
}

$tables = [
    'answer' => [
        'id' => 'INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY',
        'userId' => 'INTEGER NOT NULL',
        'questionId' => 'INTEGER NOT NULL',
        'optionNum' => 'INTEGER NOT NULL',
        'answer' => 'BLOB',
        'index_userId' => 'UNIQUE KEY (`userId`, `questionId`, `optionNum`)',
    ],
    'migration' => [
        'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
        'executedAt' => 'INT(10) UNSIGNED NOT NULL DEFAULT 0',
    ],
    'userAuthSecret' => [
        'id' => 'INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY',
        'userId' => 'INTEGER NOT NULL',
        'secret' => 'VARCHAR(64) NOT NULL',
        'createdAt' => 'INTEGER NOT NULL',
        'index_userId' => 'UNIQUE KEY (`userId`)',
    ],
    'option' => [
        'id' => 'INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY',
        'questionId' => 'INTEGER NOT NULL',
        'num' => 'TINYINT(3) NOT NULL',
        'option' => 'VARCHAR(255) NOT NULL',
    ],
    'question' => [
        'id' => 'INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY',
        'type' => 'ENUM("checkbox", "radio")',
        'question' => 'VARCHAR(255) NOT NULL',
    ],
];

if(isset($PRDL_CONFIG['dbPasswordFile'])) {
    $passwordFilename = $PRDL_CONFIG['dbPasswordFile'];
} else {
    $passwordFilename = 'dbPassword.php';
}

# The dbName and Host are also duplicated into the password file because this
# allows that password file to be used to setup a database connection for cross instance copying
# search for CROSS_INSTANCE_COPY_DESTINATIONS for more info
$password = writeDbPassword("$BASE_DIR/config/$passwordFilename", [
    'dbName' => $PRDL_CONFIG['dbName'],
    'dbHost' => $PRDL_CONFIG['dbHost'],
    'dbUsername' => $PRDL_CONFIG['dbUsername'],
]);
echo "Password written to $BASE_DIR/config/$passwordFilename\n";
$adminPassword = md5(microtime().'admin_'.$password);

global $DB, $mysqlDB, $webUserDB;

upgradeDb($BASE_DIR, $PRDL_CONFIG['dbName'], $password, $adminPassword, $tables, $PRDL_CONFIG['dbUsername']);

# run any post-upgrade functions that were defined in the instance specific script we included above i.e. $BASE_DIR.'/scripts/install/'.SITE_NAME.'.php'
if(function_exists('postUpgradeDbActions')) {
    postUpgradeDbActions();
}

$isBrandNewDatabase = $DB->getValue('SELECT COUNT(*) FROM migration') == 0;

$upgrades = ($upgrades ?? []) + [
    202603041924 => function() use($DB, $isBrandNewDatabase) {
        $DB->exec('
            INSERT INTO question (id, question, type)
            VALUES
                (1, "What pets do you have?", "checkbox"),
                (2, "What\'s your favourite food?", "radio")
        ');
        $DB->exec('
            INSERT INTO option (questionId, num, option)
            VALUES
                (1, 1, "Dog"),
                (1, 2, "Cat"),
                (1, 3, "Guinea Pig"),
                (1, 4, "Rat"),
                (1, 5, "Fish"),
                (2, 1, "Fish and Chips"),
                (2, 2, "Chinese"),
                (2, 3, "Pizza"),
                (2, 4, "Burger"),
                (2, 5, "Roast Dinner"),
                (2, 6, "Falafel")
        ');
    },
];

ksort($upgrades,SORT_NUMERIC);

if($isBrandNewDatabase) {
    echo "Running script in brand new database";
}

$migrations = $DB->getColumn('SELECT id FROM migration');
foreach($upgrades as $version => $code) {
    if(in_array($version, $migrations)) {
        continue;
    }
    
    echo "Running database migration $version\n";
    $code();

    $DB->insert('migration', ['id' => $version, 'executedAt' => time()]);
}

// We made some preemptive permission changes in installTools.php which won't take effect until we flush privileges so do this now
$mysqlDB->exec("FLUSH PRIVILEGES");

echo "All done\n";
