<?

// Ascertain the SITE_BASE_DIR
// ===================================================================
//
// This relies on the fact that for all PRDL deployments this file (startup.php)
// will be contained in "<SITE_BASE_DIR>/lib/prdl/"
// so we use __FILE__ to find the full path and name of this script and then
// go up two directories to get to the site base
// HOWEVER this doesn't work if there is a symink in the path so we use two approaches...
// First of all try and use DOCUMENT_ROOT
if (isset($_SERVER['DOCUMENT_ROOT']) && strlen($_SERVER['DOCUMENT_ROOT'])) {
    $SITE_BASE_DIR = dirname($_SERVER['DOCUMENT_ROOT']).'/';
} else {
    $SITE_BASE_DIR = dirname(dirname(dirname(__FILE__))).'/';
}

// Define some useful variables
// ===================================================================
$LIB_DIR = $SITE_BASE_DIR.'lib/';
$PRDL_DIR = $LIB_DIR.'prdl/';
$VIEWS_DIR = $SITE_BASE_DIR.'views/';
$CONFIG_DIR = $SITE_BASE_DIR.'config/';
$DATA_DIR = $SITE_BASE_DIR.'data/';
$PRDL_CONFIG = array();

define('LIB_DIR', $LIB_DIR); 
define('SITE_BASE_DIR', $SITE_BASE_DIR); 
define('PRDL_DIR', $PRDL_DIR); 
define('VIEWS_DIR', $VIEWS_DIR); 
define('CONFIG_DIR', $CONFIG_DIR); 
define('DATA_DIR', $DATA_DIR); 

require_once($PRDL_DIR."Dbif.php");
// Load in the logging code
// ===================================================================
// This is done early on so that we can log any errors that occur during startup
require_once($PRDL_DIR."logging.php");
$LOGGER = new Logger();

// Load in config
// ===================================================================
//
// The default config file
if (file_exists($CONFIG_DIR.'common.php')) include_once($CONFIG_DIR.'common.php');

// Site specific configuration can be loaded by setting the environment
// variable "CONFIG_LOCATION".
// This can usually be done in the Apache configuration.
// It is best to do this in the main apache config and not in htaccess
// since htaccess files may get copied from dev environments to live.
// This tells the script where to find its site-specific configuration data.
// This means that live, dev and staged etc. can use different configuration data
// by setting different values for this enviornment variable in the corresponding VirtualHost configuration
// This variable need not be set
// The site specific config file is loaded after the default config file
// so that this can override values found in the default config.
$configLocation = '';
if (isset($_SERVER["CONFIG_LOCATION"])) {
	$configLocation = $_SERVER["CONFIG_LOCATION"];
} else if (isset($_ENV["CONFIG_LOCATION"])) {
	$configLocation = $_ENV["CONFIG_LOCATION"];
}

if ($configLocation) { 
	// change backslashes to forward slashes
	$configLocation = str_replace('\\','/',$configLocation);
	// see if the path is absolute
	$configLocationError = false;
	if ( preg_match('/^(\\w:)?\\//',$configLocation) ) {
		if (!file_exists($configLocation) ) $configLocationError = true;
	} else if ( file_exists($CONFIG_DIR.$configLocation) ) {
		$configLocation = $CONFIG_DIR.$configLocation;
	} else $configLocationError = true;

	if ($configLocationError) {
		$LOGGER->log("Couldn't find specified config file '$configLocation'");
		exit;
	} else {
		require_once($configLocation);
        $localConfig = substr($configLocation,0,-4).'_local.php';
        if (file_exists($localConfig)) require_once($localConfig);
	}
}

// Pull in associated library files
// ===================================================================
require_once($PRDL_DIR."tools.php");
require_once($PRDL_DIR."validation.php");

// Connect to the DB if one is specified in config
// ===================================================================
// Small class for handling DB errors
if (!class_exists('DbErrorHandler')) {
	class DbErrorHandler extends DbifDefaultErrorHandler {
		function handleError($code, $basicMessage, $detailedMessage) {
			global $LOGGER;
			// Log error message
			$LOGGER->log("Database error: $basicMessage - $detailedMessage\n");
		}
	}
}

defaultValue('localhost',$PRDL_CONFIG['dbHost']);
defaultValue('',$PRDL_CONFIG['dbUsername'],$PRDL_CONFIG['dbPassword']);

if (isset($PRDL_CONFIG['dbName'])) {
	// Connect to DB
	$errorHandler = new DbErrorHandler();
	$DB = new Dbif( $PRDL_CONFIG['dbName'], $PRDL_CONFIG['dbUsername'], $PRDL_CONFIG['dbPassword'],$PRDL_CONFIG['dbHost'], $errorHandler );
	if (!$DB->connected()) {
		$LOGGER->log("Failed to connect to database - aborting\n");
		exit;
	}
    $DB->setQueryMonitor(new DefaultQueryMonitor());
}

// Define some more useful variables
// ===================================================================
if (!defined('IS_DEV')) define('IS_DEV',false);

// Setup the workspace and perform default input validation
// ===================================================================

if (!isset($INPUTS)) $INPUTS = array();
if (isset($INPUTS_FROM_FILE)) deriveInputsFromFile($INPUTS_FROM_FILE);

$WS = array();
$INPUT = new InputValidator( $INPUTS );

// Call the appStartup page if it exists
if (file_exists($LIB_DIR.'appStartup.php')) include_once($LIB_DIR.'appStartup.php');
