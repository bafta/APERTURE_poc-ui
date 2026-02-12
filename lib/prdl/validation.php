<?
/*
Syntax
---------------------------------------------------
$spec = array(
	'modeRegexp' => array(
		'fieldName'	=> "[<source>:]<type>[(<details>)] [array] [strict|mandatory] [trim] [toupper|tolower]"
	)
)

N.B. the "mode" CGI parameter will always be parsed - there is no need to include this in any input specification. It will be treated as follows
	'mode'	=> "GET|POST:ALPHANUMERIC-TRIM-TOUPPER"

modeRegexp	=	Each set of input specifications is checked against the "mode" using the modeRegexp - if there is a match then this set of inputs is spec.s is processed. If modeRegexp is empty then the specifications are processed irrespective of the mode.
fieldName	=	The CGI field name
strict 		=	If this is present then the value must match the criteria otherwise an error condition is flagged
				If strict or mandaroty is not specified then any non-conformant data in this field
				is wiped but no error is flagged	
mandatory	=	If this value is not present or equal to the empty string (or empty array) then an error will be flagged
				If the value is non-conformant an error will be flagged
<source>	=	GET|POST|COOKIE|ANY
<type>		=	ID|INT|FLOAT|ALPHA|ALPHANUMERIC|TEXT|REGEXP|BOOL|BOOLINT
<details>	=
				for REGEXP this is the regular expression itself e.g. GET:REGEXP(/^[a-z]*$/si)
				for other types this takes the form ([<minSize>-]<maxSize>)
<modifier> = TRIM|TOUPPER|TOLOWER

N.B. source,type and modifier specifices are all case insensitive

Default 


Examples
---------------------------------------------------
$spec = array (
	'' => array(
		'myId'		=> "GET:ID(255) mandatory",	// myId must be an unsigned integer which is less than or equal to 255
												// it must be present in the GET data (not the POST)
		'myName'	=> "POST:TEXT(60)",			// myName must be a string of 60 or less characters
												// it will only be read from POST data
												// if no data or non-conforming data is found then the empty string will
												// be substituted.
));
									
*/

class InputValidator {

	var $spec;
	var $errors;
	
	function __construct( $theSpec ) {
		$this->errors = array();
		if (is_array($theSpec)) {
			$this->spec = $theSpec;
			$this->parse();
		}
	}
	
	function parse( $overwrite=true ) {
		global $WS;
		$mode = $this->getFieldValue( 'mode', 'GET|POST:ALPHANUMERIC TRIM' );
		$WS['mode'] = $mode;
        if(function_exists('apache_setenv')) apache_setenv('PRDL_MODE', $mode, false);

		// now iterate over all the keys in the specification to see if they match the mode
		foreach ( $this->spec as $modeRegexp=>$fieldSpecs ) {
			// if the modeRegexp is empty then this applies to all modes
			if ($modeRegexp==='' || preg_match( '/^(?:'.addslashes($modeRegexp).')$/', $mode )) {
				// OK so we need to load the fields specified
				foreach ( $fieldSpecs as $field=>$fieldSpec ) {
					if (!$overwrite && isset($WS[$field])) continue;
                    $value = $this->getFieldValue( $field, $fieldSpec );
                    if ($value !== false) $WS[$field] = $this->getFieldValue( $field, $fieldSpec );
				}
			}
		}
	}
	
	function addError( $field, $code, $details='' ) {
		if (!isset($this->errors[$field])) $this->errors[$field] = array();
		if (!isset($this->errors[$field][$code])) $this->errors[$field][$code] = array();
		$this->errors[$field][$code][] = $details;
	}
	
	function getFieldValue( $fieldName, $spec ) {
		global $INPUT_ERRORS;
		$spec = strtoupper(str_replace(' ','',$spec));
		
		// parse the field specification
		if (!preg_match('/^(?:(ANY:|(?:(?:GET|POST|COOKIE)\\|?)+):)?(ID|INT|FLOAT|ALPHA|ALPHANUMERIC|TEXT|JSON|ANY|REGEXP|BOOL|BOOLINT)\s*(\\(.*\\))?\s*(ARRAY)?(STRICT|MANDATORY)?(TRIM)?(TOUPPER|TOLOWER)?$/',$spec,$matches)) return false;
	
		// extract the parsed data into named variables
		list( $unused, $sources, $type, $typeDetails, $array, $strict, $trim, $caseTransform ) = array_merge($matches,explode(' ','        '));
		$array = strtoupper($array) == 'ARRAY';
		$type = strtoupper($type);
		
		// ANY source => GET|POST|COOKIE
		if ($sources == 'ANY') $sources = 'GET|POST|COOKIE';
		// empty source => GET|POST
		if ($sources == '') $sources = 'GET|POST';
		
		// See if we can find the parameter in the sources listed
		$sources = explode('|',$sources);

		$value = NULL;
		foreach( $sources as $source ) {
			// the field spec. parsing above means that only valid sources should be extracted
			// but as a fail safe we check again here (deliberatley use different method - i.e. not regexp)
			if ( $source!=='GET' && $source!=='POST' && $source!=='COOKIE' ) continue;
			$source = '_'.$source;
			// see if this parameter exists in this source
			if (isset($GLOBALS[$source]) && isset($GLOBALS[$source][$fieldName])) {
				$value = $GLOBALS[$source][$fieldName];
				// echo "Extracting $fieldName from $source => '$value'";
				break;
			}
		}
		
		// First lets deal with what happens if we can't find a value for this parameter
		if (!isset($value)) {
			// If the parameter wasn't mandatory then it doesn't much matter
			if ($strict!=='MANDATORY') {
				return false;
			} else {
				// But if it was mandatory then we need to flag an error
				$this->addError($fieldName,1);
			}
		}
		
		// Tidy up the variable based on its type
		
		if (is_array($value)) $values = $value;
		else $values = array($value);

		foreach( $values as &$value ) {
            if ($trim) {
                $value = trim($value);
            }

			if($caseTransform === 'TOUPPER') {
				$value = strtoupper($value);
			}
			else if($caseTransform === 'TOLOWER') {
				$value = strtolower($value);
			}

			if ($type!='ANY' && !is_string($value)) $value='';

			if ($type=='ANY') {
				# no need to do anything in this case				

			} else if ( strpos('|ALPHANUMERIC|TEXT|',$type) ) {
				# no need to do anything in this case				

			} else if ($type=='JSON') {
                $value = json_decode($value, $array);
                $array = false; // Used with JSON to signal array|object, set false so we don't get $value wrapped in another array

			} else if ($type=='ALPHA') {
				$value = preg_replace('/[^a-zA-Z]/','',$value);

			} else if ( $type=='FLOAT' ) {
				# Allow empty string values
				if (trim($value)==='') $value='';
				else $value = (float)preg_replace('/[^0-9\\.-]/','',$value);
				
            } else if ($type == 'BOOL' || $type == 'BOOLINT') {
                // false values don't get added into $WS so it's probably best to use BOOLINT until that's changed
                if(!is_bool($value)) {
                    if(is_numeric($value)) {
                        $value = $value != 0;
                    } else if(strtolower($value) === 'true') {
                        $value = true;
                    } else {
                        $value = false;
                    }
                }

                if($type == 'BOOLINT') {
                    $value = $value ? 1 : 0;
                }

			// If INT or ID or no type is specified or the type is invalid then assume INT as this is the most restrictive
			} else {
                $value = trim($value);
				# Allow empty string values
                if ($value!=='') {
                    $minus = substr($value,0,1)==='-'?-1:1;
                    $value = $minus * round(preg_replace('/[^0-9\\.]/','',$value),0);
                }
			}	
		}
		
		# CAREFUL - DON'T USE $value AFTER THIS POINT - it is still a reference to the last element in the array
		if ($array) return $values;
		return $values[0];
	}

}
