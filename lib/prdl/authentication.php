<?

/*

This function uses the PRDL $DB global variable
It takes a hash of options as its input
	secret				=> used to create various md5 signatures
	cookieName	
	cookieTimeout	 	=> the period of inactivity which will cause the user to be automatically logged out
							expressed in seconds
	cookiePersists		=> if set this means that authentication will use a persistant cookie
	loginPage			=> the file to include if the login fails
	
	userValidationFunction => if set and the function exists will be called to see if the user should be let in
							the function will be passed the row data returned by the db from running testSql
	mustLogin			=> if set and true the user will be redirected to the login page if they are not logged in
	testSql				=> SQL run in order to validate the user

This function returns either an empty hash if the user is not logged in or a hash with the following keys
	username
	userId
	freshLogin => indicates whether the user just logged in (true) or was already logged in (false)

The testSql which is passed to this function is used to check the username and password
against the DB. This SQL should contain the stings "<<username>>" and "<<password>>"
which will be replaced with the username and password respectively.
The SQL must return a columns called userId and may optionally return other
columns. Any additional data will be serialized and stored in a signed cookie and thus
will be avialable in returned by the function in the hash  whilst the user is signed in.

The auth system will freeze any WS data that is present when login fails
and then automatically thaw this when a login finally succeeds. The contents of the
workspace will, in these cases, be included in the page in plain text. You should
therefore not put any sensitive internal data in the workspace.
*/

function authHmac( $data, $key ) {
	return hash_hmac( 'sha256' , $data , $key );
}

function setCookieWrapper($name,$value = "",$expires = 0,$path = "", $domain = "", $secure = false, $httponly = false, $sameSite='Strict') {
    global $LOGGER;
    if ((float)phpversion()>=7.3) {
        $options = array(
            'expires'=> $expires,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $sameSite,
        );
        $cRes = setCookie($name,$value,$options);
        if(!$cRes) {
            $LOGGER->log("Failed to set auth cookie '$name' to '$value' with:\n".print_r($options, true));
        }
    } else {
        $cRes = setCookie($name,$value,$expires,$path,$domain,$secure,$httponly);
        if(!$cRes) {
            $LOGGER->log("Failed to set auth cookie '$name' to '$value' with:\nexpires: $expires\npath: $path\ndomain: $domain\nsecure: $secure\nhttponly: $httponly");
        }
    };
}

if (!defined('UNDERDOG_ADDRESS')) define('UNDERDOG_ADDRESS', 'localhost');
if (!defined('UNDERDOG_BASE_PORT')) define('UNDERDOG_BASE_PORT', 0);
if (!defined('UNDERDOG_NUM_PORTS')) define('UNDERDOG_NUM_PORTS', 1);

function underdogCheck($userKey) {
    if(!UNDERDOG_BASE_PORT) return 'OK:0';

    $port = UNDERDOG_BASE_PORT + (ord(substr($userKey, -1)) % UNDERDOG_NUM_PORTS);
    $sock = fsockopen(UNDERDOG_ADDRESS, $port);
    if(!$sock) return 'ERROR:couldn\'t open socket to underdog server';

    fwrite($sock, $userKey."\n");
    if(!feof($sock)) $response = trim(fgets($sock, 255));
    else $response = 'ERROR:connection closed by underdog server';

    fclose($sock);

    if(!$response) $response = 'ERROR:bad response from underdog server';
    $response = explode(':', $response);
    $response['result'] = $response[0];
    $response['message'] = $response[1];

    return $response;
}

function prdlAuthLogin( $options, $error='' ) {	
	global $DB, $WS, $_REQUEST, $VIEWS_DIR, $SITE_BASE_DIR;

    // Destroy the session if there is one
    if (isset($options['sessionId']) && strlen($options['sessionId'])) {
        $DB->exec('DELETE FROM session WHERE id=?',$options['sessionId']);
    }

	if (strlen($error) && isset($_REQUEST['username']) && isset($_REQUEST['password'])) {
		# log the incorrect password attempt
		if (file_exists($SITE_BASE_DIR.'/log/failedLogin.log')) {
			$fh = fopen($SITE_BASE_DIR.'/log/failedLogin.log','a');
			fputs($fh,time().':'.$_REQUEST['username'].':'.$_REQUEST['password'].':'.$_SERVER['REMOTE_ADDR'].':'.$error."\n");
			fclose($fh);
		}
	}
	
	$loginPage = $options['loginPage'];
	$secret =  $options['secret'];
	
	if (isset($WS['mode']) && $WS['mode']=='login') {
		unset($WS['mode']);
	}
	
	# if we already had some frozen workspace then use that
	# otherwise freeze the workspace (although don't bother if there isn't any workspace data)
	if (!(isset($_REQUEST['frozenWorkspace']) && $_REQUEST['frozenWorkspace']) && count($WS)) {
		$data = rawurlencode( serialize($WS) );
		$sig = authHmac( $data, 'authworkspace:'.$secret );
		$WS['frozenWorkspace'] = $sig.':'.$data;
	}

	$WS['loginError'] = $error;

	# Check if they actually _need_ to login
	if (!(isset($options['mustLogin']) && $options['mustLogin'])) {
		return;
	}

    $showCaptcha = isset($options['showCaptcha']) && $options['showCaptcha'];
	if (isset($_REQUEST['username']) && $_REQUEST['username']!='!!!!') $WS['username'] = $_REQUEST['username'];
	if (file_exists($loginPage)) include($loginPage);
	else if (file_exists($VIEWS_DIR.$loginPage)) include($VIEWS_DIR.$loginPage);
	else echo "Error: Couldn't find login page";
	exit;
}

function prdlAuth( $options ) {
	global $_REQUEST, $_COOKIE, $_SERVER, $WS, $DB, $SITE_BASE_DIR, $USER_ID;
	
	if (!isset($options['cookieName'])) $options['cookieName']='prdl_cookie';	
    $options['cookieName'] = str_replace('.','_',$options['cookieName']);
	$return = array();
	$sessionId = '';

	##################################################
	### Check the username + password if provided ####
	##################################################
    if (isset($_COOKIE['loginAsUsername']) && isset($_COOKIE['loginAsPassword'])) {
        setCookie('loginAsUsername','',time() - 3600,'/','',true,true);
        setCookie('loginAsPassword','',time() - 3600,'/','',true,true);
        $_REQUEST['username'] = $_COOKIE['loginAsUsername'];
        $_REQUEST['password'] = $_COOKIE['loginAsPassword'];
    } 
	if( 
        isset($_REQUEST['mode']) && isset($_REQUEST['username']) && isset($_REQUEST['password']) &&
        $_REQUEST['mode'] === 'login' && !($_REQUEST['username']=='!!!!' AND $_REQUEST['password']=='!!!!')
        ) {
			$testUsername = trim($_REQUEST['username']);
			$password = trim($_REQUEST['password']);
			$numAttempts = null;

            if (!defined('LOGIN_LOCK_TIMEOUT')) define('LOGIN_LOCK_TIMEOUT',1800);
            if (!defined('LOGIN_ATTEMPTS_BEFORE_CAPTCHA')) define('LOGIN_ATTEMPTS_BEFORE_CAPTCHA',3);
            if (!defined('LOGIN_RECAPTCHA_SITE_KEY')) define('LOGIN_RECAPTCHA_SITE_KEY','');
            if (!defined('LOGIN_RECAPTCHA_SECRET_KEY')) define('LOGIN_RECAPTCHA_SECRET_KEY','');

            if (UNDERDOG_BASE_PORT) {
                $numAttempts = 0;
                $userKey = md5($options['userTable'].':'.$testUsername);
                list($udResult, $udMessage) = underdogCheck($userKey);
                if($udResult == 'BLOCK') { // $udMessage is timestamp when next allowed
                    if(LOGIN_RECAPTCHA_SITE_KEY) {
                        $numAttempts = LOGIN_ATTEMPTS_BEFORE_CAPTCHA + 1;
                    } else {
                        prdlAuthLogin($options, "Due to recent login activity on your account you may not log in immediately.");
                    }
                } else if($udResult == 'ERROR') {
                    global $LOGGER;
					$LOGGER->log("Underdog error for $userKey - $udMessage");
                }
            }

            if (LOGIN_RECAPTCHA_SITE_KEY) {

                # Remove any login locks that have timed out
                $DB->exec('DELETE FROM loginLog WHERE lastAttemptedAt<UNIX_TIMESTAMP()-?',LOGIN_LOCK_TIMEOUT);
                if($numAttempts === null) { // only look up / set this here if it's not set elsewhere e.g. by Underdog
                    # See if we have a login lock record for this user
                    $numAttempts = $DB->getValue('SELECT numAttempts FROM loginLog WHERE username=? AND userType=?',$testUsername,$options['userTable']);
                    if (!$numAttempts) {
                        $DB->insert('loginLog',array('lastAttemptedAt'=>time(),'userType'=>$options['userTable'],'username'=>$testUsername));
                        $numAttempts=1;
                    } else {
                        $DB->exec('UPDATE loginLog SET lastAttemptedAt=?, numAttempts=numAttempts+1 WHERE username=? AND userType=?',time(),$testUsername,$options['userTable']);
                        $numAttempts++;
                    }
                }
                if ($numAttempts>LOGIN_ATTEMPTS_BEFORE_CAPTCHA) {
                    # check the captcha is valid
                    $captcha=isset($_POST['g-recaptcha-response'])?$_POST['g-recaptcha-response']:'';
					/* @phpstan-ignore-next-line [PHPStan] checkRecaptcha is a removed function as this file is not longer used */
                    if (strlen($captcha) && checkRecaptcha($captcha)) {
                        # OK let them continue
                    } else {
                        $options['showCaptcha']=true;
                        $options['mustLogin']=true;
                        prdlAuthLogin($options, "Due to recent login activity on your account we need you to prove that you're not a robot.");
                    }
                }
            }

			$loginOk = true;
	
			# Username and password provided... lets see if they are valid
			$testSql = $options['testSql'];
			$testSql = str_replace( '<<username>>', $DB->escape($testUsername), $testSql );
			$DB->returnHash(true);
			$userDetails = $DB->getRow($testSql);
			$return = array('userId'=>0);
			if (!isset($userDetails['userId']) || !$userDetails['userId']) {
				$loginOk = false;
			} else {
				// Google Authenticator App
				if (isset($userDetails['authSecret']) && $userDetails['authSecret']) {
					# skip auth check if they're come from one of the bypass IP's
					if (!(isset($userDetails['authBypassIps']) && strlen($userDetails['authBypassIps']) && strpos(' ,'.$userDetails['authBypassIps'].',',','.$_SERVER['REMOTE_ADDR'].','))) {

						# Do the google Auth Check
						include_once(LIB_DIR.'/Google2FA.class.php');
					
						$code = preg_replace('/[^0-9]/','',substr($password,-6));
						if (strlen($code)<6) {
							$loginOk = false;
							prdlAuthLogin($options, "You must add the 6 digit auth code to the end of your password.");
						} else if (!Google2FA::verify_key($userDetails['authSecret'], $code)) {
							$loginOk = false;
							prdlAuthLogin($options, "The 6 digit authorisation code was not correct - make sure the date and time on your mobile device is correct and try again");
						} else {
							$password = substr($password,0,-6);
						}
					}
				}

                $hashOptions = array();
                if (defined('PASSWORD_HASH_COST')) $hashOptions['cost']=PASSWORD_HASH_COST;

                $salt = (isset($userDetails['salt'])&&$userDetails['salt']) ? $userDetails['salt'] : $userDetails['userId'];
                
				# Now check the password - check for md5 passwords first
                if (strlen($userDetails['password'])==32) {
                    # use the old, insecure approach to check the password - the password will get re-written securely in a moment
    				$loginOk =  md5($salt.$password)==$userDetails['password'];
                } else {  
                    $loginOk = password_verify($password, $userDetails['password']);
                }

                if ($loginOk) {

                    $return['passwordLength'] = strlen($password);

                    # see if we need to re-hash the password
                    if (isset($options['passwordUpdateSql']) && (strlen($userDetails['password'])==32 || password_needs_rehash($userDetails['password'], PASSWORD_DEFAULT, $hashOptions))) {
                        
                        $newHash = password_hash($password, PASSWORD_DEFAULT, $hashOptions);
                        $passwordUpdateSql = $options['passwordUpdateSql'];
                        $passwordUpdateSql = str_replace( '<<password>>', $DB->escape($newHash), $passwordUpdateSql );
                        $passwordUpdateSql = str_replace( '<<userId>>', $DB->escape($userDetails['userId']), $passwordUpdateSql );
                        $DB->exec($passwordUpdateSql);
                    }

                } else if (strpos($password,':')) {
                        # check for login-as token
						list($timestamp,$password)=explode(':',$password);
						$tokenAge = time()-$timestamp;
						if (
							$tokenAge>-30 && $tokenAge<30 &&
							password_verify('login_as:'.$timestamp.':'.SECRET.':'.$userDetails['userId'].':'.$userDetails['password'],$password)
						) {
							$loginOk = true;
                            $return['loginAs'] = true;
                        }
				}

				if ($loginOk) {	
					# Set the username if none came back from the database
					if (!isset($return['username'])) $return['username']=$testUsername;

					# Now build the hash of things that will be stored in the auth data
					if (!isset($options['remember'])) $options['remember']='';
					$toRemember = explode(',',$options['remember'].',userId,username');
					foreach($toRemember as $fieldName) { 
						$fieldName = trim($fieldName);
						if ($fieldName==='') continue;
						if (isset($userDetails[$fieldName])) $return[$fieldName] = $userDetails[$fieldName];
					}
					$return['freshLogin'] = true;

					$tries = 0;
					while (true) {
						$sessionId = bin2hex(openssl_random_pseudo_bytes(16));
						# make sure the session ID donesn't alreayd exist
						$exists = $DB->getValue("SELECT id FROM session WHERE id='$sessionId'");
						if (!strlen($exists)) break;
						if ($tries++>10) prdlAuthLogin($options, "Error generating new session ID");
					}

				}
			}
			
			# Final double check
			if (!$loginOk || !$return['userId']) {
				$return['userId']=0;
				prdlAuthLogin($options, "Either the username or the password supplied was not correct. Please try again.");
			}

	} else if (isset($_COOKIE[$options['cookieName']])) {
		#####################################
		### cookie exists - check it out  ###
		#####################################

        if ((count($_GET) || count($_POST)) && (!isset($options['disableCsrfProtection']) || !$options['disableCsrfProtection'])) {
            // Do some CSRF checking based on Referer
            // Not perfect but should give significant protection
            $isSsl = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off';
            if (!(IS_DEV && defined('ALLOWED_REFERER_DOMAINS') && ALLOWED_REFERER_DOMAINS=='__ANY__')) {
                #$refererShouldBe = strtolower('http'.($isSsl?'s':'').'://'.$_SERVER["SERVER_NAME"].'/');
                if (defined('ALLOWED_REFERER_DOMAINS')) $allowedReferers = explode(',',ALLOWED_REFERER_DOMAINS);
                else $allowedReferers = array();

                $thisServer =  strtolower($_SERVER["SERVER_NAME"]);
                if($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) $thisServer .= ':'.$_SERVER['SERVER_PORT'];
                $allowedReferers[] = $thisServer;

                $referer = preg_replace('/^https?:\\/\\//','',strtolower(isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:''));
                $referer = substr($referer,0,strpos($referer,'/'));
                #if (!isset($_SERVER['HTTP_REFERER']) || strpos( strtolower($_SERVER['HTTP_REFERER']), $refererShouldBe) !== 0) {
                if (!isset($_SERVER['HTTP_REFERER']) || !in_array( $referer, $allowedReferers) ) {
                    prdlAuthLogin($options, "Please re-enter your login details to confirm this action");
                }
            }
        }

		# Check the signature on the cookie data
		if (!strpos($_COOKIE[$options['cookieName']],':')) {
			prdlAuthLogin($options, "Invalid authentication cookie");
		} else {
		
			list($sig,$sessionId) = explode(':', $_COOKIE[$options['cookieName']]);

			$sigCheck = md5( $sessionId.':authcookie:'.$options['secret'] );
			if ( $sigCheck !== $sig ) {
				unset($_COOKIE[$options['cookieName']]);
				prdlAuthLogin($options, 'Invalid authentication data signature');
			} else {
                $options['sessionId'] = $sessionId;

                # see if they are logging out...
                if (
                    isset($_REQUEST['username']) && isset($_REQUEST['password']) &&
                    $_REQUEST['username']=='!!!!' && $_REQUEST['password']=='!!!!'
                    ) {
				    unset($_COOKIE[$options['cookieName']]);
                    $DB->exec('DELETE FROM session WHERE id=?',$sessionId);

                    // username=!!!! etc. breaks the viewer tfa sms resend so redirect to clear
                    $redirectVars = $_REQUEST;
                    unset($redirectVars['username']);
                    unset($redirectVars['password']);
                    unset($redirectVars['mode']);
                    header('Location: '.$_SERVER['PHP_SELF'].(count($redirectVars) ? '?'.http_build_query($redirectVars) : ''));
                    exit;
                }

				list( $authTimeout, $authData ) = $DB->getRow('SELECT timeout, data FROM session WHERE id=?',$sessionId);
				if (!strlen($authData)) prdlAuthLogin($options);
				$return = @unserialize($authData);
					
				if (!is_array($return) || !isset($return['username'])) {
					$return = array();
					$sessionId = '';
					prdlAuthLogin($options, 'No username in authentication data');
				} else {

					# See if the cookie is still valid
					if ($authTimeout<time() ) {
						$return = array();
						$sessionId = '';
						prdlAuthLogin($options, 'Your previous login has timed out. Please login again.');
					} else {
						# OK... they are in!!
						$return['freshLogin'] = false;
					}
				}
			}
		}
	}
	# if they are not logged in then return false;	(
	if ( !isset($return['userId']) || !$return['userId'] ) {
		setcookie($options['cookieName'],"",0,"/");	// remove whatever cookie they might have had	

		if (isset($options['mustLogin']) && $options['mustLogin']) {
			prdlAuthLogin(
				$options,
				(isset($_REQUEST['username']) && $_REQUEST['username']=='!!!!' && isset($_REQUEST['password']) && $_REQUEST['password']=='!!!!')?'':'You must be logged in to access this page'
			);
			exit;
		} else {
            return array('freshLogin'=>false);
        }
	}

	# run the extra userValidationFunction if one was provided
	if (isset($options['userValidationFunction'])) {
		if (function_exists($options['userValidationFunction'])) {
			$userValidated = $options['userValidationFunction']($return);
			if ($userValidated!==true) {
				if (isset($options['mustLogin']) && $options['mustLogin']) {
					prdlAuthLogin($options, strlen($userValidated)?$userValidated:'You must be logged in order to access this page');
					exit;
				} else return array('freshLogin'=>false);
			}
		}
		else {
			prdlAuthLogin($options, 'Couldn\'t find user validation function');
			exit;
		}
	}
	
	#######################################################
	### Regenerate the cookie if the user is logged in ####
	#######################################################
	# They should have a sessionId buy now - flag an error if not
	if (!strlen($sessionId)) prdlAuthLogin($options, 'Session ID Missing');

	$DB->exec('REPLACE INTO session (id,timeout,data) VALUES (?,?,?)',$sessionId,time()+$options['cookieTimeout'],serialize($return));
	
	$sig = md5($sessionId.':authcookie:'.$options['secret']);

	# actually set the cookie
	if (isset($options['cookiePersists']) && $options['cookiePersists']) {
		# give the user 10 minutes grace just in case their clock is out -
		# this makes it less likely that a browser on a machine whose clock is fast will delete an
		# otherwise still valid cookie
		# For those whose clock is on time it doesn't matter becasue the embedded expiry time in the
		# cookie will kick in then

		setCookieWrapper($options['cookieName'],"$sig:$sessionId",$return['authTimeout']+10*60,"/",'', $_SERVER['HTTPS']==='on', true);
	} else {
		setCookieWrapper($options['cookieName'],"$sig:$sessionId",0,"/",'', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'),true);
	}

	####################################
	### Unpack any frozen workspace ####
	####################################

	if (isset($_REQUEST['frozenWorkspace']) && strlen($_REQUEST['frozenWorkspace'])) {
		list($sig,$data) = explode(':', $_REQUEST['frozenWorkspace']);

		# check the sig
		$sigCheck = authHmac( $data, 'authworkspace:'.$options['secret'] );
		
		if (!isset($data) || $sigCheck !== $sig) {
			unset($_REQUEST['frozenWorkspace']);
			prdlAuthLogin($options,"The workspace data was corrupted");
			$data = serialize(array());
		}

		$data = unserialize(rawurldecode($data));
		$WS=array_merge($WS, $data);
	}


	unset($return['authTimeout']);
	$USER_ID = $return['userId'];

	// Tell apache who is logged - this is most often used to allow the userId to be included in web logs
	if (function_exists('apache_setenv')) apache_setenv('USERID', $USER_ID, false);

	// Every now and again clear out old sessions
	if (mt_rand(0,1000)<1) $DB->exec('DELETE FROM session WHERE timeout<UNIX_TIMESTAMP()-3600');

	return( $return );
}
