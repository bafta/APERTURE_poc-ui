<?
// KEY GUARDIAN
$EXPECTED_API_REQUEST_PARAMETERS = [
    'requesterPaillierEncryptionKey', 'encryptedSum'
];
require_once('../../lib/prdl/startup.php');
require_once(LIB_DIR.'/apertureDoApi.php');

$encFreqDist = doApi(function () {
    global $API_REQUEST_PARAMETERS;
    $packedSums = pal_decrypt($masterDecryptionKey, $API_REQUEST_PARAMETERS['encryptedSum'];
    $freqDist = toFD($packedSums);
    return pal_encrypt_array($API_REQUEST_PARAMETERS['requesterPaillierEncryptionKey'], $freqDist);
});

echo json_encode($encFreqDist);
