<?
class ApertureException extends Exception {};

class EncryptedAnswer {
    private int $optionNum;
    private string $encAnswer;

    function __construct(?int $optionNum, ?string $encAnswer) {
        if($optionNum === null || empty($encAnswer)) {
            throw new ApertureException('Bad answer data', 400);
        }

        $this->optionNum = $optionNum;
        $decodedAnswer = base64_decode($encAnswer, true);
        // If we got something other than false back then it was a base64 encoded encrypted answer
        $this->encAnswer = $decodedAnswer ?: $encAnswer;
    }

    public function getOptionNum(): int {
        return $this->optionNum;
    }

    public function getAnswer(): string {
        return $this->encAnswer;
    }

    public function getBase64Answer(): string {
        return base64_encode($this->encAnswer);
    }
}

class EncryptedAnswerArray extends ArrayObject {
    public function offsetSet($key, $val) {
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
            ', $userId, $this->questionId, $encAnswer->getOptionNum(), $encAnswer->getBase64Answer(), $encAnswer->getBase64Answer());
        }
    }
}
