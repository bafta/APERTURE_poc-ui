<?

/**
 * Return the result of the passed function or handle any caught ApertureException
 * @param $func callable
 * @result any The result of $func()
 */
function doApi(callable $func) {
    require_once(LIB_DIR.'/apertureApiTools.php');

    try {
        $result = $func();
    } catch (ApertureException $aex) {
        $responseCode = $aex->getCode();
        http_response_code($responseCode);
        if(400 <= $responseCode && $responseCode <= 499) {
            echo $aex->getMessage();
        } else {
            echo 'Server error';
        }
        exit;
    }

    return $result;
}
