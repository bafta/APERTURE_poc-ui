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
    try {
        $wasB36Answer = gmp_init($numStr, 36);
    } catch (ValueError) {
        $wasB36Answer = false;
    }
    // Must compare against false, not truthiness: a GMP zero is falsy, and
    // gmp_export(0) is an empty string, so zero needs an explicit "\0" byte
    // or gmp_import() would re-import the character '0' as its ASCII value 48
    if($wasB36Answer !== false) { // from one of the pal_* functions
        $binsryStr = gmp_export($wasB36Answer);
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
    $summedAnswers = gmp_import(strToBin($summedAnswers));
    $answers = [0]; // Put 0 in position 0 so the broken out answers correspond with the option numbers, which start at 1
    for($i = 0; $i < $numOptions; $i++) {
        $answers[] = gmp_intval($summedAnswers & ANSWER_MASK);
        $summedAnswers = $summedAnswers >> BITS_PER_ANSWER;
    }

    return $answers;
}

