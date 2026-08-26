import * as bigintConversion from 'bigint-conversion';
import * as paillierBigint from 'paillier-bigint';

export let answers = $state({});
export let encProgress = $state({ done: 0, total: 0, questionsDone: 0, questionsTotal: 0 });
export let saveProgress = $state({ done: 0, total: 0 });
export let unanswered = $state({});
let encKey = null;
let userId = 0;

export const setEncKey = (nData, gData) => {
    const n = bigintConversion.base64ToBigint(nData);
    const g = bigintConversion.base64ToBigint(gData);
    encKey = new paillierBigint.PublicKey(n, g);
};

export const setUserId = (newUserId) => {
    userId = parseInt(newUserId, 10);
};

export const fillRandom = (questionData) => {
    for (const q of questionData) {
        const key = `q${q.id}`;
        if (q.type === 'radio') {
            // Pick one random option
            const pick = Math.floor(Math.random() * q.options.length);
            for (let i = 0; i < q.options.length; i++) {
                answers[key][`o${q.options[i].num}`] = (i === pick);
            }
        } else {
            // Checkbox: pick at least one, random selection
            let anyPicked = false;
            for (const opt of q.options) {
                const on = Math.random() < 0.4;
                answers[key][`o${opt.num}`] = on;
                if (on) anyPicked = true;
            }
            if (!anyPicked) {
                const pick = q.options[Math.floor(Math.random() * q.options.length)];
                answers[key][`o${pick.num}`] = true;
            }
        }
    }
};

export const validate = () => {
    // Clear previous errors
    for (const key of Object.keys(unanswered)) {
        delete unanswered[key];
    }

    let valid = true;
    for (const qId of Object.keys(answers)) {
        const hasAnswer = Object.values(answers[qId]).some(v => v === true);
        if (!hasAnswer) {
            unanswered[qId] = true;
            valid = false;
        }
    }
    return valid;
};

export const saveAnswers = async () => {
    if(encKey === null) {
        window.alert('No encryption key set');
        return;
    }
    if(userId == 0) {
        window.alert('Set a user ID');
        return;
    }
    if(!validate()) {
        return;
    }

    // Count totals for progress tracking
    let total = 0;
    const qIds = Object.keys(answers);
    for (const qId of qIds) {
        total += Object.keys(answers[qId]).length;
    }
    encProgress.done = 0;
    encProgress.total = total;
    encProgress.questionsDone = 0;
    encProgress.questionsTotal = qIds.length;

    let questionIds = [];
    let allEncAnswers = [];
    for(const qId of qIds) {
        const questionId = parseInt(qId.substring(1), 10);
        questionIds.push(questionId);
        let encAnswers = [];
        for(const oNum of Object.keys(answers[qId])) {
            const optionNum = parseInt(oNum.substring(1), 10);
            // Yield to let the UI repaint the progress bar
            await new Promise(r => setTimeout(r, 0));
            const encAnswer = bigintConversion.bigintToBase64(encKey.encrypt(BigInt(answers[qId][oNum] ? 1 : 0)));
            encProgress.done++;
            encAnswers.push({
                optionNum,
                encAnswer,
            });
        }
        encProgress.questionsDone++;
        allEncAnswers.push({
            questionId,
            encAnswers,
        });
    }
    const jsonData = JSON.stringify({
        userToken: `${userId};123456789;abcdefg`,
        questionIds: `${questionIds.join(',')};123456789;abcdefg`,
        answers: allEncAnswers,
    });

    saveProgress.done = 0;
    saveProgress.total = qIds.length;

    const res = await fetch('/api/submitResponse.php', {
        method: 'POST',
        body: jsonData,
    });

    const reader = res.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });

        const lines = buffer.split('\n');
        // Keep the last (possibly incomplete) chunk in the buffer
        buffer = lines.pop();

        for (const line of lines) {
            const trimmed = line.trim();
            if (!trimmed) continue;
            let msg;
            try {
                msg = JSON.parse(trimmed);
            } catch {
                continue;
            }
            if (msg.progress !== undefined) {
                saveProgress.done = msg.progress;
                saveProgress.total = msg.total;
            }
            if (msg.status === 'OK') {
                return true;
            }
            if (msg.error) {
                window.alert('Submission failed: ' + msg.error);
                return false;
            }
        }
    }

    // Process any remaining buffer content
    const trimmed = buffer.trim();
    if (trimmed) {
        try {
            const msg = JSON.parse(trimmed);
            if (msg.status === 'OK') return true;
            if (msg.error) {
                window.alert('Submission failed: ' + msg.error);
                return false;
            }
        } catch {
            // ignore
        }
    }

    window.alert('Submission failed. Please try again.');
    return false;
};
