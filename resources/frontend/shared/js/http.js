const API_TIMEOUT = Object.freeze({
    DEFAULT: 30_000,
    UPLOAD: 60_000,
    AI: 90_000
});

class ApiError extends Error {
    constructor(message, { status = 0, code = 'request_failed', data = null, cause = null } = {}) {
        super(message, cause ? { cause } : undefined);
        this.name = 'ApiError';
        this.status = status;
        this.code = code;
        this.data = data;
    }
}

function apiFetch(url, options = {}) {
    const { timeoutMs = 0, signal: externalSignal, ...fetchOptions } = options;
    const headers = new Headers(fetchOptions.headers || {});
    if (telegramInitData) {
        headers.set('X-Telegram-Init-Data', telegramInitData);
    }

    if (!timeoutMs) {
        return fetch(url, { ...fetchOptions, headers, signal: externalSignal });
    }

    const controller = new AbortController();
    const abortFromExternalSignal = () => controller.abort(externalSignal?.reason);
    if (externalSignal?.aborted) {
        abortFromExternalSignal();
    } else {
        externalSignal?.addEventListener('abort', abortFromExternalSignal, { once: true });
    }

    const timeoutId = window.setTimeout(() => {
        controller.abort(new DOMException('Request timed out', 'TimeoutError'));
    }, timeoutMs);

    return fetch(url, { ...fetchOptions, headers, signal: controller.signal })
        .finally(() => {
            window.clearTimeout(timeoutId);
            externalSignal?.removeEventListener('abort', abortFromExternalSignal);
        });
}

async function apiRequestJson(url, options = {}) {
    const {
        timeoutMs = API_TIMEOUT.DEFAULT,
        requireSuccessStatus = true,
        json,
        ...fetchOptions
    } = options;
    const headers = new Headers(fetchOptions.headers || {});

    if (json !== undefined) {
        headers.set('Content-Type', 'application/json');
        fetchOptions.body = JSON.stringify(json);
    }

    const response = await apiRequestResponse(url, { ...fetchOptions, headers, timeoutMs });

    const data = await parseJsonResponse(response);
    if (data === null) {
        throw new ApiError('Сервер вернул некорректный ответ', {
            status: response.status,
            code: 'invalid_json'
        });
    }

    const hasSuccessfulStatus = !requireSuccessStatus || data.status === 'success';
    if (!response.ok || !hasSuccessfulStatus) {
        throw new ApiError(getApiErrorMessage(response.status, data), {
            status: response.status,
            code: getApiErrorCode(response.status, data),
            data
        });
    }

    return data;
}

async function apiRequestBlob(url, options = {}) {
    const { timeoutMs = API_TIMEOUT.DEFAULT, ...fetchOptions } = options;
    const response = await apiRequestResponse(url, { ...fetchOptions, timeoutMs });

    if (!response.ok) {
        const data = await parseJsonResponse(response);
        throw new ApiError(getApiErrorMessage(response.status, data), {
            status: response.status,
            code: getApiErrorCode(response.status, data),
            data
        });
    }

    return response.blob();
}

async function apiRequestResponse(url, options) {
    try {
        return await apiFetch(url, options);
    } catch (error) {
        if (error?.name === 'AbortError' || error?.name === 'TimeoutError') {
            throw new ApiError('Сервер не успел ответить. Попробуйте ещё раз', {
                code: 'timeout',
                cause: error
            });
        }

        throw new ApiError('Нет соединения с сервером', {
            code: 'network',
            cause: error
        });
    }
}

async function parseJsonResponse(response) {
    try {
        return await response.json();
    } catch (error) {
        return null;
    }
}

function getApiErrorMessage(status, data) {
    if (status === 401 || status === 403) {
        return 'Сессия Telegram недействительна. Закройте и заново откройте приложение через Telegram.';
    }

    const serverMessage = String(data?.message || data?.error || '').trim();
    if (serverMessage) {
        return serverMessage;
    }

    return {
        400: 'Проверьте заполненные данные',
        413: 'Загруженный файл слишком большой',
        429: 'Лимит запросов исчерпан. Попробуйте позже',
        502: 'Сервис временно недоступен',
        504: 'Сервис не успел ответить. Попробуйте ещё раз'
    }[status] || 'Не удалось выполнить запрос';
}

function getApiErrorCode(status, data) {
    if (status === 401 || status === 403) {
        return 'telegram_auth_required';
    }

    return String(data?.code || 'request_failed');
}
