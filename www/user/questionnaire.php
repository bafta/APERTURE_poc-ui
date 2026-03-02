<?
require_once('../../lib/prdl/startup.php');
require_once(LIB_DIR.'/apertureApiTools.php');
?>
<html>
    <head>
        <title>Questionnaire</title>
        <script defer src="./javascript/bundle.js"></script>
    </head>
    <body>
        <script>
            const publicKeyData = <?= json_encode(getJSEncryptionKeyNumbers()) ?>;
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
            echo "\nconst questionData = ".json_encode($questionData).";\n";
            ?>
        </script>
        <div id="questionnaire"></div>
    </body>
</html>
