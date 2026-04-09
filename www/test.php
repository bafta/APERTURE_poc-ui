<?
require_once('../lib/prdl/startup.php');
require_once(LIB_DIR.'/apertureApiTools.php');

//$res = pal_encrypt("!Obviously not a key!", 100);
//echo "Done";
//exit;

$questionData = $DB->getHash('SELECT id, question FROM question ORDER BY id');
foreach($questionData as $questionId => $question) {
    $options = $DB->getHash('SELECT num, option FROM option WHERE questionId = ?', $questionId);
    $encPackedAns = $DB->getColumn('SELECT answer FROM answer WHERE questionId = ? AND optionNum = 0', $questionId);
    $encPackedAns = pal_add_array(MASTER_PAL_ENC_KEY, $encPackedAns);
    $packedAns = pal_decrypt(MASTER_PAL_DEC_KEY, $encPackedAns);
    //$packedAns = gmp_init($packedAns, 36);
    $freqDist = toFD($packedAns, count($options));
    $optFreqDist = [];
    foreach($options as $optionNum => $option) {
        $optFreqDist[$option] = $freqDist[$optionNum];
    }
    echo "<h1>Freq Dist: $question</h1><pre>";
    print_r($optFreqDist);
    echo '</pre>';
    $encFreqDist = array_map(function ($num) { return pal_encrypt(USER_PAL_ENC_KEY, $num); }, $optFreqDist);
    echo '<h2>Encrypted for the user:</h2><pre>';
    print_r($encFreqDist);
    echo '</pre>';
}
?>
