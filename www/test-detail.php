<?
set_time_limit(120);
require_once('../lib/prdl/startup.php');
require_once(LIB_DIR.'/apertureTools.php');
require_once(LIB_DIR.'/testKeys.php');

global $DB;

$questionId = (int)($_GET['questionId'] ?? 0);
$error = null;
$questionText = null;
$options = [];
$rows = [];
$totals = [];

if(!$questionId) {
    $error = 'Missing or invalid questionId';
} else {
    $questionText = $DB->getValue('SELECT question FROM question WHERE id = ?', $questionId);
    if(!$questionText) {
        $error = 'Question not found';
    } else {
        $DB->returnHash();
        $options = $DB->getRows('SELECT num, option FROM option WHERE questionId = ? ORDER BY num', $questionId);
        $encRows = $DB->getRows('SELECT userId, answer FROM answer WHERE questionId = ? AND optionNum = 0', $questionId);

        if(!empty($encRows)) {
            $numOptions = count($options);
            $totals = array_fill(0, $numOptions, 0);
            $maxOptionNum = max(array_column($options, 'num'));

            foreach($encRows as $encRow) {
                $plaintext = pal_decrypt(MASTER_PAL_DEC_KEY, $encRow['answer']);
                $freqDist = toFD($plaintext, $maxOptionNum);
                $values = [];
                foreach($options as $idx => $opt) {
                    $val = $freqDist[$opt['num']];
                    $values[] = $val;
                    $totals[$idx] += $val;
                }
                $rows[] = ['values' => $values, 'raw' => $encRow['answer']];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aperture – Bitfield Detail</title>
    <link rel="stylesheet" href="./user/javascript/main.css">
    <style>
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: var(--f-xs);
        }
        .detail-table th,
        .detail-table td {
            padding: 0.5rem 0.75rem;
            text-align: center;
            border: 1px solid var(--c-border);
        }
        .detail-table th {
            background-color: var(--c-form-bg);
            font-weight: 600;
            color: var(--c-grey-dark-2);
            font-size: var(--f-2xs);
        }
        .detail-table th.row-header {
            text-align: left;
            width: 5rem;
        }
        .detail-table td.row-label {
            text-align: left;
            font-weight: 600;
            color: var(--c-grey-dark-2);
            white-space: nowrap;
        }
        .detail-table td.cell-one {
            background-color: var(--c-blue-light-2);
            color: var(--c-blue);
            font-weight: 700;
        }
        .detail-table td.cell-zero {
            color: var(--c-grey-mid);
        }
        .detail-table tfoot td {
            font-weight: 700;
            background-color: var(--c-form-bg);
        }
        .binary-row td {
            font-weight: 500;
            font-size: var(--f-2xs);
            letter-spacing: 0.05em;
            color: var(--c-blue);
        }
        .binary-row code {
            font-family: monospace;
        }
        .btn-raw {
            font-size: 0.65rem;
            padding: 0.1em 0.45em;
            border: 1px solid var(--c-border);
            border-radius: 3px;
            background: var(--c-white);
            color: var(--c-grey-dark-2);
            cursor: pointer;
            vertical-align: middle;
            line-height: 1;
        }
        .btn-raw:hover {
            background: var(--c-blue-pale);
            border-color: var(--c-blue);
            color: var(--c-blue);
        }
        .btn-raw.active {
            background: var(--c-blue);
            color: var(--c-white);
            border-color: var(--c-blue);
        }
        .raw-row td {
            text-align: left;
            padding: 0.75rem 1rem;
            background: var(--c-text);
        }
        .raw-blob {
            font-family: monospace;
            font-size: 0.7rem;
            color: var(--c-green);
            word-break: break-all;
            line-height: 1.5;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: var(--f-xs);
            margin-bottom: 1rem;
        }
        .respondent-count {
            font-size: var(--f-xs);
            color: var(--c-grey-dark-2);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="site-header__logo" href="/">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
            <span>Aperture</span>
        </a>
    </header>

    <div class="page-body" style="max-width: none;">
        <a class="back-link" href="/test.php">&larr; Back to results</a>

        <? if($error): ?>
            <div class="card">
                <div class="empty-state"><?= htmlspecialchars($error) ?></div>
            </div>
        <? else: ?>
            <h1 class="page-title">Bitfield Detail</h1>
            <p class="page-subtitle"><?= htmlspecialchars($questionText) ?></p>

            <? if(empty($rows)): ?>
                <div class="card">
                    <div class="empty-state">No responses yet</div>
                </div>
            <? else: ?>
                <div class="respondent-count"><?= count($rows) ?> respondent<?= count($rows) !== 1 ? 's' : '' ?></div>
                <div class="card" style="overflow-x: auto;">
                    <table class="detail-table">
                        <thead>
                            <tr>
                                <th class="row-header">#</th>
                                <? foreach($options as $opt): ?>
                                    <th><?= htmlspecialchars($opt['option']) ?></th>
                                <? endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <? foreach($rows as $rowIdx => $row): ?>
                            <tr>
                                <td class="row-label"><?= $rowIdx + 1 ?> <button class="btn-raw" onclick="toggleRaw(this)" title="Show encrypted ciphertext">raw</button></td>
                                <? foreach($row['values'] as $val): ?>
                                    <td class="<?= $val ? 'cell-one' : 'cell-zero' ?>"><?= $val ?></td>
                                <? endforeach; ?>
                            </tr>
                            <tr class="raw-row" style="display: none;">
                                <td colspan="<?= $numOptions + 1 ?>"><code class="raw-blob"><?= htmlspecialchars($row['raw']) ?></code></td>
                            </tr>
                            <? endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="binary-row">
                                <td class="row-label">Binary</td>
                                <? foreach($totals as $total): ?>
                                    <td><code><?= str_pad(decbin($total), BITS_PER_ANSWER, '0', STR_PAD_LEFT) ?></code></td>
                                <? endforeach; ?>
                            </tr>
                            <tr>
                                <td class="row-label">Total</td>
                                <? foreach($totals as $total): ?>
                                    <td><?= $total ?></td>
                                <? endforeach; ?>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <? endif; ?>
        <? endif; ?>
    </div>
    <script>
        function toggleRaw(btn) {
            const rawRow = btn.closest('tr').nextElementSibling;
            const visible = rawRow.style.display !== 'none';
            rawRow.style.display = visible ? 'none' : '';
            btn.classList.toggle('active', !visible);
        }
    </script>
</body>
</html>
