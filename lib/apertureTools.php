<?
define('BITS_PER_ANSWER', 16);
define('ANSWER_MASK', 2**BITS_PER_ANSWER - 1);
// Prevent the 'factor' argument to pal_multiply() overflowing
// '-1' to discount the sign bit as all ints are signed
define('MAX_ANSWER_SHIFTS', (int)(((PHP_INT_SIZE * 8) - 1) / BITS_PER_ANSWER));

/**
 * Convert a string encoded large number to a binary string
 * @param $numStr string base36, base64 or binary string
 * @return binary string
 */
function strToBin($numStr) {
    $wasB36Answer = gmp_init($numStr, 36);
    if($wasB36Answer) { // from one of the pal_* functions
        $binsryStr = gmp_export($wasB36Answer);
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

// Not needed? Think this will be handled by the answer aggregating function?
function fromFD($answers) {
    $answers = array_reverse($answers);
    $packedAnswers = 0;
    foreach($answers as $answer) {
        $packedAnswers << BITS_PER_ANSWER;
        $packedAnswers += $answer;
    }
    return $packedAnswers;
}

// TODO how are we storing the master paillier encryption key?
function getEncryptedSum($userIds, $questionId) {
    global $DB;
    $encUserAnswers = $DB->getColumn('
        SELECT answer
        FROM answer
        WHERE questionId = ? AND optionNum = 0 AND userId IN (?)
    ', $questionId, $userIds);
    $encSum = pal_add_array(MASTER_PAL_ENC_KEY, $encUserAnswers);

    return $encSum;
}
