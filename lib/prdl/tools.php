<?

function curlGet( $url ) {
    $curl = curl_init($url);
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
    ));
    $result = curl_exec($curl);
    curl_close($curl);
    return $result;
}

# This function checks if the file exists with a variety of extensions added.
# The first parameter is the base filename (passed by reference)
# the second parameter is either an array of extensions to try
# or a single extension as a string - in this case this is interpreted as being an optional extension
# and the file is sought first without then with this extension.
function checkFileExists( &$file, $extension ) {
	if ( !is_array($extension) ) $extension = array( '', $extension );
    foreach($extension as $value) {
		# echo "looking for '$file$value'<BR />";
		if ( file_exists( $file.$value ) && is_file( $file.$value ) ) {
			$file = $file.$value;
			# echo "found it";
			return(1);
		}
	}
	return(0);
}

function wsParseDate( $wsField ) {
	global $WS;
    $dateStr = preg_replace('/(\\d+)\\D+(\\d+)\\D+(\\d+)/','$3-$2-$1',ws($wsField));
    $WS[$wsField] = strtotime( $dateStr );
}

function defaultValue( $default, &$var1 ) {
	$args = func_num_args()-1;
	if ($args>10) $args=10;
	for ($idx = $args; $idx>0; $idx--) {
		$var = 'var'.$idx;
		if (!isset($$var)) $$var = $default;
	}
}

function prdlError( $userError, $sysError, $exit=true ) {
	global $PRDL_CONFIG, $LOGGER, $_SERVER;
	$LOGGER->log($sysError);

	if (isset($PRDL_CONFIG['errorEmail'])) {
		$subject = "PRDL encountered error on ".$_SERVER['HTTP_HOST'];
		$body = "<H1>Error encountered</H1><HR>\n<H2>$userError</H2>\n$sysError\n";
		emailError($PRDL_CONFIG['errorEmail'],$subject,$body);
	}

	if ( isset($PRDL_CONFIG['errorDisplay']) && $PRDL_CONFIG['errorDisplay'] ) {
		echo "<H1>Error encountered</H1><HR>\n<H2>$userError</H2>\n<!-- $sysError -->\n<HR>";
		if ($exit) exit;
	} else if (strlen($userError) && $exit) {
		$redirect = $PRDL_CONFIG['errorRedirectUrl'];
		if (!headers_sent()) {
			defaultValue('/systemError.html',$redirect);
			header("Location: $redirect");
			exit;
		} else {
			?>
				//<SCRIPT>
				//<!--
					window.location.replace('<?=addslashes($redirect)?>');
				//-->
				//</SCRIPT>
			<?
			exit;
		}
	}

}

function getPageDebugInfo() {
	ob_start();
	phpinfo();
	echo "<HR><PRE>";
	print_r( $GLOBALS );
	echo "</PRE>";
	$info = ob_get_contents();
	ob_end_clean();
	return $info;
}

function emailError( $email, $subject, $body ) {
	$body = "
		Page: ".$_SERVER['DOCUMENT_ROOT']."<BR />
		URL: http://".$_SERVER['HTTP_HOST'].'/'.$_SERVER['REQUEST_URI']."
		<HR />
		$body
		<HR />
	".getPageDebugInfo();
	return mail($email,$subject,$body,"Mime-Version: 1.0\nContent-Type: text/html\n");
}

function htmlEcho( $str ) {
	echo htmlspecialchars( $str );
}

function jsEcho( $str ) {
	echo htmlspecialchars( addslashes($str) );
}

function wsp( $param, $echo=true, $convNL = false ) {
	global $WS;
    if (!isset($WS[$param]) ) return;
    $wsOut = htmlspecialchars($WS[$param]);
    if($convNL) $wsOut = nl2br($wsOut);
	if ($echo) echo $wsOut;
    else return $wsOut;
}

function ws( $param, $value=null ) {
	global $WS;
	if ($value!==null) {
		$WS[$param] = $value;
	}
	if (isset($WS[$param])) return $WS[$param];
	else return '';
}

function wsset($param) {
    global $WS;
    return isset($WS[$param]);
}

function wsunset($param,$removeFromCache=true) {
    global $WS;
    $return = (isset($WS[$param]))?$WS[$param]:'';
    unset($WS[$param]);
    if($removeFromCache) {
        $cacheKey = $_SERVER['SCRIPT_NAME'];
        unset($_SESSION['ADMIN_LIST_FILTER_CACHE']['cache'][$cacheKey][$param]);
    }
    return $return;
}

function jsStringEscape( $str ) {
	return addslashes(htmlspecialchars($str));
}

function forceArray( &$var ) {
	if (!isset($var)) {
		$var = array();
	} else if (!is_array($var)) {
		$var = array($var);
	}
	return $var;
}

/**
 * $finalValue : false - use row data and DON'T remove dimensions (default)
 *                true - use row data and DO remove dimensions
 *          int/string - use $finalValue element of row data
 */
function buildNDimensionalArray($data, $dimensions, $finalValue = false)  {
    if(!is_array($data) || !is_array($dimensions) || !count($dimensions)) return null;

    $formattedData = array();
    foreach($data as $dataRow) {
        $currentLevel = &$formattedData;
        foreach($dimensions as $dimension) {
            if(!isset($currentLevel[$dataRow[$dimension]]) || !is_array($currentLevel[$dataRow[$dimension]])) $currentLevel[$dataRow[$dimension]] = array();
            $currentLevel = &$currentLevel[$dataRow[$dimension]];
            if($finalValue === true) unset($dataRow[$dimension]);
        }
        $currentLevel = is_bool($finalValue) ? $dataRow : $dataRow[$finalValue];
    }
    return $formattedData;
}

# This function adds $what into $str at the first occurence of $where
# $where remains in the output
# $what is either inserted before or after $what as determined by $after - default is afterwards
function strSplice( $str, $where, $what, $after=1, $which='first' ) {
	if (!preg_match("/$where/", $str)) return $str;
    if ($which=='first') {
    	list( $first_bit, $last_bit ) = preg_split( "/$where/", $str,2 );
    } else {
        $bits = preg_split( "/$where/", $str );
        $last_bit = array_pop($bits);
        $first_bit = substr( $str, 0, -1 * strlen($last_bit));
    }

	$pos = strlen( $first_bit );
	$length = strlen($str) - strlen( $last_bit ) - strlen( $first_bit );
	if ( $pos == strlen( $str ) ) return $str;
	if ( $after ) { $pos += $length; }
	$new_str = substr( $str, 0, $pos ) . $what . substr( $str, $pos );
	return $new_str;
}

# this function adds in search conditions after the last " where " (case insensitive - will match WHERE or where but not WhErE or Where) in the sql search
# It grabs $sql by reference and applies the changes in-situ for you
# It also adds on extra conditions if they are supplied - in fact you can call it with an empty prefix to just add extra conditions
function addConditions( &$sql, $prefix , $extra='', $type='where' ) {
	$new_sql = ' ';
	if ($prefix != '') $new_sql .= makeConditions( $prefix );
	if ($extra !== '') $new_sql .= $extra.' AND ';
	$sql = strSplice( $sql, "[\r\n\t ]".convertToCaseInsensitiveRegExp($type)."[\r\n\t ]", $new_sql,1,'last' );
	return $sql;
}

global $_SEARCH_SPEC_LOOKUP_TABLE;
$_SEARCH_SPEC_LOOKUP_TABLE = array(
	'lk' => '%s LIKE \'%s\'',
	'rl' => '%s RLIKE \'%s\'',
	'ct' => '%s LIKE \'%%%s%%\'',
	'eq' => '%s = \'%s\'',
	'eq_numeric' => '%s = %s',
	'gt' => '%s > %s',
	'ge' => '%s >= %s',
	'lt' => '%s < %s',
	'le' => '%s <= %s',
	'ne' => '%s <> \'%s\'',
	'sw' => '%s LIKE \'%s%%\'',
	'ew' => '%s LIKE \'%%%s\'',
	'nl' => 'ISNULL(%s) = \'%s\'',
  # the followig are special cases
	'cm' => '',
	'cy' => '',
	'cl' => '',
	'in' => '%s IN (%s)',
    'on' => '(%s >= %s AND %s < %s)'
);

/*

	ct => Contains*'
	eq => Equals*
		- use lk if you want to test for emptiness
	gt => Greater than*+
	ge => Greater than or equal to*+
	ne => Not equal to
	lt => Less than*+
	le => Less than or equal to*+
	sw => Starts with*'
	ew => Ends with*'
	lk => Like'
	ct => Contains
	cy => Contains any*^
	cl => Contains all*^
	in => in*
	rl => RLike
	nl => ISNULL()
	cm => Compare - first character of value is used as type of comparison (<,>,=) if none supplied default is =
	on => on date (assumes input is dd/mm/yyyy and db field is Unix timestamp)

	* = Tests that will be ignored if empty
	+ = Numeric only
	^ = Extra negative logic applies
		syntax is "[^][!]word [[!]word]..."
	' = These tests accept "like" expression wild cards
		- see http://dev.mysql.com/doc/refman/4.1/en/string-comparison-functions.html
*/

function makeConditions( $prefix ) {
	global $WS, $DB;
	global $_SEARCH_SPEC_LOOKUP_TABLE;
	$condition_sql = '';
	$limited_param_space=0;
	if (is_array($prefix)) {
		$param_space = $prefix;
		$prefix = '';
		$limited_param_space=1;
	} else {
		$param_space = $WS;
	}

	reset($param_space);
    foreach($param_space as $full_var => $val) {

#		echo "Testing $full_var against $prefix<BR />";
		$sub_sql = '(';

		# next line is required to stop people submitting CGI variables with names of the form
		# "seemingly_OK_variable_but_includes|filter_bogus_filter_eq"
		# This might get past some CHECKS with the author of the code thinking its OK
		# 'cos it doesn't start filter_ but the explode below would have acted on the filter.
		if (!preg_match( "/^$prefix/i", $full_var )) continue;

		if ( is_object($val) ) continue;

		# Prevent SQL injection
		if (is_array($val)) {
			foreach($val as $key=>$value) {
				if (is_array($value) || is_object($value)) continue;
				$val[$key] = addslashes($value);
			}
		} else {
			$val = $DB->escape($val);
		}

		$sub_fields = explode('|',$full_var);
        foreach($sub_fields as $var) {
			$not=0;
			if (substr( $var, -4, 2 ) == '_!') {
				$not=1;
				$var = substr( $var, 0, -3 ).substr( $var, -2 );
			}
			if ( (substr( $var, -3, 1 ) == '_' || substr( $var, -4, 2 ) == '_!') && preg_match( "/^$prefix/i", $var ) ) {
				$var = preg_replace( "/^$prefix/i", '', $var );
#				echo "$var looks good<BR />";
				# just double check
				if ( substr( $var, -3, 1 ) == '_' || substr( $var, -4, 2 ) == '_!') {
					$end = substr( $var, -2 );
#					echo "checking '$end'<BR />\n";
					$start = substr( $var, 0, -3 );
					# DONT let the user put any ;'s in the column names - this could be bad
					# e.g. filter_;drop table blah; select_eq=1
					# This only applies if the param_space was set to the whole workspace ($WS)
					# Don't do this if we were passed in a specific param_space
					if (!$limited_param_space) $start = preg_replace( '/[^a-zA-Z0-9&:()_+*\/\\-\,]/','',$start );
					# replace : with .
					$start = str_replace( ':', '.', $start );
					# limit the length
					$start = substr( $start, 0, $limited_param_space?100:50 );

					if ( $end == 'gt' || $end == 'lt' || $end == 'ge' || $end == 'le') {
						// Reformat dates
						$val = preg_replace( '/(\\d{1,2})\\D(\\d{1,2})\\D(\d{2,4})/','$1-$2-$3',$val,1,$matches);
						if ($matches) {
							$val = strtotime($val);
                            # when using gt or le with dates we want to go from the end of the day not the start
                            if ( $val && $end == 'gt' || $end == 'le' ) {
                                $val+=86400;
                            }
						} else if($val !== '') {
							// empty string means no value submitted, which will be skipped later
							// don't convert it to 0 as that looks like the user has deliberately submitted a 0 value
							$val = (int)preg_replace( '/[^0-9\\.]/','', $val );
						}
					}
#					if ( $val == '' && strpos(' ,ct,eq,gt,lt,sw,ew,cy,cl,in,',",$end,") ) {
#						echo "Skipping empty $start $end<BR />\n";
#					}
					if ( $val === '' && strpos(' ,ct,eq,gt,ge,lt,le,sw,ew,cy,cl,cm,in,',",$end,") ) continue;
					if ( $end == 'in' && is_array($val) && (!count($val) || $val[0]== '') ) continue;
					# array values are only allowed for "in" filters
					if ( $end != 'in' && is_array($val) ) $val = implode(' ',$val);

					if ( isset( $_SEARCH_SPEC_LOOKUP_TABLE[$end] ) ) {
#						echo "adding ".sprintf( $_SEARCH_SPEC_LOOKUP_TABLE[$end], $start, (string)$val );
						switch ($end) {
							case 'cy':
							case 'cl':
								$not=0;
								if (substr($val,0,1)=='^') {
									$not = 1;
									$val = substr($val,1);
								}
								$join = $end=='cy'?'OR ':'AND';
								# remove multiple spaces, spaces of the end and spaces of the start
								$val = preg_replace( '/  +/', ' ', trim($val) );
								$test_values = explode( ' ', $val );
                                foreach($test_values as $test_value) {
									$thing = '';
									if (substr($test_value,0,1)=='!') {
										$thing = ' NOT';
										$test_value = substr($test_value,1);
									}
									$thing = " $start$thing LIKE '%".addslashes($test_value)."%' $join";
									$sub_sql .= $thing;
								}
								$sub_sql = substr($sub_sql,0,-4);
								if ($not) { $sub_sql = ' NOT'.$sub_sql.' '; }
								break;
                            case 'on':
								$time = strtotime($val);
								if (!$time) {
									if (!preg_match('/(\\d{1,2})\D(\\d{1,2})\D(\\d{1,4})/',$val,$matches)) {
										$sub_sql .= ' 1=1 ';
										break;
									}
									if ($matches[3]<70) $matches[3]+=2000;
									if ($matches[3]<100) $matches[3]+=1900;
									$time = strtotime($matches[3].'-'.$matches[2].'-'.$matches[1]);
								}
								if (!$time) {
                                    $sub_sql .= ' 1=1 ';
                                    break;
                                }
                                $sub_sql .= sprintf( $_SEARCH_SPEC_LOOKUP_TABLE[$end], $start, $time, $start, $time+86400 );
                                break;
							case 'in':
								# allow comma-separated numerical lists
								if (is_array($val)) {
									if (!count($val)) break;
									$val = "'".implode("','",$val)."'";
								} else if (preg_match('/^(\\d+,)+\\d+$/',$val)) {
									# do nothing - allow $val through as it is
								} else {
									$end = 'eq';
								}
							default:
								if (!is_array($val)) $val = array($val);
								if (!count($val)) $sub_sql .= ' 1=1 ';
								else {
									$sub_sql .= ' ( ';
									foreach ( $val as $term ) {
										$not=0;
										if (substr($term,0,1)=='!') {
											$not = 1;
											$term = substr($term,1);
										}
										if ($not) $sub_sql .= ' NOT(';

                                        // Convert the special value "___EMPTY___" into an empty string
                                        if ($term==='___EMPTY___') $term='';

										if ($end=='cm') {
											if (strpos(' <>=',$term[0])) {
												$test = $term[0];
												$term = substr( $term,1 );
											} else $test='=';

                                            // if $term is not entirely numeric then quote it
                                            if ( !preg_match('/^\\d+$/',$term) ) $term = '\''.$term.'\'';
											$sub_sql .= ''.$start.' '.$test.' '.$term;
										} else if ($end=='eq' && $term==='__NULL__') {
                                            $sub_sql .= sprintf('ISNULL(%s)',$start);
										} else if ($end=='eq' && preg_match('/^\\d+$/',$term)) {
                                            $sub_sql .= sprintf($_SEARCH_SPEC_LOOKUP_TABLE['eq_numeric'], $start, (string)$term);
                                        } else $sub_sql .= sprintf($_SEARCH_SPEC_LOOKUP_TABLE[$end], $start, (string)$term);


										if ($not) $sub_sql .= ') ';
										$sub_sql .= ' AND ';
									}
									$sub_sql = substr($sub_sql,0,-5);
									$sub_sql .= ' ) ';
								}
						}
						$sub_sql .= ' OR ';
					}
				}
			}
		}
		if (strlen($sub_sql) > 1) {
			$sub_sql = substr( $sub_sql, 0, -3). ') AND ';
			$condition_sql .= $sub_sql;
		}
	}
	return($condition_sql);
}

/**
 * WS entries in the form $prefix<priority> => table:column:direction
 * eg (
 *     'order_0' => entry:status:asc,
 *     'order_1' => user:firstName:desc,
 * )
 * Returns bool Whether or not any order by items were added to $sql
 */
function addOrder(&$sql, $prefix) {
    global $WS;

    $orderItems = array();
    foreach($WS as $key => $value) {
        if(strpos($key, $prefix) === 0) $orderItems[$key] = $value;
    }
    if(!count($orderItems)) return false;

    ksort($orderItems);

    $orderSql = ' ';
    foreach($orderItems as $orderItem) {
        $orderItem = explode(':', $orderItem);
        if(count($orderItem) != 2) continue;
        $orderSql .= $orderItem[0].' '.strtoupper($orderItem[1]).', ';
    }
    $sql = strSplice($sql, "[\r\n\t ]".convertToCaseInsensitiveRegExp('ORDER BY')."[\r\n\t ]", $orderSql, 1, 'last');
    return true;
}

function deriveInputsFromFile( $files='' ) {
	global $_SERVER, $INPUTS,$SITE_BASE_DIR;
	if (!isset($INPUTS['.*'])) $INPUTS['.*'] = array();

	// get the right filename
	// if no file is specified then use the current file
	if ($files==='') $files = $_SERVER["DOCUMENT_ROOT"].$_SERVER["SCRIPT_NAME"];

	forceArray($files);
	
	foreach( $files as $file ) {
		if (!strlen($file)) continue;
		// if the filename doesn't start with "/" then it is a relative path - make it absolute by adding the base_dir
		if (strpos($file, DIRECTORY_SEPARATOR)!==0) $file = $SITE_BASE_DIR.$file;

		// ignore this file if it doesn't exist;
		if (!file_exists($file)) continue;

		// read in the file data and split it into HTML tags
		$contents = file_get_contents( $file );
		
		$tags = explode('<',$contents);
		foreach( $tags as $tag ) {

			// ignore this if it isn't a tag
			if (!strpos($tag,'>')) continue;
			// ignore all closing tags
			if (preg_match('/^\s*\\//',$tag)) continue;

			if (preg_match('/\\w+/',$tag,$matches)) {
				// ignore any tag that doesn't contain form data
				if (!strpos(' |INPUT|TEXTAREA| ', strtoupper("|{$matches[0]}|"))) continue;

				// find the name attribute
				if (preg_match('/\\s+NAME\\s*=(["\'])(.*?)\\1/smi',$tag,$matches)) {
					$fieldType = 'ALPHANUMERIC';
					$fieldName = trim($matches[2]);
					// remove [] from the end of array fields
					$fieldName = preg_replace('/\\[\\]\\Z/','',$fieldName,1,$matches);
					if ($matches) $fieldType.=' ARRAY';
					// Don't override inputs definitions that already exist
					if (!isset($INPUTS['.*'][$fieldName])) $INPUTS['.*'][$fieldName] = $fieldType;
				}
			}
		}

		// Now look for PRDL function calls in the source
		extractFunctionInputs('/\bform(?:Label|Field|Hidden|Textbox|Textarea|Placeholder|Optionbox|Checkbox|Radio|Phone|YesNo|Picklist|Number|Date|Time|defaultFormField)\s*\(\s*\[\s*[^]]*?\'name\'\s*=>\s*(["\'])(.*?)\1/i', $contents, 2);
		extractFunctionInputs('/(?:form(?:Label|Field|Hidden|Textbox|Textarea|Placeholder|Optionbox|Checkbox|Radio|Phone|YesNo|Picklist|Number|Date|Time)|defaultFormField)\\(\s*(["\'])(.*?)\\1/i', $contents, 2);
		extractFunctionInputs('/formBox\\(\s*(["\'])(.*?)\\1\s*,\s*(["\'])(.*?)\\3/i', $contents, 4);
	}
}

function extractFunctionInputs($regex, $contents, $matchIdx) {
    global $INPUTS;
	$alreadyDefinedInputs = [];
	foreach($INPUTS AS $alreadyDefinedInput) {
		if(!empty($alreadyDefinedInput)) {
			$alreadyDefinedInputs = array_merge($alreadyDefinedInputs,array_keys($alreadyDefinedInput));
		}
	}
	if (preg_match_all($regex, $contents, $matches)) {
		foreach( $matches[$matchIdx] as $fieldName ) {
            $fieldType = 'ALPHANUMERIC';
            // remove [] from the end of array fields
            $fieldName = preg_replace('/\\[\\]\\Z/', '', trim($fieldName), 1, $isArray);

			if(in_array($fieldName,$alreadyDefinedInputs)) {
				continue;
			}

            if ($isArray) $fieldType.=' ARRAY';
            if (!isset($INPUTS['.*'][$fieldName])) $INPUTS['.*'][$fieldName] = $fieldType;
        }
    }
}

function dump( &$vars ) {
	echo "<pre>";
	print_r($vars);
	echo "</pre>";
}


/*
This routine uses prune_list to remove entries from target_hash
Depending on the values of target_key either the key (target_key=1) or the value (target_key=0) of each entry in target_hash is chechked to see if it occurs in the prune list.
Depending on the value of list_key the data as to which entries to prune is taken from either the keys or the values of the prune_list
i.e.
	list_key=0 => prune out entries by testing against keys in the prune list
	list_key=1 => prune out entries by testing against the values in the prune list
The test parameter defines what compairson is used
	0 -> exact comparison
	1 -> case insensitive comparison
	2 -> case sensitive regular expression
	3 -> case insensitive regular expression
Add 10 to test to negate it
*/

function pruneHash( &$target_hash, $prune_list, $target_key = 1, $list_key = 1, $test = 2 ) {

	if ( strtolower($target_key) == 'key' ) $target_key = 1;
	if ( strtolower(substr($target_key,0,3)) == 'val' ) $target_key = 0;
	if ( strtolower($list_key) == 'key' ) $list_key = 0;
	if ( strtolower(substr($list_key,0,3)) == 'val' ) $list_key = 1;
	reset( $target_hash );
	if (!is_array($prune_list)) {
		$prune_list = array( $prune_list );
		$list_key = 1;
	}
	$negate = 0;
	if ( $test > 9 ) {
		$test -= 10;
		$negate = 1;
	}
    foreach($target_hash as $key => $value) {
		$compare = $target_key?$key:$value;
		if ( $test==1 ) $compare = strtolower($compare);
#		echo "testing $compare (negate = $negate)<BR />";
		# check if this is to be excluded
		# this first one is a special case which is really easy
		if ( !$list_key && ($test == 0) ) {
			if ( ( isset($prune_list[$compare]) && !$negate ) || ( !isset($prune_list[$compare]) && $negate ) ) {
				unset( $target_hash[$key] );
			}
		} else {
			# otherwise we have to plod our way through the prune list
			reset( $prune_list );
            foreach($prune_list as $subvalue) {
				if ( $test==1 ) $subvalue = strtolower($subvalue);
#				echo "testing '$subvalue' against '$compare'<BR />";
				if (
					( ( $test==1 || $test == 0 ) && ( $subvalue == $compare ) ) ||
					( $test==2 && preg_match( '/'.$subvalue.'/', $compare ) ) ||
					( $test==3 && preg_match( '/'.$subvalue.'/i', $compare ) )
					) {
					if (!$negate) unset( $target_hash[$key] );
					break;
				} else {
					if ($negate) unset( $target_hash[$key] );
				}
			}
		}
	}
}

# This function takes a hash and a regexp string as inputs
# It returns a hash consisting of all elements of the hash
#   for which the regexp matches the key name
function fuzzyLookup( &$hash, $lookup ) {
	reset( $hash );
	$return = array();
    foreach($hash as $key => $value) {
#		echo "comparing $key against $lookup <BR />";
		if (preg_match( '/'.$lookup.'/', $key )) $return[$key] = $value;
	}
	return $return;
}

function defaultLookup( &$hash, $lookup, $default ) {
	if (!isset($hash[$lookup])) return $default;
	return $hash[$lookup];
}

function defaultKey( &$hash, $lookup, $default ) {
	if (!isset($hash[$lookup])) {
		$hash[$lookup] = $default;
		return true;
	}
	return false;
}

function keyIsTrue( &$hash, $key ) {
	return ( isset($hash[$key]) && $hash[$key] );
}

# if pos is negative counts that many from the end of the array
function splitArray( &$array, $pos ) {
	if ($pos < 0) $pos = count($array)+$pos;
	if ($pos < 0) $pos = 0;

	$ar1 = array();
	$ar2 = array();
	reset( $array );
    foreach($array as $key => $value) {
		if ( $key < $pos ) {
			$ar1[] = $value;
		} else {
			$ar2[] = $value;
		}
	}

	$array = $ar1;
	return $ar2;
}

function isAjaxRequest() {
	static $answer = null;

	if ($answer !== null) return $answer;

	global $_SERVER;
	if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
		$answer = true;
	} else $answer = false;

	return $answer;
}

function flatten(array $array) {
    $return = array();
    array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
    return $return;
}

function enclose($startTag, $thing) {
	forceArray($thing);
	$endTag = preg_replace('/^</','</',$startTag);
	return $startTag.implode($endTag.$startTag,$thing).$endTag;
}

function definedAndTrue($varName) {
    return defined($varName) && constant($varName);
}

function getConstant($varName, $default='') {
    return defined($varName) ? constant($varName) : $default;
}

function toCamelCase($string, $lcFirst = true) {
    $result = preg_replace_callback('/(_|\\s)(.?)/',function($matches){ return strtoupper($matches[2]); },$string);
    if($lcFirst) {
        $result = lcfirst($result);
    }
    return $result;
}

function toKebabCase($string, $strtolower = true) {
	$string = preg_replace('/([a-z])([A-Z])/', '$1-$2', $string);
	if($strtolower) {
		$string = strtolower($string);
	}
    return $string;
}

function fromCamelCase($string) {
    return trim(ucwords( preg_replace( '/([A-Z])/', ' $1', $string ) ));
}

/**
* Make regular expression for case insensitive match
* i.e: convertToCaseInsensitiveRegExp('Foo - bar.'); Output: [Ff][Oo][Oo] - [Bb][Aa][Rr].
* @param String $s
* @return String
*/
function convertToCaseInsensitiveRegExp($s) {
     return array_reduce(str_split($s), function ($result, $char) {
         return (preg_match("/[A-Za-z]/", $char))
             ? $result . '[' . strtoupper($char) . strtolower($char) . ']'
             : $result . $char;
     });
}


function forceCleanArray($inputArray) {
    forceArray($inputArray);
	$inputArray = array_map('trim', $inputArray);
	// It will remove '' (empty string), null, false, 0 (zero)
    $inputArray = array_filter($inputArray);
	$inputArray = array_unique($inputArray);
    $inputArray = array_values($inputArray);
    return $inputArray;
}

function forceCleanArrayFromWS($inputName, $delimiter = null) {
    $dataSelected = ws($inputName);
	if(!empty($delimiter)) {
		$dataSelected = explode($delimiter, $dataSelected);
	}
	return forceCleanArray($dataSelected);
}

function alert( $message, $severity='info', $markAsFixed=false ) {
    global $USER_ID, $DB, $USER_TYPE;
    $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1);

    return $DB->insert('systemAlert',array(
        'time'      => time(),
        'fixedAt'   => $markAsFixed?time():0,
        'userId'    => $USER_ID,
        'userType'  => $USER_TYPE,
        'file'      => $backtrace[0]['file'],
        'lineNumber'  => $backtrace[0]['line'],
        'message'   => $message,
        'severity'   => $severity
    ));
}

/**
 * Get time in microseconds
 */
function getUSTime() {
    return (int)(hrtime(true) / 1000);
}


function rearrangeSearchTemplateColumns($html, $desiredOrder,$rowData=[]){
    preg_match('/<row[^>]*\btype="([^"]+)"/', $html, $rowType);

    $searchTemplateRowType = (empty($rowType[0]))?"list":$rowType[1];

    preg_match_all('/<column name="([^"]+)">(.*?)<\/column>/s', $html, $matches, PREG_SET_ORDER);


    $columns = [];
    foreach ($matches as $match) {
        $columns[$match[1]] = $match[2];
    }

    $sortedHtml = '';
    $sortedRowHtml = '';
    if(!empty($desiredOrder)) {
        foreach($desiredOrder AS $columnValue => $columnTitle) {
            if(isset($columns[$columnValue])) {
                if($searchTemplateRowType == "list" || empty($columnTitle)) {
                    $sortedHtml .= $columns[$columnValue];
                } else {
                    $sortedHtml .= preg_replace(
                        '/(<span\s+class="columnName"[^>]*>)(.*?)(<\/span>)/',
                        '$1' . $columnTitle . '$3',
                        $columns[$columnValue]
                    );
                }
            } else if(isset($columns["*"])){
				if($searchTemplateRowType == "list") {
                    $value = $rowData[$columnValue]??'';
                    if(substr(strtolower($columnValue), -2) === "at") {
                        if(!empty($value)) {
                            $value = formatDateTime((int)$value);
                        } else {
                            $value = "";
                        }
                    } else {
                        $columnValueFormat = getColumnFormat($columnValue);
                        if(!empty($columnValueFormat)) {
                            $value = str_replace("[[CONTENT_HERE]]",$value,$columnValueFormat);
                            preg_match_all('/\[\[(.*?)\]\]/', $value, $specialValues); 
                            if(!empty($specialValues[1])) {
                                $specialValues = $specialValues[1];
                                foreach($specialValues AS $specialValue) {
                                    $value = str_replace("[[".$specialValue."]]",$rowData[$specialValue]??"",$value);
                                }
                            }
                        }
                    }
                    $sortedHtml .= str_replace("[[CONTENT_HERE]]",$value,$columns["*"]);
                } else {
					$columnFilter = getColumnFilter($columnValue);
					$columnHtml = $columns["*"];
					$columnHtml = str_replace("[[FILTER_HERE]]",$columnFilter,$columnHtml);
                    $columnHtml = str_replace("[[CONTENT_HERE]]",$columnTitle,$columnHtml);
					$sortedHtml .= $columnHtml;
                }
            }
        }
    } else {
        if(isset($columns["*"])) {
            unset($columns["*"]);
        }
        $sortedHtml .= implode(" ",$columns);
    }

    $sortedRowHtml = preg_replace('/<column[^>]*>.*?<\/column>/s', '[[SORTED_HTML_HERE]]', $html);

    $sortedRowHtml = preg_replace('/\[\[SORTED_HTML_HERE\]\]/', $sortedHtml, $sortedRowHtml, 1);
    $sortedRowHtml = str_replace("[[SORTED_HTML_HERE]]","",$sortedRowHtml);
    $sortedRowHtml = preg_replace(['/<row[^>]*>/', '/<\/row>/'], '', $sortedRowHtml);
    return $sortedRowHtml;

}

function flushContent($message) {
    echo str_repeat(' ', 1024) .$message;
    flush();
}