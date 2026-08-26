<script>
    import Question from './components/Question.svelte';
    import { answers, encProgress, saveProgress, unanswered, setEncKey, setUserId, fillRandom, validate, saveAnswers } from './stores/answers.svelte';

    let userId = $state(0);
    let saving = $state(false);
    let submitted = $state(false);
    let keyError = $state(null);

    try {
        setEncKey(publicKeyData.n, publicKeyData.g);
    } catch {
        keyError = 'This questionnaire could not be initialised: the encryption key is missing or invalid.';
    }

    async function handleSave() {
        if (!validate()) {
            // Scroll to first unanswered question
            const firstKey = Object.keys(unanswered)[0];
            const el = document.getElementById(firstKey);
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        saving = true;
        try {
            const ok = await saveAnswers();
            if (ok) submitted = true;
        } catch {
            window.alert('Something went wrong submitting your answers. Please try again.');
        } finally {
            saving = false;
        }
    }

    function handleTest() {
        fillRandom(questionData);
        userId = Math.floor(Math.random() * 9000) + 1000;
        setUserId(userId);
    }

    function handleRepeat() {
        for (const key of Object.keys(answers)) {
            delete answers[key];
        }
        userId = 0;
        setUserId(0);
        submitted = false;
    }

    function handleClose() {
        window.close();
        document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:Figtree,sans-serif;color:#686f85;">You can close this tab now.</div>';
    }
</script>

{#if keyError}
<div class="page-body">
    <div class="card">
        <div class="empty-state">{keyError}</div>
    </div>
</div>
{:else if submitted}
<div class="page-body">
    <div class="card thank-you-card">
        <div class="thank-you-icon">
            <svg width="56" height="56" viewBox="0 0 56 56" fill="none">
                <circle cx="28" cy="28" r="28" fill="var(--c-blue)" opacity="0.1"/>
                <circle cx="28" cy="28" r="20" fill="var(--c-blue)" opacity="0.15"/>
                <path d="M22 28.5L26 32.5L34 24.5" stroke="var(--c-blue)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h1 class="thank-you-title">Thanks for your answers</h1>
        <p class="thank-you-message">Your responses have been encrypted and submitted. Individual answers cannot be viewed by anyone — only aggregated statistics are available.</p>
        <div class="thank-you-actions">
            <button class="btn btn--primary" onclick={handleRepeat}>Complete again</button>
            <button class="btn btn--secondary" onclick={handleClose}>Close</button>
        </div>
    </div>
</div>
{:else}
<div class="page-body">
    <h1 class="page-title">Questionnaire</h1>
    <p class="page-subtitle">Your responses are encrypted before they leave your browser. Individual answers cannot be viewed by anyone.</p>

    {#each questionData as question, i (question.id)}
        <div class="card" class:card--error={unanswered[`q${question.id}`]} id="q{question.id}">
            <Question question={question} index={i + 1} error={unanswered[`q${question.id}`]} />
        </div>
    {:else}
        <div class="card">
            <div class="empty-state">No questions have been configured yet.</div>
        </div>
    {/each}

    <div class="card">
        <div class="section-title">Submit</div>
        <div class="field-group">
            <label for="userId">Your User ID</label>
            <div class="user-id-field">
                <input type="number" id="userId" bind:value={userId} onchange={() => setUserId(userId)} placeholder="Enter your ID">
            </div>
        </div>
        {#if saving}
            <div class="progress-section">
                {#if encProgress.done < encProgress.total}
                    <div class="progress-label">
                        Encrypting question {encProgress.questionsDone + 1} of {encProgress.questionsTotal}...
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" style="width: {encProgress.total ? (encProgress.done / encProgress.total) * 100 : 0}%"></div>
                    </div>
                {:else}
                    <div class="progress-label">
                        Saving question {Math.min(saveProgress.done + 1, saveProgress.total)} of {saveProgress.total}...
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" style="width: {saveProgress.total ? (saveProgress.done / saveProgress.total) * 100 : 0}%"></div>
                    </div>
                {/if}
            </div>
        {:else}
            <div class="form-actions">
                <button class="btn btn--primary" onclick={handleSave}>
                    Save responses
                </button>
                <button class="btn btn--secondary" onclick={handleTest}>
                    Test
                </button>
            </div>
        {/if}
    </div>
</div>
{/if}

<style>
    .field-group {
        margin-bottom: 1.2em;
    }

    :global(.card--error) {
        border-color: var(--c-red) !important;
    }

    .thank-you-card {
        text-align: center;
        padding: 3rem 2rem;
        max-width: 480px;
        margin: 2rem auto;
    }

    .thank-you-icon {
        margin-bottom: 1.5rem;
    }

    .thank-you-title {
        font-size: var(--f-xl);
        font-weight: 600;
        color: var(--c-text);
        margin: 0 0 0.75rem 0;
    }

    .thank-you-message {
        font-size: var(--f-sm);
        color: var(--c-grey-dark-2);
        line-height: 1.6;
        margin: 0 0 2rem 0;
    }

    .thank-you-actions {
        display: flex;
        gap: 0.75rem;
        justify-content: center;
    }

    .progress-section {
        margin-top: 0.5rem;
    }

    .progress-label {
        font-size: var(--f-xs);
        color: var(--c-grey-dark-2);
        margin-bottom: 0.5rem;
    }

    .progress-track {
        height: 8px;
        background: var(--c-border);
        border-radius: 4px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: var(--c-blue);
        border-radius: 4px;
        transition: width 0.15s ease-out;
    }
</style>
