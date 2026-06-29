const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

global.window = globalThis;
global.telegramInitData = 'telegram-signed-data';

const sourcePath = path.resolve(__dirname, '../../resources/frontend/shared/js/http.js');
const source = fs.readFileSync(sourcePath, 'utf8');
vm.runInThisContext(`${source}\nglobalThis.__httpClient = { ApiError, apiFetch, apiRequestJson, apiRequestBlob };`, {
    filename: sourcePath
});

const { ApiError, apiRequestJson, apiRequestBlob } = global.__httpClient;
const originalFetch = global.fetch;

test.afterEach(() => {
    global.fetch = originalFetch;
});

test('sends Telegram auth and serializes JSON request body', async () => {
    let request = null;
    global.fetch = async (url, options) => {
        request = { url, options };
        return new Response(JSON.stringify({ status: 'success', data: { saved: true } }), {
            status: 200,
            headers: { 'Content-Type': 'application/json' }
        });
    };

    const result = await apiRequestJson('/api/example', {
        method: 'POST',
        json: { name: 'Салат' }
    });

    assert.equal(result.data.saved, true);
    assert.equal(request.url, '/api/example');
    assert.equal(request.options.headers.get('X-Telegram-Init-Data'), 'telegram-signed-data');
    assert.equal(request.options.headers.get('Content-Type'), 'application/json');
    assert.equal(request.options.body, JSON.stringify({ name: 'Салат' }));
});

test('throws structured ApiError for unsuccessful response', async () => {
    global.fetch = async () => new Response(JSON.stringify({
        status: 'error',
        message: 'Лимит исчерпан'
    }), {
        status: 429,
        headers: { 'Content-Type': 'application/json' }
    });

    await assert.rejects(
        () => apiRequestJson('/api/limited'),
        error => error instanceof ApiError
            && error.status === 429
            && error.message === 'Лимит исчерпан'
    );
});

test('normalizes Telegram authorization errors', async () => {
    global.fetch = async () => new Response(JSON.stringify({
        status: 'error',
        message: 'Invalid Telegram init data'
    }), {
        status: 401,
        headers: { 'Content-Type': 'application/json' }
    });

    await assert.rejects(
        () => apiRequestJson('/api/progress'),
        error => error instanceof ApiError
            && error.status === 401
            && error.code === 'telegram_auth_required'
            && error.message.includes('заново откройте приложение')
    );
});

test('accepts successful JSON without status field when explicitly allowed', async () => {
    global.fetch = async () => new Response(JSON.stringify({
        registered: true,
        daily_goal: 2000
    }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' }
    });

    const result = await apiRequestJson('/api/user-status', {
        requireSuccessStatus: false
    });

    assert.equal(result.registered, true);
    assert.equal(result.daily_goal, 2000);
});

test('reports invalid JSON response', async () => {
    global.fetch = async () => new Response('<html>error</html>', { status: 502 });

    await assert.rejects(
        () => apiRequestJson('/api/broken'),
        error => error instanceof ApiError
            && error.status === 502
            && error.code === 'invalid_json'
    );
});

test('aborts request after configured timeout', async () => {
    global.fetch = async (url, options) => new Promise((resolve, reject) => {
        options.signal.addEventListener('abort', () => {
            reject(options.signal.reason || new DOMException('Aborted', 'AbortError'));
        }, { once: true });
    });

    await assert.rejects(
        () => apiRequestJson('/api/slow', { timeoutMs: 5 }),
        error => error instanceof ApiError && error.code === 'timeout'
    );
});

test('loads protected blob with Telegram auth', async () => {
    let requestHeaders = null;
    global.fetch = async (url, options) => {
        requestHeaders = options.headers;
        return new Response(new Blob(['image-bytes'], { type: 'image/jpeg' }), {
            status: 200,
            headers: { 'Content-Type': 'image/jpeg' }
        });
    };

    const blob = await apiRequestBlob('/api/meals/55/thumbnail');

    assert.equal(requestHeaders.get('X-Telegram-Init-Data'), 'telegram-signed-data');
    assert.equal(blob.type, 'image/jpeg');
    assert.equal(await blob.text(), 'image-bytes');
});
