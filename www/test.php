<?
require_once('../lib/prdl/startup.php');
require_once(LIB_DIR.'/apertureApiTools.php');

$questionData = $DB->getHash('SELECT id, question FROM question ORDER BY id');
foreach($questionData as $questionId => $question) {
    $options = $DB->getHash('SELECT num, option FROM option WHERE questionId = ?', $questionId);
    $encPackedAns = $DB->getColumn('SELECT answer FROM answer WHERE questionId = ? AND optionNum = 0', $questionId);
    $encPackedAns = array_map(function ($b64Ans) { return base64_decode($b64Ans); }, $encPackedAns);
    $encPackedAns = pal_add_array(MASTER_PAL_ENC_KEY, $encPackedAns);
    $packedAnsStr = pal_decrypt(MASTER_PAL_DEC_KEY, $encPackedAns);
    $packedAns = gmp_import($packedAnsStr);
    // Make this take GMP or binary string? Or just string. Maybe shouldn't be needing to mess with GMP outside utility functions?
    $freqDist = toFD($packedAns, count($options));
    $optFreqDist = [];
    foreach($options as $optionNum => $option) {
        $optFreqDist[$option] = $freqDist[$optionNum];
    }
    echo "<h1>Freq Dist: $question</h1><pre>";
    print_r($optFreqDist);
    echo '</pre>';
}
?>
