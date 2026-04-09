<script>
    import Question from './components/Question.svelte';
    import { setEncKey, setUserId, saveAnswers } from './stores/answers.svelte';

    setEncKey(publicKeyData.n, publicKeyData.g);

    let userId = 0;
</script>

<main>
    <h1>Questions...</h1>
    {#each questionData as question (question.id)}
        <Question question={question} />
    {:else}
        <div>No question data</div>
    {/each}
    <h3>User ID</h3>
    <div>
        <input type="number" bind:value={userId} onchange={() => setUserId(userId)}>
    </div>
    <br><br>
    <div class="buttons">
        <button onclick="{saveAnswers}">Save</button>
    </div>
</main>

<style>
</style>
