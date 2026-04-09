import * as bigintConversion from 'bigint-conversion';
import * as paillierBigint from 'paillier-bigint';

export let answers = $state({});
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

export const saveAnswers = async () => {
    if(encKey === null) {
        window.alert('No encryption key set');
        return;
    }
    if(userId == 0) {
        window.alert('Set a user ID');
        return;
    }

    let questionIds = [];
    let allEncAnswers = [];
    for(const qId of Object.keys(answers)) {
        const questionId = parseInt(qId.substring(1), 10);
        questionIds.push(questionId);
        let encAnswers = [];
        for(const oNum of Object.keys(answers[qId])) {
            const optionNum = parseInt(oNum.substring(1), 10);
            const encAnswer = bigintConversion.bigintToBase64(encKey.encrypt(BigInt(answers[qId][oNum] ? 1 : 0)));
            encAnswers.push({
                optionNum,
                encAnswer,
            });
        }
        allEncAnswers.push({
            questionId,
            encAnswers,
        });
    }
    //console.log('Enc answers:', allEncAnswers);
    const jsonData = JSON.stringify({
        userToken: `${userId};123456789;abcdefg`,
        questionIds: `${questionIds.join(',')};123456789;abcdefg`,
        answers: allEncAnswers,
    });
    console.log('Sending:', jsonData);
    const res = await fetch('/api/submitResponse.php', {
        method: 'POST',
        body: jsonData,
    });
    const respData = await res.json();
    console.log(respData);
    if(respData.status == 'OK') {
        window.alert('Saved');
    } else {
        window.alert('Failed!');
    }
};
