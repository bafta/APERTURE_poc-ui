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
    foreach($answers as $questionAnswers) {
        if(in_array($questionAnswers->getQuestionId(), $API_REQUEST_PARAMETERS['validatedQuestionIds'])) {
            $questionAnswers->save($USER_ID);
        }
    }
});

echo json_encode([
    'status' => 'OK',
]);
