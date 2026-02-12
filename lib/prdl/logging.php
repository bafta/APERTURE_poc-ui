<?

class Logger {

	var $logFile;
	public static $severityLevel = array("info", "warn", "error");
	
	function __construct() {
		$this->logFile = false;
	}
	
	function log( $info , $severity = 0) {
		global $SITE_BASE_DIR, $_SERVER;
	
		$logSeverity = Logger::$severityLevel[$severity]??Logger::$severityLevel[0];
		
		// open the log file (if it's not already open)
		if (!$this->logFile) {

			// generate the log file name
			$logFilename = $SITE_BASE_DIR.'log/prdl_'.date('Ymd').'.log';
			
			// open the log file
			$this->logFile = @fopen( $logFilename, 'a');
			if (!$this->logFile or !is_resource($this->logFile)) {
				echo "Error writing to log file\n";
				exit;
			}

		}
		
		// add a newline to the message if it doesn't have one
		if (!preg_match('/[\\r\\n]$/',$info)) $info.="\n";
		
		if($logSeverity != 'info') {
			alert( $info, $logSeverity);
		}
		// write the message
		fputs( $this->logFile, date('H:i:s ').$_SERVER["SCRIPT_FILENAME"].' '.$info );
	
	}

	function __destruct() {
		// close the log file
		if (is_resource($this->logFile)) fclose($this->logFile);
	}
}

?>
