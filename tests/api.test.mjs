import assert from 'node:assert/strict';
import test from 'node:test';

const baseUrl = (process.env.LIMIS_TEST_BASE_URL || 'http://localhost:8080').replace(/\/+$/, '');
const tokenUrl = `${baseUrl}/tagasiside/token.php`;
const submitUrl = `${baseUrl}/tagasiside/submit.php`;

async function postSubmission(payload) {
    return fetch(submitUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
}

test('token endpoint returns a no-store HMAC token', async () => {
    const response = await fetch(tokenUrl);

    assert.equal(response.status, 200);
    assert.match(response.headers.get('content-type') || '', /application\/json/i);
    assert.match(response.headers.get('cache-control') || '', /no-store/i);

    const body = await response.json();
    assert.match(body.token || '', /^[a-f\d]{32}\.\d+\.[a-f\d]{64}$/i);
});

test('submit endpoint rejects GET and advertises POST', async () => {
    const response = await fetch(submitUrl);

    assert.equal(response.status, 405);
    assert.equal(response.headers.get('allow'), 'POST');
    assert.deepEqual(await response.json(), { error: 'method' });
});

test('submit endpoint rejects malformed JSON', async () => {
    const response = await fetch(submitUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{'
    });

    assert.equal(response.status, 400);
    assert.deepEqual(await response.json(), { error: 'invalid' });
});

test('submit endpoint rejects a missing token before database access', async () => {
    const response = await postSubmission({ lang: 'ru' });

    assert.equal(response.status, 403);
    assert.deepEqual(await response.json(), { error: 'csrf' });
});

test('honeypot submissions return a neutral success', async () => {
    const response = await postSubmission({ website: 'automated-test' });

    assert.equal(response.status, 200);
    assert.deepEqual(await response.json(), { ok: true });
});

test('submit endpoint rejects invalid enum values before database access', async () => {
    const tokenResponse = await fetch(tokenUrl);
    assert.equal(tokenResponse.status, 200);
    const { token } = await tokenResponse.json();

    await new Promise(resolve => setTimeout(resolve, 2100));

    const response = await postSubmission({
        token,
        lang: 'xx',
        purpose: 'other',
        completed: 'yes',
        ease_rating: 5
    });

    assert.equal(response.status, 422);
    assert.deepEqual(await response.json(), { error: 'validation' });
});

test('submit endpoint enforces the 500-character comment limit', async () => {
    const tokenResponse = await fetch(tokenUrl);
    assert.equal(tokenResponse.status, 200);
    const { token } = await tokenResponse.json();

    await new Promise(resolve => setTimeout(resolve, 2100));

    const response = await postSubmission({
        token,
        lang: 'ru',
        purpose: 'other',
        completed: 'yes',
        ease_rating: 5,
        comment: 'x'.repeat(501)
    });

    assert.equal(response.status, 422);
    assert.deepEqual(await response.json(), { error: 'too_long' });
});
