<?
set_time_limit(120);
header('Content-Type: application/json');

require_once('../../lib/prdl/startup.php');
require_once(LIB_DIR.'/apertureTools.php');
require_once(LIB_DIR.'/testKeys.php');

$questionId = (int)($_GET['questionId'] ?? 0);
if(!$questionId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid questionId']);
    exit;
}

global $DB;

$question = $DB->getValue('SELECT question FROM question WHERE id = ?', $questionId);
if(!$question) {
    http_response_code(404);
    echo json_encode(['error' => 'Question not found']);
    exit;
}

$options = $DB->getHash('SELECT num, option FROM option WHERE questionId = ? ORDER BY num', $questionId);
$encPackedAns = $DB->getColumn('SELECT answer FROM answer WHERE questionId = ? AND optionNum = 0', $questionId);

if(empty($encPackedAns)) {
    echo json_encode(['questionId' => $questionId, 'results' => null]);
    exit;
}

$encPackedAns = pal_add_array(MASTER_PAL_ENC_KEY, $encPackedAns);
$packedAns = pal_decrypt(MASTER_PAL_DEC_KEY, $encPackedAns);
$freqDist = toFD($packedAns, count($options));

$results = [];
foreach($options as $optionNum => $option) {
    $results[$option] = $freqDist[$optionNum];
}

echo json_encode(['questionId' => $questionId, 'results' => $results]);
