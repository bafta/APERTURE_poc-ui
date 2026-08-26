<?
// DATA SERVER
$EXPECTED_API_REQUEST_PARAMETERS = [
    'validatedQuestionIds', 'answers'
];
// The body is a stream of newline-delimited JSON progress frames. Set before
// requiring apertureApiTools so it also applies to any parse-error response.
header('Content-Type: application/x-ndjson');
require_once('../../lib/prdl/startup.php');
require_once(LIB_DIR.'/apertureApiTools.php');

// Disable output buffering and enable implicit flush for streaming
while(ob_get_level()) ob_end_flush();
ob_implicit_flush(true);
$pad = str_repeat(' ', 4096);

try {
    $answers = $API_REQUEST_PARAMETERS['answers'];
    $total = count($answers);
    $done = 0;

    foreach($answers as $questionAnswers) {
        if(in_array($questionAnswers->getQuestionId(), $API_REQUEST_PARAMETERS['validatedQuestionIds'])) {
            $questionAnswers->save($USER_ID);
        }
        $done++;
        echo json_encode(['progress' => $done, 'total' => $total]) . $pad . "\n";
        flush();
    }

    echo json_encode(['status' => 'OK']) . $pad . "\n";
    flush();
} catch (ApertureException $aex) {
    $responseCode = $aex->getCode();
    http_response_code($responseCode);
    $message = (400 <= $responseCode && $responseCode <= 499) ? $aex->getMessage() : 'Server error';
    echo json_encode(['error' => $message]) . $pad . "\n";
    flush();
}
