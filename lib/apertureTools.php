<?
define('BITS_PER_ANSWER', 16);
define('ANSWER_MASK', 2**BITS_PER_ANSWER - 1);
// Prevent the 'factor' argument to pal_multiply() overflowing
// '-1' to discount the sign bit as all ints are signed
define('MAX_ANSWER_SHIFTS', (int)(((PHP_INT_SIZE * 8) - 1) / BITS_PER_ANSWER));

/**
 * To Frequency Distribution
 * Convert packed summed answers to array of answer sums
 * @param $summedAnswers GMP
 * @param $numOptions int Stop after breaking off $numOptions options rather than do the whole 2048 bits
 */
function toFD(GMP $summedAnswers, int $numOptions): array {
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
