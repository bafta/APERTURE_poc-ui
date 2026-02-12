<?
// DATA SERVER
$EXPECTED_API_REQUEST_PARAMETERS = [
    'requesterPaillierEncryptionKey', 'searchCriteria', 'questionId'
];
require_once('../../lib/prdl/startup.php');
require_once(LIB_DIR.'/apertureDoApi.php');

$encFreqDist = doApi(function () {
    global $API_REQUEST_PARAMETERS;
    $userIds = serverApi('lookupCohort', ['searchCriteria' => $API_REQUEST_PARAMETERS['searchCriteria']]);
    $encryptedSum = getEncryptedSum($API_REQUEST_PARAMETERS['questionId'], $userIds);
    $encFreqDist = serverapi('requestTranscription', [
        'requesterPaillierEncryptionKey' => $API_REQUEST_PARAMETERS['requesterPaillierEncryptionKey'],
        'encryptedSum' => $encryptedSum,
    ]);
    return $encFreqDist;
});

echo json_encode($encFreqDist);
