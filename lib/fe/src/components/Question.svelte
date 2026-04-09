<script>
import { answers } from "../stores/answers.svelte";
let { question } = $props();

answers[`q${question.id}`] = {};
for(const option of question.options) {
    answers[`q${question.id}`][`o${option.num}`] = false;
}

const radioSet = (qId, num) => {
    for(const opt in answers[`q${qId}`]) {
        answers[`q${qId}`][opt] = false;
    }
    answers[`q${qId}`][`o${num}`] = true;
};

//$inspect(answers);
</script>

<section>
    <h2>{question.question}</h2>
    {#each question.options as option (option.num)}
        <div>
            {#if question.type == 'checkbox'}
                <input type="checkbox" id="q{question.id}p{option.num}" name="q{question.id}" bind:checked={answers[`q${question.id}`][`o${option.num}`]}>
            {:else if question.type == 'radio'}
                <input type="radio" id="q{question.id}p{option.num}" name="q{question.id}" onchange={() => radioSet(question.id, option.num)}>
            {/if}
            <label for="q{question.id}p{option.num}">{option.option}</label>
        </div>
    {/each}
</section>
