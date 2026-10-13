<?
require_once('../lib/prdl/startup.php');

global $DB;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aperture – Results</title>
    <link rel="stylesheet" href="./user/javascript/main.css">
    <style>
        .result-bar-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }
        .result-bar-label {
            flex: 0 0 40%;
            font-size: var(--f-xs);
            font-weight: 500;
            color: var(--c-grey-dark-2);
            text-align: right;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .result-bar-track {
            flex: 1;
            height: 1.4rem;
            background-color: var(--c-form-bg);
            border-radius: 4px;
            overflow: hidden;
        }
        .result-bar-fill {
            height: 100%;
            background-color: var(--c-blue);
            border-radius: 4px;
            min-width: 0;
            transition: width 0.4s ease;
        }
        .result-bar-count {
            flex: 0 0 2.5rem;
            font-size: var(--f-xs);
            font-weight: 600;
            color: var(--c-text);
        }
        .spinner {
            display: inline-block;
            width: 1.2rem;
            height: 1.2rem;
            border: 2px solid var(--c-border);
            border-top-color: var(--c-blue);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            vertical-align: middle;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .result-status {
            font-size: var(--f-xs);
            color: var(--c-grey-dark-2);
            padding: 0.5rem 0;
        }
        .result-error {
            color: var(--c-red);
            font-size: var(--f-xs);
            padding: 0.5rem 0;
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .card-header .question-title {
            margin-bottom: 0;
        }
        .card-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
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

    <div class="page-body">
        <h1 class="page-title">Aggregated Results</h1>
        <p class="page-subtitle">Click a question to decrypt and view its frequency distribution.</p>

        <? foreach($questionData as $qd): ?>
        <div class="card" id="q-<?= (int)$qd['id'] ?>">
            <div class="card-header">
                <div class="question-title"><?= htmlspecialchars($qd['question']) ?></div>
                <div class="card-actions">
                    <button class="btn btn--secondary btn-load" data-question-id="<?= (int)$qd['id'] ?>">Load results</button>
                    <a class="btn btn--secondary" href="/test-detail.php?questionId=<?= (int)$qd['id'] ?>">Bitfield detail</a>
                </div>
            </div>
            <div class="result-area"></div>
        </div>
        <? endforeach; ?>
    </div>

    <script>
        document.querySelectorAll('.btn-load').forEach(btn => {
            btn.addEventListener('click', async function () {
                const questionId = this.dataset.questionId;
                const card = document.getElementById('q-' + questionId);
                const area = card.querySelector('.result-area');

                this.disabled = true;
                this.textContent = 'Loading\u2026';
                area.innerHTML = '<div class="result-status"><span class="spinner"></span> Decrypting\u2026</div>';

                try {
                    const res = await fetch('/api/testResults.php?questionId=' + encodeURIComponent(questionId));
                    const data = await res.json();

                    if (!res.ok) {
                        area.innerHTML = '<div class="result-error">' + escapeHtml(data.error || 'Request failed') + '</div>';
                        this.textContent = 'Retry';
                        this.disabled = false;
                        return;
                    }

                    if (data.results === null) {
                        area.innerHTML = '<div class="result-status">No responses yet.</div>';
                        this.style.display = 'none';
                        return;
                    }

                    const entries = Object.entries(data.results);
                    const max = Math.max(...entries.map(e => e[1]), 1);

                    let html = '<div style="margin-top: 1rem;">';
                    for (const [option, count] of entries) {
                        const pct = (count / max) * 100;
                        html += '<div class="result-bar-row">'
                            + '<div class="result-bar-label" title="' + escapeAttr(option) + '">' + escapeHtml(option) + '</div>'
                            + '<div class="result-bar-track"><div class="result-bar-fill" style="width:' + pct + '%"></div></div>'
                            + '<div class="result-bar-count">' + count + '</div>'
                            + '</div>';
                    }
                    html += '</div>';
                    area.innerHTML = html;
                    this.style.display = 'none';

                } catch (err) {
                    area.innerHTML = '<div class="result-error">Network error: ' + escapeHtml(err.message) + '</div>';
                    this.textContent = 'Retry';
                    this.disabled = false;
                }
            });
        });

        function escapeHtml(str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }
        function escapeAttr(str) {
            return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
    </script>
</body>
</html>
