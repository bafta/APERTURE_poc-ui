<script>
import { answers, unanswered } from "../stores/answers.svelte";
let { question, index = 1, error = false } = $props();

answers[`q${question.id}`] = {};
for(const option of question.options) {
    answers[`q${question.id}`][`o${option.num}`] = false;
}

const clearError = () => {
    delete unanswered[`q${question.id}`];
};

const radioSet = (qId, num) => {
    for(const opt in answers[`q${qId}`]) {
        answers[`q${qId}`][opt] = false;
    }
    answers[`q${qId}`][`o${num}`] = true;
    clearError();
};
</script>

<div class="question">
    <div class="question-header">
        <span class="question-number">{index}</span>
        <h2 class="question-title">{question.question}</h2>
    </div>
    <span class="tag tag--blue">{question.type === 'checkbox' ? 'Select all that apply' : 'Choose one'}</span>
    <div class="option-group">
        {#each question.options as option (option.num)}
            <div class="option-item">
                {#if question.type === 'checkbox'}
                    <input type="checkbox" id="q{question.id}p{option.num}" name="q{question.id}" bind:checked={answers[`q${question.id}`][`o${option.num}`]} onchange={clearError}>
                {:else if question.type === 'radio'}
                    <input type="radio" id="q{question.id}p{option.num}" name="q{question.id}" checked={answers[`q${question.id}`][`o${option.num}`]} onchange={() => radioSet(question.id, option.num)}>
                {/if}
                <label for="q{question.id}p{option.num}">{option.option}</label>
            </div>
        {/each}
    </div>
    {#if error}
        <div class="validation-error">Please select at least one answer.</div>
    {/if}
</div>

<style>
    .question-header {
        display: flex;
        align-items: baseline;
        gap: 0.6em;
        margin-bottom: 0.5em;
    }

    .question-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.6em;
        height: 1.6em;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--c-blue);
        background-color: var(--c-blue-light-2);
        border-radius: 50%;
        flex-shrink: 0;
    }

    .question-title {
        margin: 0;
    }

    .tag {
        margin-bottom: 1em;
    }

    .validation-error {
        margin-top: 0.75em;
        font-size: var(--f-xs);
        font-weight: 600;
        color: var(--c-red);
    }
</style>
