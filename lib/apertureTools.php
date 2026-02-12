<?
/**
 * To Frequency Distribution
 * Convert packed summed answers to array of answer sums
 * @param $summedAnswers Binary string SHOULD THIS JUST BE INT?
 */
function toFD($summedAnswers) {
    $answers = [];
    foreach(mb_str_split($summedAnswers, 1, '8bit') as $char) {
        $answers[] = unpack('Cbyte', $char)['byte'];
    }

    return array_reverse($answers);
}

// Not needed? Think this will be handled by the answer aggregating function?
function fromFD($answers) {
    $packedAnswers = '';
    foreach($answers as $answer) {
        $packedAnswers .= chr($answer);
    }
    return $packedAnswers;
}

// TODO how are we storing the master paillier encryption key?
// How will the answer data be provided. Array of answer values for a question that need to be packed?
function saveAnswer(int $userId, int $questionId, int $answer) {
    global $DB;
    $encAnswer = pal_encrypt(MASTER_PAL_ENC_KEY, $answer);
    $DB->exec('
        INSERT INTO answer (userId, questionId, answer)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE answer = ?
    ', $userID, $questionId, $encAnswer, $encAnswer);
}

// TODO how are we storing the master paillier encryption key?
function getEncryptedSum($userIds, $questionId) {
    global $DB;
    $encUserAnswers = $DB->getColumn('SELECT answer FROM answer WHERE questionId = ? AND userId IN (?)', $questionId, $userIds);
    $encSum = pal_add_array(MASTER_PAL_ENC_KEY, $encUserAnswers);

    return $encSum;
}
