<?
//require_once('../lib/prdl/startup.php');
$SITE_BASE_DIR = dirname(dirname(__FILE__)).'/';
define('LIB_DIR', $SITE_BASE_DIR.'lib');
$NO_REQUEST_PARAMETERS = true;
require_once(LIB_DIR.'/apertureApiTools.php');

//$res = pal_encrypt("!Obviously not a key!", 100);
//echo "Done";
//exit;

//$questionData = $DB->getHash('SELECT id, question FROM question ORDER BY id');
//foreach($questionData as $questionId => $question) {
//    $options = $DB->getHash('SELECT num, option FROM option WHERE questionId = ?', $questionId);
//    $encPackedAns = $DB->getColumn('SELECT answer FROM answer WHERE questionId = ? AND optionNum = 0', $questionId);
//    $encPackedAns = pal_add_array(MASTER_PAL_ENC_KEY, $encPackedAns);
//    $packedAns = pal_decrypt(MASTER_PAL_DEC_KEY, $encPackedAns);
//    //$packedAns = gmp_init($packedAns, 36);
//    $freqDist = toFD($packedAns, count($options));
//    $optFreqDist = [];
//    foreach($options as $optionNum => $option) {
//        $optFreqDist[$option] = $freqDist[$optionNum];
//    }
//    echo "<h1>Freq Dist: $question</h1><pre>";
//    print_r($optFreqDist);
//    echo '</pre>';
//    $encFreqDist = array_map(function ($num) { return pal_encrypt(USER_PAL_ENC_KEY, $num); }, $optFreqDist);
//    echo '<h2>Encrypted for the user:</h2><pre>';
//    print_r($encFreqDist);
//    echo '</pre>';
//}

echo "Doing...\n\n";

//set_time_limit(60 * 60);

//////////
// Key gen
//////////
$iterations = 100;
$keyStart = time();
for($i = 0; $i < $iterations; $i++) {
    $unused = pal_generate_keys();
}
$keyEnd = time();
echo "Keygen x $iterations in ".($keyEnd - $keyStart)." seconds\n";

///////////////////////////////
// Encrypt / Multiply / Decrypt
///////////////////////////////
$items = 1000;
$encData = [];
$encStart = time();
for($i = 0; $i < $items; $i++) {
    $encData[] = pal_encrypt(MASTER_PAL_ENC_KEY, rand());
}
$encEnd = time();
echo "Encrypt x $items in ".($encEnd - $encStart)." seconds\n";

$pwr = 1;
$multStart = time();
foreach($encData as $encDatum) {
    $unused = pal_multiply(MASTER_PAL_ENC_KEY, $encDatum, 2**($pwr++ % 5));
}
$multEnd = time();
echo "Multiply x $items in ".($multEnd - $multStart)." seconds\n";

$decStart = time();
foreach($encData as $encDatum) {
    $unused = pal_decrypt(MASTER_PAL_DEC_KEY, $encDatum);
}
$decEnd = time();
echo "Decrypt x $items in ".($decEnd - $decStart)." seconds\n";

////////////////////////////////
// Encrypt / Add / Decrypt array
////////////////////////////////
$items = 1000;
$testAry = [];
for($i = 0; $i < $items; $i++) {
    $testAry[] = rand();
}
$encStart = time();
$encAry = pal_encrypt_array(MASTER_PAL_ENC_KEY, $testAry);
$encEnd = time();
echo "Enc array ($items items) in ".($encEnd - $encStart)." seconds\n";

$addStart = time();
$unused = pal_add_array(MASTER_PAL_ENC_KEY, $encAry);
$addEnd = time();
echo "Add array ($items items) in ".($addEnd - $addStart)." seconds\n";

$decStart = time();
$unused = pal_decrypt_array(MASTER_PAL_DEC_KEY, $encAry);
$decEnd = time();
echo "Dec array ($items items) in ".($decEnd - $decStart)." seconds\n";
