<?
define('BITS_PER_ANSWER', 16);
define('ANSWER_MASK', 2**BITS_PER_ANSWER - 1);
// Prevent the 'factor' argument to pal_multiply() overflowing
// '-1' to discount the sign bit as all ints are signed
define('MAX_ANSWER_SHIFTS', (int)(((PHP_INT_SIZE * 8) - 1) / BITS_PER_ANSWER));
// The packed plaintext is a 2048-bit field of 16-bit slots, so a question can
// hold at most 128 options. This is also the DoS bound on client-supplied
// optionNum / answer counts (each option costs homomorphic shifts to pack).
define('MAX_OPTIONS', 2048 / BITS_PER_ANSWER);
// Safety cap on the number of questions accepted in one submission (a guard
// against request-amplification DoS, not a functional limit on survey size).
define('MAX_QUESTIONS', 128);

/**
 * Convert a string encoded large number to a binary string
 * @param $numStr string base36, base64 or binary string
 * @return binary string
 */
function strToBin($numStr) {
    // The pal_* extension and gmp_strval(...,36) only ever emit lowercase base36
    // ([0-9a-z], with an optional leading '-'). Restricting the base36 branch to
    // that charset stops a standard-base64 ciphertext from the front end (which
    // uses upper-case letters, '+', '/' and '=') from being misread as base36 and
    // silently corrupted. (A base64 value made up purely of [0-9a-z] would still
    // collide, but that is astronomically unlikely for a real ciphertext.)
    if(is_string($numStr) && preg_match('/^-?[0-9a-z]+$/', $numStr)) { // from one of the pal_* functions
        // Compare via the charset test above, not truthiness: a GMP zero is
        // falsy, and gmp_export(0) is an empty string, so zero needs an explicit
        // "\0" byte or gmp_import() would re-import '0' as its ASCII value 48.
        $binsryStr = gmp_export(gmp_init($numStr, 36));
        if($binsryStr === '') {
            $binsryStr = "\0";
        }
    } else {
        $wasB64Answer = base64_decode($numStr, true);
        if($wasB64Answer) { // from the front end
            $binsryStr = $wasB64Answer;
        } else {
            $binsryStr = $numStr;
        }
    }

    return $binsryStr;
}

/**
 * To Frequency Distribution
 * Convert packed summed answers to array of answer sums
 * @param $summedAnswers string base36, bas64 or binary string
 * @param $numOptions int Stop after breaking off $numOptions options rather than do the whole 2048 bits
 */
function toFD(string $summedAnswers, int $numOptions): array {
    // The extension decrypts into a signed range and returns a leading '-' for
    // negative plaintexts. A packed aggregate is an unsigned bitfield, so a
    // negative value means the sum exceeded n/2 and wrapped — corrupt or
    // adversarial data. strToBin's gmp_export silently drops the sign, which
    // would then unpack as garbage, so detect it here and fail closed.
    if(str_starts_with(ltrim($summedAnswers), '-')) {
        throw new RuntimeException('Packed aggregate out of range');
    }
    $summedAnswers = gmp_import(strToBin($summedAnswers));
    $answers = [0]; // Put 0 in position 0 so the broken out answers correspond with the option numbers, which start at 1
    for($i = 0; $i < $numOptions; $i++) {
        $answers[] = gmp_intval($summedAnswers & ANSWER_MASK);
        $summedAnswers = $summedAnswers >> BITS_PER_ANSWER;
    }

    return $answers;
}

