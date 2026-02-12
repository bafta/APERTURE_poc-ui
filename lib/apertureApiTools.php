<?
require_once(LIB_DIR.'/apertureClasses.php');
require_once(LIB_DIR.'/apertureTools.php');
define('TOKEN_EXPIRY', 5); // seconds
define('PRIVATE_KEY', 0);
define('PUBLIC_KEY', 1);


// For now, return 400 errors for anything that was probably the user's fault and 500 for anything that was ours

function getRequestParameters() {
    $reqBody = file_get_contents('php://input');
    $reqData = json_decode(trim($reqBody), true);
    $reqParams = [];

    // Do this first so $USER_ID gets set
    if(isset($reqData['userToken'])) {
        $reqParams['userId'] = validateUser($reqData['userToken']);
        global $USER_ID;
        $USER_ID = $reqParams['userId'];
    }

    foreach($reqData as $paramName => $paramData) {
        if(substr($paramName, -3) == 'Key') {
            // Verify against master key and set from the 'key' item
            if(substr($reqParamName, 0, 6) == 'signed')) {
                $reqParamName = substr($reqParamName, 6);
            }
            $reqParamName = lcfirst($reqParamName);
            $reqParams[$reqParamName] = validateKey($paramData);
        } else if($paramName == 'questionIds') {
            $reqParams['validatedQuestionIds'] = validateQuestionIds($paramData);
        } else if($paramName == 'searchCriteria') {
            $reqParams['searchCriteria'] = validateSearchCriteria($paramData);
        } else if($paramName == 'answers') {
            $reqParams['answers'] = normaliseAnswers($paramData);
        } else if($paramName == 'userToken') {
            // Already done above
        } else {
            $reqParams[$paramName] = $paramData;
        }
    }

    global $EXPECTED_API_REQUEST_PARAMETERS;
    $missingParams = [];
    if(is_array($EXPECTED_API_REQUEST_PARAMETERS) && !empty($EXPECTED_API_REQUEST_PARAMETERS)) {
        foreach($EXPECTED_API_REQUEST_PARAMETERS as $earp) {
            if(empty($reqParams[$earp])) {
                $missingParams[] = $earp;
            }
        }
    }
    $reqParams['__missing'] = $missingParams;

    return $reqParams;
}

global $API_REQUEST_PARAMETERS;
$API_REQUEST_PARAMETERS = getRequestParameters();

/**
 * @param $userToken string "userId;createTime;HMAC"
 * @return userId
 */
function validateUser(string $userToken): int {
    $tokenData = explode(';', $userToken);
    if(
        !count($tokenData) === 3 ||
        empty($tokenData[0]) || empty($tokenData[1]) || empty($tokenData[2])
    ) {
        throw new ApertureException('Bad inputs', 400);
    }

    $creationTime = (int)$tokenData[1];
    if(time() - $creationTime > TOKEN_EXPIRY) {
        throw new ApertureException('Token expired', 400);
    }

    $userId = (int)$tokenData[0];
    $authSecret = getUserAuthSecret($userId));

    $checkHash = hash_hmac('sha256', "$userId;$creationTime", $authSecret);
    if(!hash_equals($checkHash, $tokenData[2])) {
        throw new ApertureException('Bad token signature', 400);
    }
    return $userId;
}

/**
 * @param $qIdData string "comma-separated-ids;createTime;HMAC"
 * @return [questionIds]
 */
function validateQuestionIds(string $qIdData): array {
    $tokenData = explode(';', $userToken);
    if(
        !count($tokenData) === 3 ||
        empty($tokenData[0]) || empty($tokenData[1]) || empty($tokenData[2])
    ) {
        throw new ApertureException('Bad inputs', 400);
    }

    $creationTime = (int)$tokenData[1];
    if(time() - $creationTime > TOKEN_EXPIRY) {
        throw new ApertureException('Token expired', 400);
    }

    global $USER_ID;
    $authSecret = getUserAuthSecret($USER_ID));

    $checkHash = hash_hmac('sha256', "{$tokenData[0]};$creationTime", $authSecret);
    if(!hash_equals($checkHash, $tokenData[2])) {
        throw new ApertureException('Bad token signature', 400);
    }

    $questionIds = array_filter(array_map(function ($qId) {
        return intval($qId, 10);
    }, explode(',', $tokenData[0])));
    return $questionIds;
}

function validateSearchCriteria($searchCriteria) {
    if(empty($searchCriteria['creationTime']) || empty($searchCriteria['hmac'])) {
        throw new ApertureException('Bad inputs', 400);
    }

    $creationTime = (int)$searchCriteria['creationTime'];
    unset($searchCriteria['creationTime']);
    if(time() - $creationTime > TOKEN_EXPIRY) {
        throw new ApertureException('Token expired', 400);
    }
    $hmac = $searchCriteria['hmac'] ?? '';
    unset($searchCriteria['hmac']);

    ksort($searchCriteria);
    $checkCriteriaStr = json_encode($searchCriteria);

    global $USER_ID;
    $authSecret = getUserAuthSecret($USER_ID));

    $checkHash = hash_hmac('sha256', "$checkCriteriaStr;$creationTime", $authSecret);
    if(!hash_equals($checkHash, $hmac)) {
        throw new ApertureException('Bad token signature', 400);
    }

    return $searchCriteria;
}

/**
 * @return string 32 random bytes, base64 encoded
 */
function generateUserAuthSecret(): string {
    $secretBytes = openssl_random_pseudo_bytes(32); // TODO - do we care about the $strong param?
    return sodium_bin2base64($secretBytes, SODIUM_BASE64_VARIANT_ORIGINAL);
}

/**
 * @param $userId int
 * @return string - the user's auth secret
 */
function getUserAuthSecret(int $userId): string {
    if(!$userId) {
        throw new ApertureException('Missing user id', 400);
    }

    static $userSecretCache = [];
    if(empty($userSecretCache[$userId])) {
        // get from DB?

        if(empty($userSecretCache[$userId])) {
            throw new ApertureException('Could not find auth secret for user', 500);
        }
    }

    return $userSecretCache[$userId];
}

/**
 * @param $keyData [
 *     'key' => base64 encoded key,
 *     'signature' => the base64 key signed by the master public key
 * ]
 * @return the binary (not base64) key|null
 */
function validateKey(array $keyData): string {
    global $DB;
    if(empty($keyData['key']) || empty($keyData['signature'])) {
        throw new ApertureException('Missing key data', 400);
    }

    $rawKey = sodium_base642bin($keyData['key'], SODIUM_BASE64_VARIANT_ORIGINAL);
    if(empty($rawKey)) {
        throw new ApertureException('Bad encoded key', 400);
    }

    $masterPublicKey = getMasterKey(PUBLIC_KEY);
    if(!sodium_crypto_sign_verify_detached($keyData['signature'], $keyData['key'], $masterPublicKey)) {
        throw new ApertureException('Bad signature', 400);
    }

    return $rawKey;
}

/**
 * Return one of the libsodium master keys
 * @param $type PRIVATE_KEY | PUBLIC_KEY
 * @return string the binary private or public master key
 */
function getMasterKey(int $type): string {
    $type = strtoupper($type);
    if(!in_array($type, [PRIVATE_KEY, PUBLIC_KEY], true)) {
        throw new ApertureException('Bad key type', 500);
    }

    static $masterKeys = [];
    if(empty($masterKeys[$type])) {
        $privateKeyFile = DATA_DIR.'/masterKeys/private_key';
        if(!is_readable($privateKeyFile)) {
            throw new ApertureException('Could not open master private key file', 500);
        }

        // TODO - Do we want to store the private key base64 encoded?

        $masterKeys[PRIVATE_KEY] = file_get_contents($privateKeyFile);
        if(empty($masterKeys[PRIVATE_KEY])) {
            throw new ApertureException('No master private key data', 500);
        }

        $masterKeys[PUBLIC_KEY] = sodium_crypto_sign_publickey_from_secretkey($masterKeys[PRIVATE_KEY]);
        if(empty($masterKeys[PUBLIC_KEY])) {
            $masterKeys = [];
            throw new ApertureException('Bad master private key', 500);
        }
    }

    return $masterKeys[$type];
}

function normaliseAnswers($answerData): array {
    return AnswerArray::fromArray($answerData);
}
