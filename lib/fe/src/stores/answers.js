import { writable, get } from "svelte/store";

function createAnswerStore() {
    const store = writable({});

    const saveAnswers = (encKey) => {
        const answers = get(store);
    };

    return {
        ...store,
        saveAnswers,
    }
}

export const answers = createAnswerStore();
