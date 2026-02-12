<?
class ApertureException extends Exception {};

class Answer {
    private int $questionId;
    private int $answer;

    function __construct(int $questionId, int $answer) {
        if(empty($questionId) || empty($answer)) {
            throw new ApertureException('Bad answer data', 400);
        }

        $this->questionId = $questionId;
        $this->answer = $answer;
    }

    public function getQuestionId(): int {
        return $this->questionId;
    }

    public function getAnswer(): int {
        return $this->answer;
    }
}

class AnswerArray extends ArrayObject {
    public function offsetSet($key, $val) {
        if($val instanceof Answer) {
            parent::offsetSet($key, $val);
            return;
        }
        throw new \InvalidArgumentException('Value must be an Answer');
    }

    static function fromArray($answerData): AnswerArray {
        $ansArray = new AnswerArray();
        foreach($answerData as $qa) {
            $ansArray[] = new Answer($qa);
        }
        return $ansArray;
    }
}
