<?
// 16 bits per option
// store as both padded bitfield and an array of encrypted 1/0s use each as most appropriate
// padded for big additions, bool array for intersection calculations
require_once(LIB_DIR.'/apertureClasses.php');
require_once(LIB_DIR.'/apertureTools.php');
require_once(LIB_DIR.'/testKeys.php');
define('TOKEN_EXPIRY', 5); // seconds


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

    foreach(($reqData ?? []) as $paramName => $paramData) {
        if($paramName == 'questionIds') {
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
if(!($NO_REQUEST_PARAMETERS ?? false)) {
    $API_REQUEST_PARAMETERS = getRequestParameters();
}

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
