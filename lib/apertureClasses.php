<?
require_once(LIB_DIR.'/apertureTools.php');

class ApertureException extends Exception {};

class EncryptedAnswer {
    private int $optionNum;
    private string $rawEncAnswer; // Raw binary string

    function __construct(?int $optionNum, ?string $encAnswer) {
        if($optionNum === null || empty($encAnswer)) {
            throw new ApertureException('Bad answer data', 400);
        }

        $this->optionNum = $optionNum;
        $this->rawEncAnswer = strToBin($encAnswer);
    }

    public function getOptionNum(): int {
        return $this->optionNum;
    }

    /**
     * @return The raw answer encoded as a base36 string, suitable to pass to the pal_* extension functions
     */
    public function getAnswer(): string {
        return gmp_strval(gmp_import($this->rawEncAnswer), 36);
    }

    /**
     * @return The answer as a binary string
     */
    public function getRawEncAnswer(): string {
        return $this->rawEncAnswer;
    }

    /**
     * @return The answer as a base64 encoded string
     */
    public function getBase64Answer(): string {
        return base64_encode($this->rawEncAnswer);
    }
}

class EncryptedAnswerArray extends ArrayObject {
    public function offsetSet(mixed $key, mixed $val): void {
        if($val instanceof EncryptedAnswer) {
            parent::offsetSet($key, $val);
            return;
        }
        throw new \InvalidArgumentException('Value must be an EncryptedAnswer');
    }

    static function fromArray($answerData): EncryptedAnswerArray {
        $ansArray = [];
        foreach($answerData as $optAns) {
            $ansArray[] = new EncryptedAnswer($optAns['optionNum'] ?? null, $optAns['encAnswer'] ?? null);
        }
        usort($ansArray, function (EncryptedAnswer $a, EncryptedAnswer $b) { return $a->getOptionNum() <=> $b->getOptionNum(); });
        return new EncryptedAnswerArray($ansArray);
    }
}

class QuestionAnswers {
    private int $questionId;
    private EncryptedAnswerArray $encAnswers;

    function __construct($questionId, $answerData) {
        $this->questionId = $questionId;
        $this->encAnswers = EncryptedAnswerArray::fromArray($answerData);
    }

    public function getQuestionId(): int {
        return $this->questionId;
    }

    public function getEncryptedAnswers(): EncryptedAnswerArray {
        return $this->encAnswers;
    }

    public function generatePackedAnswer() {
        $gotPackedAnswer = array_reduce($this->encAnswers->getArrayCopy(), function (bool $gotAlready, EncryptedAnswer $encAnswer) {
            return $gotAlready || ($encAnswer->getOptionNum() == 0);
        }, false);

        if($gotPackedAnswer) {
            return;
        }

        $shiftedAnswers = [];
        foreach($this->encAnswers as $encAnswer) {
            $neededAnswerShifts = $encAnswer->getOptionNum() - 1;
            $shiftedAnswer = $encAnswer->getAnswer();
            while($neededAnswerShifts > 0) {
                $answerShiftsThisTime = min($neededAnswerShifts, MAX_ANSWER_SHIFTS);
                $shiftedAnswer = pal_multiply(MASTER_PAL_ENC_KEY, $shiftedAnswer, 2**($answerShiftsThisTime * BITS_PER_ANSWER));
                $neededAnswerShifts -= $answerShiftsThisTime;
            }
            $shiftedAnswers[] = $shiftedAnswer;
        }
        $packedAnswer = pal_add_array(MASTER_PAL_ENC_KEY, $shiftedAnswers);
        $this->encAnswers[] = new EncryptedAnswer(0, $packedAnswer);
    }

    public function save(int $userId) {
        if($userId <= 0) {
            throw new ApertureException('Missing / bad user id in QuestionAnswers::save()');
        }
        $this->generatePackedAnswer();
        global $DB;
        foreach($this->encAnswers as $encAnswer) {
            $DB->exec('
                INSERT INTO answer (userId, questionId, optionNum, answer)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE answer = ?
            ', $userId, $this->questionId, $encAnswer->getOptionNum(), $encAnswer->getAnswer(), $encAnswer->getAnswer());
        }
    }
}
