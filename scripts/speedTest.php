<?php
/**
 * Paillier extension speed test.
 *
 * Run:  php scripts/speedTest.php
 *
 * Times key generation, encrypt/multiply/decrypt and the array variants
 * using the master test keys. Originally lived in www/test.php (commit
 * 0f294e2); moved here so test.php can stay as the results page.
 */

define('LIB_DIR', dirname(__DIR__).'/lib');
$NO_REQUEST_PARAMETERS = true;
require_once(LIB_DIR.'/apertureApiTools.php');

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
