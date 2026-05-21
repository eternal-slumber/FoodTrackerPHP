function apiFetch(url, options = {}) {
    const headers = new Headers(options.headers || {});
    if (telegramInitData) {
        headers.set('X-Telegram-Init-Data', telegramInitData);
    }

    return fetch(url, {
        ...options,
        headers
    });
}
