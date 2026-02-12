<?
// USER MANAGEMENT - from e.g. requestStatistic

$EXPECTED_API_REQUEST_PARAMETERS = [
    'searchCriteria'
];
require_once('../../lib/prdl/startup.php');
require_once(LIB_DIR.'/apertureDoApi.php');

$cohortUserIds = doApi(function () {
    global $API_REQUEST_PARAMETERS;
    return lookupCohort($API_REQUEST_PARAMETERS['searchCriteria']);
});

echo json_encode($cohortUserIds);
