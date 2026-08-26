<?
// 16 bits per option
// store as both padded bitfield and an array of encrypted 1/0s use each as most appropriate
// padded for big additions, bool array for intersection calculations
require_once(LIB_DIR.'/apertureClasses.php');
require_once(LIB_DIR.'/apertureTools.php');
require_once(LIB_DIR.'/testKeys.php');
define('TOKEN_EXPIRY', 5); // seconds
define('PRIVATE_KEY', 0);
define('PUBLIC_KEY', 1);


// For now, return 400 errors for anything that was probably the user's fault and 500 for anything that was ours

function getRequestParameters() {
    $reqBody = file_get_contents('php://input');
    $reqData = json_decode(trim($reqBody), true);
    $reqSig = '';
    $reqParams = [];

    if(isset($reqData['request']) && isset($reqData['requestSignature'])) {
        $reqSig = $reqData['requestSignature'];
        $reqData = $reqData['request'];
    }

    // Do this first so $USER_ID gets set
    if(isset($reqData['userToken'])) {
        $reqParams['userId'] = validateUser($reqData['userToken']);
        global $USER_ID;
        $USER_ID = $reqParams['userId'];
    }

    // TODO ...Key stuff needs to be re-done, see the Transcription Request docs
    foreach(($reqData ?? []) as $paramName => $paramData) {
        if(substr($paramName, -3) == 'Key') {
            // Verify against master key and set from the 'key' item
            if(substr($reqParamName, 0, 6) == 'signed') {
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

    // Don't validate for POC
    //$creationTime = (int)$tokenData[1];
    //if(time() - $creationTime > TOKEN_EXPIRY) {
    //    throw new ApertureException('Token expired', 400);
    //}

    $userId = (int)$tokenData[0];
    //$authSecret = getUserAuthSecret($userId);

    //$checkHash = hash_hmac('sha256', "$userId;$creationTime", $authSecret);
    //if(!hash_equals($checkHash, $tokenData[2])) {
    //    throw new ApertureException('Bad token signature', 400);
    //}
    return $userId;
}

/**
 * @param $qIdData string "comma-separated-ids;createTime;HMAC"
 * @return [questionIds]
 */
function validateQuestionIds(string $qIdData): array {
    $qIdData = explode(';', $qIdData);
    if(
        !count($qIdData) === 3 ||
        empty($qIdData[0]) || empty($qIdData[1]) || empty($qIdData[2])
    ) {
        throw new ApertureException('Bad inputs', 400);
    }

    // Don't bother validating while we're just in POC
    //$creationTime = (int)$qIdData[1];
    //if(time() - $creationTime > TOKEN_EXPIRY) {
    //    throw new ApertureException('Token expired', 400);
    //}

    //global $USER_ID;
    //$authSecret = getUserAuthSecret($USER_ID);

    //$checkHash = hash_hmac('sha256', "{$qIdData[0]};$creationTime", $authSecret);
    //if(!hash_equals($checkHash, $qIdData[2])) {
    //    throw new ApertureException('Bad token signature', 400);
    //}

    $questionIds = array_filter(array_map(function ($qId) {
        return (int)$qId;
    }, explode(',', $qIdData[0])));
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
    $authSecret = getUserAuthSecret($USER_ID);

    $checkHash = hash_hmac('sha256', "$checkCriteriaStr;$creationTime", $authSecret);
    if(!hash_equals($checkHash, $hmac)) {
        throw new ApertureException('Bad token signature', 400);
    }

    return $searchCriteria;
}

/**
 * @param $userId int
 * @return string - the user's auth secret
 */
function getUserAuthSecret(int $userId): string {
    global $DB;

    if(!$userId) {
        throw new ApertureException('Missing user id', 400);
    }

    static $userSecretCache = [];
    if(empty($userSecretCache[$userId])) {
        $userSecretCache[$userId] = $DB->getValue('SELECT secret FROM userAuthSecret WHERE userId = ?', $userId);

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
        // TODO - replace these test keys with however we're storing them for real
        $keypairEncryptionKey = sodium_crypto_pwhash(SODIUM_CRYPTO_BOX_SECRETKEYBYTES, passphrase, salt, SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_ALG_DEFAULT);
        $masterKeys[PRIVATE_KEY] = sodium_crypto_secretbox_open(MASTER_SODIUM_KEYS['encPrivateKey'], MASTER_SODIUM_KEYS['nonce'], $keypairEncryptionKey);
        $masterKeys[PUBLIC_KEY] = MASTER_SODIUM_KEYS['publicKey'];
    }

    return $masterKeys[$type];
}

function normaliseAnswers($allAnswerData): array {
    $result = [];
    foreach($allAnswerData as $answerData) {
        $result[] = new QuestionAnswers($answerData['questionId'] ?? 0, $answerData['encAnswers'] ?? []);
    }
    return $result;
}

function getJSEncryptionKeyNumbers() {
    $keyData = pal_get_encryption_key_numbers(MASTER_PAL_ENC_KEY);
    // Extension returns all big values as base36 encoded (due to fast_paillier lib)
    // Front end needs them base64 encoded
    return [
        'n' => base64_encode(gmp_export(gmp_init($keyData['n'], 36))),
        'g' => base64_encode(gmp_export(gmp_init($keyData['g'], 36))),
    ];
}
