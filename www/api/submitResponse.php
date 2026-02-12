<?
// DATA SERVER
$EXPECTED_API_REQUEST_PARAMETERS = [
    'validatedQuestionIds', 'answers'
];
require_once('../../lib/prdl/startup.php');
require_once(LIB_DIR.'/apertureDoApi.php');

doApi(function () {
    global $USER_ID, $API_REQUEST_PARAMETERS;

    $answers = $API_REQUEST_PARAMETERS['answers'];
    foreach($answers as $answer) {
        if(in_array($answer->getQuestionId(), $API_REQUEST_PARAMETERS['validatedQuestionIds'])) {
            saveAnswer($USER_ID, $questionId, $answer);
        }
    }
});

echo 'OK';
