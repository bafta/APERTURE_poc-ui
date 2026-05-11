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
            INSERT INTO question (id, question, type) VALUES
                (1, "What is your age group?", "radio"),
                (2, "What is your gender identity?", "radio"),
                (3, "What is your ethnic group?", "radio"),
                (4, "Do you consider yourself to have a disability?", "radio"),
                (5, "What is your sexual orientation?", "radio"),
                (6, "What is your religion or belief?", "radio"),
                (7, "Do you have any caring responsibilities?", "checkbox"),
                (8, "What best describes the occupation of your main household earner when you were aged 14?", "radio")
        ');
        $DB->exec('
            INSERT INTO option (questionId, num, option) VALUES
                (1, 1, "Under 18"),
                (1, 2, "18–24"),
                (1, 3, "25–34"),
                (1, 4, "35–44"),
                (1, 5, "45–54"),
                (1, 6, "55–64"),
                (1, 7, "65 or over"),
                (1, 8, "Prefer not to say"),

                (2, 1, "Man"),
                (2, 2, "Woman"),
                (2, 3, "Non-binary"),
                (2, 4, "Prefer to self-describe"),
                (2, 5, "Prefer not to say"),

                (3, 1, "White"),
                (3, 2, "Mixed or multiple ethnic groups"),
                (3, 3, "Asian or Asian British"),
                (3, 4, "Black, African, Caribbean or Black British"),
                (3, 5, "Other ethnic group"),
                (3, 6, "Prefer not to say"),

                (4, 1, "Yes"),
                (4, 2, "No"),
                (4, 3, "Prefer not to say"),

                (5, 1, "Heterosexual / straight"),
                (5, 2, "Gay or lesbian"),
                (5, 3, "Bisexual"),
                (5, 4, "Other"),
                (5, 5, "Prefer not to say"),

                (6, 1, "No religion"),
                (6, 2, "Christian"),
                (6, 3, "Muslim"),
                (6, 4, "Hindu"),
                (6, 5, "Jewish"),
                (6, 6, "Sikh"),
                (6, 7, "Buddhist"),
                (6, 8, "Other"),
                (6, 9, "Prefer not to say"),

                (7, 1, "Primary carer of a child or children under 18"),
                (7, 2, "Primary carer of a disabled adult (18 or over)"),
                (7, 3, "Primary carer of an older person"),
                (7, 4, "Secondary carer"),
                (7, 5, "No caring responsibilities"),
                (7, 6, "Prefer not to say"),

                (8, 1, "Modern professional or senior manager"),
                (8, 2, "Clerical or intermediate"),
                (8, 3, "Technical or craft"),
                (8, 4, "Routine, semi-routine or manual"),
                (8, 5, "Long-term unemployed"),
                (8, 6, "Retired"),
                (8, 7, "Not applicable"),
                (8, 8, "Prefer not to say")
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
