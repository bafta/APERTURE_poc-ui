<?
require_once('../../lib/prdl/startup.php');
require_once(LIB_DIR.'/apertureApiTools.php');
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Aperture – Questionnaire</title>
        <link rel="stylesheet" href="./javascript/main.css">
        <script defer src="./javascript/bundle.js"></script>
    </head>
    <body>
        <script>
            const publicKeyData = <?= json_encode(getJSEncryptionKeyNumbers(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            <?
            $DB->returnHash();
            $questionData = $DB->getRows('SELECT id, question, type FROM question ORDER BY id');
            foreach($questionData as $idx => $qd) {
                $DB->returnHash();
                $questionData[$idx]['options'] = $DB->getRows('
                    SELECT num, option
                    FROM option
                    WHERE questionId = ?
                    ORDER BY num
                ', $qd['id']);
            }
            echo "\nconst questionData = ".json_encode($questionData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).";\n";
            ?>
        </script>

        <header class="site-header">
            <a class="site-header__logo" href="/">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                <span>Aperture</span>
            </a>
        </header>

        <div id="questionnaire"></div>
    </body>
</html>
