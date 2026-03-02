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

// MOCK just calls it on this server with no auth
function callApi(string $apiName, string $server, array $apiData) {
    $ch = curl_init($_SERVER['SERVER_NAME']."/api/$apiName.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/plain']);

    $curlRes = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if($curlErrno) {
        throw new ApertureException($curlError, 500);
    }

    $response = json_decode($curlRes, true);
    if($response === null) {
        throw new ApertureException('Bad api response');
    }

    return $response;
}
