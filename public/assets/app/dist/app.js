// Source: shared/js/helpers.js
function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatMacro(value) {
    const number = Number(value || 0);
    return Number.isInteger(number) ? String(number) : number.toFixed(1);
}

function getTimezoneOffsetMinutes() {
    return new Date().getTimezoneOffset();
}

function setElementText(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.textContent = String(value ?? '');
    }
}

function formatActivityLabel(activityLevel) {
    return {
        minimal: 'Минимальная',
        low: 'Низкая',
        medium: 'Средняя',
        high: 'Высокая',
        extra: 'Очень высокая'
    }[activityLevel || 'medium'] || 'Средняя';
}

function formatGoalLabel(goal) {
    return {
        deficit: 'Похудение',
        maintenance: 'Поддержание',
        surplus: 'Набор массы'
    }[goal || 'maintenance'] || 'Поддержание';
}

// Source: app/js/auth.js
function getDevTelegramUserConfig() {
    const configUser = window.__APP_CONFIG__?.devTelegramUser || {};
    const id = Number(configUser.id || 100001);
    const username = String(configUser.username || 'dev_user');
    const firstName = String(configUser.firstName || username || 'Dev');

    return {
        id,
        first_name: firstName,
        username
    };
}

function createTelegramWebAppMock() {
    const clickHandlers = new Set();
    const backHandlers = new Set();
    const devUser = getDevTelegramUserConfig();

    const mainButton = {
        setText(text) {
            console.debug('[Telegram mock] MainButton text:', text);
            return this;
        },
        show() {
            console.debug('[Telegram mock] MainButton show');
            return this;
        },
        hide() {
            console.debug('[Telegram mock] MainButton hide');
            return this;
        },
        showProgress() {
            console.debug('[Telegram mock] MainButton progress show');
            return this;
        },
        hideProgress() {
            console.debug('[Telegram mock] MainButton progress hide');
            return this;
        },
        onClick(handler) {
            clickHandlers.add(handler);
            return this;
        },
        offClick(handler) {
            clickHandlers.delete(handler);
            return this;
        }
    };

    const backButton = {
        show() {
            console.debug('[Telegram mock] BackButton show');
            return this;
        },
        hide() {
            console.debug('[Telegram mock] BackButton hide');
            return this;
        },
        onClick(handler) {
            backHandlers.add(handler);
            return this;
        },
        offClick(handler) {
            backHandlers.delete(handler);
            return this;
        }
    };

    return {
        initData: '',
        initDataUnsafe: {
            user: devUser
        },
        colorScheme: window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light',
        themeParams: {},
        MainButton: mainButton,
        BackButton: backButton,
        HapticFeedback: {
            notificationOccurred(type) {
                console.debug('[Telegram mock] haptic notification:', type);
            },
            impactOccurred(type) {
                console.debug('[Telegram mock] haptic impact:', type);
            }
        },
        expand() {
            console.debug('[Telegram mock] expand');
        },
        requestFullscreen() {
            console.debug('[Telegram mock] requestFullscreen');
        },
        setHeaderColor(color) {
            console.debug('[Telegram mock] header color:', color);
        },
        setBackgroundColor(color) {
            console.debug('[Telegram mock] background color:', color);
        },
        setBottomBarColor(color) {
            console.debug('[Telegram mock] bottom bar color:', color);
        },
        onEvent(eventName) {
            console.debug('[Telegram mock] onEvent:', eventName);
        },
        showAlert(message) {
            window.alert(message);
        },
        showConfirm(message, callback) {
            callback(window.confirm(message));
        }
    };
}

function hasTelegramSession(webApp) {
    return Boolean(webApp?.initData && webApp?.initDataUnsafe?.user?.id);
}

function isDevTelegramMockAllowed() {
    return window.__APP_CONFIG__?.appEnv === 'local'
        && Boolean(window.__APP_CONFIG__?.devTelegramUser?.id);
}

function renderTelegramAuthHardFail() {
    document.body.innerHTML = [
        '<main style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:#0f1115;color:#f5f5f7;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif;">',
        '<section style="max-width:420px;width:100%;border:1px solid rgba(255,255,255,.12);border-radius:8px;padding:28px;">',
        '<h1 style="font-size:24px;margin:0 0 10px;">Telegram required</h1>',
        '<p style="color:#a0a3aa;line-height:1.5;margin:0;">Откройте приложение через Telegram. Без валидной Telegram WebApp-сессии доступ отключён.</p>',
        '</section>',
        '</main>'
    ].join('');
}

function getAppBackgroundColor() {
    return colorToHex(getComputedStyle(document.body).backgroundColor) || '#ffffff';
}

const APP_THEME_STORAGE_KEY = 'foodTracker.theme';

function normalizeAppTheme(theme) {
    return ['system', 'light', 'dark'].includes(theme) ? theme : 'system';
}

function getStoredAppTheme() {
    try {
        return normalizeAppTheme(localStorage.getItem(APP_THEME_STORAGE_KEY) || 'system');
    } catch (error) {
        return 'system';
    }
}

function normalizeEffectiveTheme(theme) {
    return theme === 'dark' ? 'dark' : 'light';
}

function getThemeFromColor(color) {
    var hex = colorToHex(color || '');

    if (!hex) {
        return null;
    }

    var red = parseInt(hex.slice(1, 3), 16);
    var green = parseInt(hex.slice(3, 5), 16);
    var blue = parseInt(hex.slice(5, 7), 16);
    var luminance = (0.2126 * red + 0.7152 * green + 0.0722 * blue) / 255;

    return luminance < 0.5 ? 'dark' : 'light';
}

function getSystemAppTheme() {
    if (tg?.colorScheme === 'dark' || tg?.colorScheme === 'light') {
        return tg.colorScheme;
    }

    var telegramBgTheme = getThemeFromColor(tg?.themeParams?.bg_color);

    if (telegramBgTheme) {
        return telegramBgTheme;
    }

    if (window.matchMedia?.('(prefers-color-scheme: dark)').matches) {
        return 'dark';
    }

    return 'light';
}

function getEffectiveAppTheme(theme) {
    var normalizedTheme = normalizeAppTheme(theme);

    return normalizeEffectiveTheme(normalizedTheme === 'system' ? getSystemAppTheme() : normalizedTheme);
}

function applyAppTheme(theme, options = {}) {
    const normalizedTheme = normalizeAppTheme(theme);
    const effectiveTheme = getEffectiveAppTheme(normalizedTheme);

    if (options.animate) {
        document.documentElement.classList.add('theme-transition');
        window.setTimeout(() => {
            document.documentElement.classList.remove('theme-transition');
        }, 340);
    }

    document.documentElement.dataset.appTheme = normalizedTheme;
    document.documentElement.dataset.appEffectiveTheme = effectiveTheme;
    document.documentElement.style.colorScheme = effectiveTheme;

    if (options.persist) {
        try {
            localStorage.setItem(APP_THEME_STORAGE_KEY, normalizedTheme);
        } catch (error) {
            console.debug('[Theme] save failed:', error);
        }
    }

    if (options.updateViewport !== false && typeof applyTelegramViewportSettings === 'function') {
        applyTelegramViewportSettings();
    }

    return normalizedTheme;
}

function syncSystemTheme() {
    if (getStoredAppTheme() === 'system') {
        applyAppTheme('system');
        return;
    }

    applyTelegramViewportSettings();
}

function colorToHex(color) {
    var match = color.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
    if (!match) {
        return /^#[0-9a-f]{6}$/i.test(color) ? color : null;
    }

    return '#' + [match[1], match[2], match[3]]
        .map(function (value) {
            return Number(value).toString(16).padStart(2, '0');
        })
        .join('');
}

function callTelegramViewportMethod(methodName, value) {
    if (typeof tg[methodName] !== 'function') {
        return;
    }

    try {
        tg[methodName](value);
    } catch (error) {
        console.debug('[Telegram] ' + methodName + ' failed:', error);
    }
}

function applyTelegramViewportSettings() {
    var backgroundColor = getAppBackgroundColor();

    callTelegramViewportMethod('setHeaderColor', backgroundColor);
    callTelegramViewportMethod('setBackgroundColor', backgroundColor);
    callTelegramViewportMethod('setBottomBarColor', backgroundColor);

    var safeAreaTop = Number(tg.safeAreaInset?.top || 0);
    var contentSafeAreaTop = Number(tg.contentSafeAreaInset?.top || 0);
    document.documentElement.style.setProperty('--tg-safe-area-top', safeAreaTop + 'px');
    document.documentElement.style.setProperty('--tg-content-safe-area-top', contentSafeAreaTop + 'px');
}

var realTg = window.Telegram?.WebApp;
var tg = null;
if (hasTelegramSession(realTg)) {
    tg = realTg;
} else if (isDevTelegramMockAllowed()) {
    tg = createTelegramWebAppMock();
} else {
    renderTelegramAuthHardFail();
    throw new Error('Telegram WebApp initData is required');
}
applyAppTheme(getStoredAppTheme(), { updateViewport: false });
applyTelegramViewportSettings();
tg.expand();
if (typeof tg.requestFullscreen === 'function') {
    try {
        tg.requestFullscreen();
    } catch (error) {
        console.debug('[Telegram] fullscreen request failed:', error);
    }
}
if (typeof tg.onEvent === 'function') {
    tg.onEvent('safeAreaChanged', applyTelegramViewportSettings);
    tg.onEvent('fullscreenChanged', applyTelegramViewportSettings);
    tg.onEvent('themeChanged', syncSystemTheme);
}

var systemThemeMediaQuery = window.matchMedia?.('(prefers-color-scheme: dark)');
if (typeof systemThemeMediaQuery?.addEventListener === 'function') {
    systemThemeMediaQuery.addEventListener('change', syncSystemTheme);
} else if (typeof systemThemeMediaQuery?.addListener === 'function') {
    systemThemeMediaQuery.addListener(syncSystemTheme);
}

var user = tg.initDataUnsafe?.user;
var tgId = user?.id || 0;
var telegramInitData = tg.initData || '';

window.appTheme = {
    get: getStoredAppTheme,
    set(theme) {
        return applyAppTheme(theme, { persist: true, animate: true });
    }
};

// Source: shared/js/http.js
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

// Source: app/js/progress.js
let progressLoadId = 0;

// Загрузка прогресса
async function loadProgress() {
    const loadId = ++progressLoadId;
    setHomeProgressState('loading');

    try {
        const result = await apiRequestJson(`/api/progress?tz_offset=${getTimezoneOffsetMinutes()}`);

        if (loadId !== progressLoadId) {
            return false;
        }

        updateProgressUI(result.data);
        setHomeProgressState('ready');
        return true;
    } catch (error) {
        console.error('Ошибка загрузки прогресса:', error);

        if (loadId === progressLoadId) {
            setHomeProgressState('error', error?.message || 'Не удалось загрузить дневной прогресс');
        }

        return false;
    }
}

function setHomeProgressState(state, message = '') {
    const card = document.getElementById('home-progress-card');
    const feedbackText = document.getElementById('home-progress-feedback-text');
    const retryButton = document.getElementById('btn-retry-progress');

    if (!card || !feedbackText || !retryButton) {
        return;
    }

    card.dataset.state = state;
    card.setAttribute('aria-busy', String(state === 'loading'));
    feedbackText.textContent = state === 'loading'
        ? 'Загружаем дневной прогресс...'
        : message;
    retryButton.classList.toggle('hidden', state !== 'error');
}

document.getElementById('btn-retry-progress')?.addEventListener('click', loadProgress);

// Обновление UI прогресса
function updateProgressUI(data) {
    const dailyGoal = data.daily_goal || 0;
    const todaySum = data.today_sum || 0;
    const percentage = data.percentage || 0;
    const macroGoals = data.macro_goals || {};
    const todayMacros = data.today_macros || {};
    const macroPercentages = data.macro_percentages || {};

    setElementText(
        'profile-macro-goals',
        `Б ${formatMacro(macroGoals.proteins_goal)} · Ж ${formatMacro(macroGoals.fats_goal)} · У ${formatMacro(macroGoals.carbs_goal)}`
    );

    document.getElementById('daily-goal').innerText = dailyGoal;
    document.getElementById('today-calories').innerText = todaySum;
    document.getElementById('progress-percentage').innerText = `${Math.round(percentage)}%`;
    document.getElementById('remaining-calories').innerText = Math.max(0, Math.round(Number(data.remaining_calories || 0)));
    document.getElementById('progress-fill').style.setProperty(
        '--day-progress',
        Math.min(Math.max(Number(percentage), 0), 100)
    );

    updateMacroProgress('proteins', todayMacros.proteins, macroGoals.proteins_goal, macroPercentages.proteins);
    updateMacroProgress('fats', todayMacros.fats, macroGoals.fats_goal, macroPercentages.fats);
    updateMacroProgress('carbs', todayMacros.carbs, macroGoals.carbs_goal, macroPercentages.carbs);
}

function updateMacroProgress(key, currentValue, goalValue, percentage) {
    const current = Number(currentValue || 0);
    const goal = Number(goalValue || 0);
    const calculatedProgress = goal > 0 ? (current / goal) * 100 : 0;
    const apiProgress = Number(percentage);
    const progress = goal > 0
        ? calculatedProgress
        : (Number.isFinite(apiProgress) ? apiProgress : 0);
    const card = document.getElementById(`${key}-progress-fill`);

    document.getElementById(`today-${key}`).innerText = formatMacro(current);
    document.getElementById(`goal-${key}`).innerText = formatMacro(goal);
    card.style.setProperty('--macro-progress', `${Math.min(Math.max(progress, 0), 100)}%`);
    card.setAttribute('aria-valuenow', String(Math.max(current, 0)));
    card.setAttribute('aria-valuemax', String(Math.max(goal, 0)));
    card.classList.toggle('is-over-goal', goal > 0 && current > goal);
    document.getElementById(`${key}-warning`)?.classList.toggle('hidden', goal <= 0 || current <= goal);
}

// Source: app/js/daily-insight.js
let dailyInsightLoadId = 0;
let dailyInsightRefreshPromise = null;
let dailyInsightHasContent = false;
let dailyInsightNeedsFreshContent = false;
let dailyInsightRefreshQueued = false;
let dailyInsightRevision = 0;

async function loadDailyNutritionInsight() {
    if (dailyInsightNeedsFreshContent) {
        return refreshDailyNutritionInsight();
    }

    const loadId = ++dailyInsightLoadId;

    if (!dailyInsightHasContent) {
        setDailyInsightLoading();
    }

    try {
        const result = await apiRequestJson(`/api/daily-insight?tz_offset=${getTimezoneOffsetMinutes()}`);

        if (loadId !== dailyInsightLoadId) {
            return;
        }

        renderDailyNutritionInsight(result.data);

        if (result.data?.state === 'missing' || result.data?.state === 'stale') {
            refreshDailyNutritionInsight();
        }
    } catch (error) {
        if (loadId === dailyInsightLoadId) {
            renderDailyInsightError(error?.message);
        }
    }
}

function refreshDailyNutritionInsight({ invalidate = false } = {}) {
    if (invalidate) {
        dailyInsightRevision += 1;
        dailyInsightLoadId += 1;
        dailyInsightNeedsFreshContent = true;
        dailyInsightHasContent = false;
        setDailyInsightLoading('Обновляем рекомендацию после нового приёма...');
    }

    if (dailyInsightRefreshPromise) {
        if (invalidate) {
            dailyInsightRefreshQueued = true;
        }

        return dailyInsightRefreshPromise;
    }

    const refreshRevision = dailyInsightRevision;

    if (!dailyInsightHasContent && !dailyInsightNeedsFreshContent) {
        setDailyInsightLoading('Анализируем сегодняшний рацион...');
    }

    dailyInsightRefreshPromise = (async () => {
        try {
            const result = await apiRequestJson(
                `/api/daily-insight/refresh?tz_offset=${getTimezoneOffsetMinutes()}`,
                {
                    method: 'POST',
                    timeoutMs: API_TIMEOUT.AI
                }
            );

            if (refreshRevision === dailyInsightRevision) {
                dailyInsightNeedsFreshContent = false;
                renderDailyNutritionInsight(result.data);
            }

            return result.data;
        } catch (error) {
            if (refreshRevision === dailyInsightRevision) {
                dailyInsightNeedsFreshContent = false;
                renderDailyInsightError(error?.message);
            }

            return null;
        } finally {
            dailyInsightRefreshPromise = null;

            if (dailyInsightRefreshQueued) {
                dailyInsightRefreshQueued = false;
                refreshDailyNutritionInsight();
            }
        }
    })();

    return dailyInsightRefreshPromise;
}

function renderDailyNutritionInsight(data) {
    const state = data?.state || 'missing';
    const insight = data?.insight;

    if (state === 'empty') {
        dailyInsightHasContent = false;
        setDailyInsightState('empty');
        setDailyInsightText({
            shortSummary: 'Добавьте первый приём — после него появится персональная рекомендация.',
            dayAnalysis: 'Пока недостаточно данных для анализа питания за сегодня.',
            nextMealAdvice: '',
            nextMealType: '',
            targetCalories: 0,
            foods: []
        });
        return;
    }

    if (!insight) {
        dailyInsightHasContent = false;
        setDailyInsightLoading('Формируем рекомендацию по сегодняшним приёмам...');
        return;
    }

    const nextMeal = insight.next_meal || {};
    dailyInsightHasContent = true;
    setDailyInsightState(state);
    setDailyInsightText({
        shortSummary: insight.short_summary || 'Рекомендация обновлена.',
        dayAnalysis: insight.day_analysis || '',
        nextMealAdvice: nextMeal.advice || '',
        nextMealType: nextMeal.type || 'следующий приём',
        targetCalories: Number(nextMeal.target_calories || 0),
        foods: Array.isArray(nextMeal.foods) ? nextMeal.foods : []
    });
}

function setDailyInsightText({
    shortSummary,
    dayAnalysis,
    nextMealAdvice,
    nextMealType,
    foods
}) {
    setDailyInsightElementText('home-ai-insight-short', shortSummary);
    setDailyInsightElementText('summary-ai-day-analysis', dayAnalysis);
    setDailyInsightElementText(
        'summary-ai-next-meal',
        nextMealAdvice
            ? `${nextMealType ? `${capitalizeDailyInsightText(nextMealType)}: ` : ''}${nextMealAdvice}`
            : ''
    );
    renderDailyInsightFoods(foods);
}

function setDailyInsightLoading(message = 'Загружаем рекомендацию...') {
    setDailyInsightState('loading');
    setDailyInsightElementText('home-ai-insight-short', message);
    setDailyInsightElementText('summary-ai-day-analysis', message);
    setDailyInsightElementText('summary-ai-next-meal', '');
    renderDailyInsightFoods([]);
}

function renderDailyInsightError(message = '') {
    setDailyInsightState('error');

    if (dailyInsightHasContent) {
        return;
    }

    const safeMessage = message || 'AI-рекомендация временно недоступна';
    setDailyInsightElementText('home-ai-insight-short', safeMessage);
    setDailyInsightElementText('summary-ai-day-analysis', safeMessage);
    setDailyInsightElementText('summary-ai-next-meal', 'Приёмы пищи сохранены — ошибка AI на них не влияет.');
    renderDailyInsightFoods([]);
}

function setDailyInsightState(state) {
    document.getElementById('home-ai-insight-card')?.setAttribute('data-insight-state', state);
    document.getElementById('summary-ai-insight-card')?.setAttribute('data-insight-state', state);
    document.getElementById('btn-retry-daily-insight')?.classList.toggle('hidden', state !== 'error');
}

function renderDailyInsightFoods(foods) {
    const container = document.getElementById('summary-ai-tags');
    if (!container) {
        return;
    }

    container.replaceChildren(...foods.slice(0, 5).map(food => {
        const item = document.createElement('span');
        item.textContent = String(food);
        return item;
    }));
    container.classList.toggle('hidden', container.childElementCount === 0);
}

function setDailyInsightElementText(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = String(value || '');
    }
}

function capitalizeDailyInsightText(value) {
    const text = String(value || '').trim();
    return text ? text.charAt(0).toUpperCase() + text.slice(1) : '';
}

document.getElementById('btn-retry-daily-insight')?.addEventListener('click', () => {
    refreshDailyNutritionInsight();
});

document.getElementById('home-ai-insight-card')?.addEventListener('click', () => {
    showScreen('summary');
});

document.getElementById('home-ai-insight-card')?.addEventListener('keydown', event => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        showScreen('summary');
    }
});

// Source: app/js/features/history/data.js
// Meal history loading and cache

let mealDetailBackHandler = null;
let mealDetailParentBackHandler = null;
let mealDetailKeepBodyLockedOnClose = false;
let protectedImagesObserver = null;
let protectedImagesRenderId = 0;
let mealHistoryCache = null;
let mealHistoryDirty = true;
let mealHistoryLoadPromise = null;
let historySwipeGesture = null;
const historyMealDetailsCache = new Map();
const HISTORY_MAX_SWIPE_X = -120;
const HISTORY_OPEN_THRESHOLD = -18;
const HISTORY_INTENT_THRESHOLD = 6;

async function loadMealHistory(options = {}) {
    const force = Boolean(options.force);
    const historyList = document.getElementById('history-list');

    if (!force && !mealHistoryDirty && Array.isArray(mealHistoryCache)) {
        if (historyList?.dataset.historyRendered !== '1') {
            renderMealHistory(mealHistoryCache);
        }

        return mealHistoryCache;
    }

    if (mealHistoryLoadPromise) {
        return mealHistoryLoadPromise;
    }

    mealHistoryLoadPromise = fetchMealHistory();

    try {
        return await mealHistoryLoadPromise;
    } finally {
        mealHistoryLoadPromise = null;
    }
}

async function refreshMealHistory() {
    mealHistoryDirty = true;
    return loadMealHistory({ force: true });
}

function updateMealHistoryAfterDelete(mealId) {
    historyMealDetailsCache.delete(mealId);

    if (Array.isArray(mealHistoryCache)) {
        mealHistoryCache = mealHistoryCache.filter(meal => Number(meal.id) !== Number(mealId));
        mealHistoryDirty = false;
        renderMealHistory(mealHistoryCache);
        return;
    }

    mealHistoryDirty = true;
}

async function fetchMealHistory() {
    const historyList = document.getElementById('history-list');

    try {
        const result = await apiRequestJson('/api/history');
        mealHistoryCache = Array.isArray(result.data) ? result.data : [];
        mealHistoryDirty = false;
        renderMealHistory(mealHistoryCache);
        return mealHistoryCache;
    } catch (error) {
        console.error('Ошибка загрузки истории:', error);
        if (!Array.isArray(mealHistoryCache)) {
            historyList.innerHTML = '<p class="home-meals-error">Не удалось загрузить сегодняшние приёмы.</p>';
        }
    }

    return mealHistoryCache || [];
}

// Source: app/js/features/history/today.js
// Today meal groups on the main screen

// Отрисовка истории приемов пищи
function renderMealHistory(meals) {
    const historyList = document.getElementById('history-list');
    disconnectProtectedImagesObserver();
    revokeProtectedImageUrls(historyList);
    protectedImagesRenderId++;
    historySwipeGesture = null;
    historyList.dataset.historyRendered = '1';

    const todayKey = formatDateKey(new Date());
    const todayMeals = (Array.isArray(meals) ? meals : [])
        .map(meal => ({ ...meal, parsedDate: parseMealDate(meal.created_at) }))
        .filter(meal => meal.parsedDate && formatDateKey(meal.parsedDate) === todayKey);
    const currentSlot = getCurrentHomeMealSlot();
    const slots = getHomeMealSlots().sort((left, right) => {
        if (left.key === currentSlot) return -1;
        if (right.key === currentSlot) return 1;
        return left.order - right.order;
    });

    historyList.innerHTML = slots.map(slot => {
        const slotMeals = todayMeals.filter(meal => getHomeMealSlotForDate(meal.parsedDate) === slot.key);
        const totalCalories = slotMeals.reduce((sum, meal) => sum + Number(meal.calories || 0), 0);
        const isCurrent = slot.key === currentSlot;
        const isExpanded = isCurrent && slotMeals.length > 0;

        return `
            <article class="meal-item${isCurrent ? ' is-current' : ''}${slotMeals.length ? ' has-meals' : ''}${isExpanded ? ' is-expanded' : ''}"
                     data-meal-slot="${slot.key}">
                <button class="meal-header" type="button" aria-expanded="${String(isExpanded)}">
                    <span class="meal-icon" aria-hidden="true">${slot.icon}</span>
                    <span class="meal-title">
                        <strong>${slot.title}</strong>
                        ${slotMeals.length ? `<small>${formatHomeMealCount(slotMeals.length)}</small>` : ''}
                    </span>
                    <span class="meal-calories"><strong>${Math.round(totalCalories)}</strong> ккал</span>
                    <span class="meal-chevron" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M7 10l5 5 5-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"/>
                        </svg>
                    </span>
                </button>
                <div class="meal-panel${slotMeals.length ? '' : ' meal-panel-empty'}">
                    ${slotMeals.length
                        ? slotMeals.map(renderHomeMealCard).join('')
                        : '<p>Приемы пищи пока не добавлены. Нажмите плюс, чтобы добавить блюдо.</p>'}
                </div>
            </article>
        `;
    }).join('');

    requestAnimationFrame(() => {
        historyList.querySelectorAll('.meal-item.is-expanded').forEach(item => {
            setHomeMealItemExpanded(item, true);
        });
    });
    observeProtectedImages();
}

function getHomeMealSlots() {
    return [
        {
            key: 'breakfast',
            title: 'Завтрак',
            order: 0,
            icon: '<svg viewBox="0 0 24 24"><path d="M5 16.8c0-3.9 3.1-7 7-7s7 3.1 7 7H5Z" fill="currentColor"/><path d="M3.2 18.2h17.6c.4 0 .7.3.7.7s-.3.7-.7.7H3.2c-.4 0-.7-.3-.7-.7s.3-.7.7-.7Z" fill="currentColor"/><path d="M12 3c.4 0 .7.3.7.7v2a.7.7 0 0 1-1.4 0v-2c0-.4.3-.7.7-.7ZM19.2 6.1c.3.3.3.7 0 1l-1.4 1.4a.7.7 0 0 1-1-1l1.4-1.4c.3-.3.7-.3 1 0ZM4.8 6.1c.3-.3.7-.3 1 0l1.4 1.4a.7.7 0 0 1-1 1L4.8 7.1c-.3-.3-.3-.7 0-1ZM2.4 13.2c0-.4.3-.7.7-.7h2a.7.7 0 0 1 0 1.4h-2c-.4 0-.7-.3-.7-.7ZM18.9 12.5h2a.7.7 0 0 1 0 1.4h-2a.7.7 0 0 1 0-1.4Z" fill="currentColor"/></svg>'
        },
        {
            key: 'lunch',
            title: 'Обед',
            order: 1,
            icon: '<svg viewBox="0 0 24 24"><path d="M12 17.2a5.2 5.2 0 1 0 0-10.4 5.2 5.2 0 0 0 0 10.4Z" fill="currentColor"/><path d="M12 1.9c.4 0 .7.3.7.7v1.8a.7.7 0 0 1-1.4 0V2.6c0-.4.3-.7.7-.7ZM12 18.9c.4 0 .7.3.7.7v1.8a.7.7 0 0 1-1.4 0v-1.8c0-.4.3-.7.7-.7ZM22.1 12c0 .4-.3.7-.7.7h-1.8a.7.7 0 0 1 0-1.4h1.8c.4 0 .7.3.7.7ZM5.1 12c0 .4-.3.7-.7.7H2.6a.7.7 0 0 1 0-1.4h1.8c.4 0 .7.3.7.7ZM19.1 4.9c.3.3.3.7 0 1l-1.3 1.3a.7.7 0 0 1-1-1l1.3-1.3c.3-.3.7-.3 1 0ZM7.2 16.8c.3.3.3.7 0 1l-1.3 1.3a.7.7 0 0 1-1-1l1.3-1.3c.3-.3.7-.3 1 0ZM19.1 19.1c-.3.3-.7.3-1 0l-1.3-1.3a.7.7 0 0 1 1-1l1.3 1.3c.3.3.3.7 0 1ZM7.2 7.2c-.3.3-.7.3-1 0L4.9 5.9a.7.7 0 0 1 1-1l1.3 1.3c.3.3.3.7 0 1Z" fill="currentColor"/></svg>'
        },
        {
            key: 'dinner',
            title: 'Ужин',
            order: 2,
            icon: '<svg viewBox="0 0 24 24"><path d="M18.8 15.3c-5.3 0-9.6-4.3-9.6-9.6 0-.9.1-1.8.4-2.6.1-.5-.4-.9-.8-.6A9.9 9.9 0 1 0 21.5 15c.2-.5-.3-.9-.7-.8-.7.1-1.3.2-2 .2Z" fill="currentColor"/><path d="M17.6 4.4 18 6c.1.3.3.5.6.6l1.6.4c.4.1.4.7 0 .8l-1.6.4c-.3.1-.5.3-.6.6l-.4 1.6c-.1.4-.7.4-.8 0l-.4-1.6c-.1-.3-.3-.5-.6-.6l-1.6-.4c-.4-.1-.4-.7 0-.8l1.6-.4c.3-.1.5-.3.6-.6l.4-1.6c.1-.4.7-.4.8 0Z" fill="currentColor"/></svg>'
        },
        {
            key: 'snacks',
            title: 'Перекусы',
            order: 3,
            icon: '<svg viewBox="0 0 24 24"><path d="M16.2 7.5c1.8 0 3.1 1.6 3.1 4.3 0 4-2.6 8.2-5.2 8.2-.8 0-1.2-.3-2.1-.3-.9 0-1.3.3-2.1.3-2.6 0-5.2-4.2-5.2-8.2 0-2.7 1.3-4.3 3.1-4.3 1.2 0 2 .6 2.7.6.6 0 1.3-.6 2.5-.6.9 0 1.5.2 2 .5.3-.3.7-.5 1.2-.5Z" fill="currentColor"/><path d="M12.9 6.4c.2-2.1 1.3-3.5 3.3-4 .2 2.1-.9 3.6-3.3 4Z" fill="currentColor"/></svg>'
        }
    ];
}

function getCurrentHomeMealSlot() {
    return getHomeMealSlotForDate(new Date());
}

function getHomeMealSlotForDate(date) {
    const hour = date.getHours();

    if (hour >= 5 && hour < 12) return 'breakfast';
    if (hour >= 12 && hour < 16) return 'lunch';
    if (hour >= 16 && hour < 22) return 'dinner';
    return 'snacks';
}

function formatHomeMealCount(count) {
    const lastDigit = count % 10;
    const lastTwoDigits = count % 100;
    const label = lastDigit === 1 && lastTwoDigits !== 11
        ? 'блюдо'
        : (lastDigit >= 2 && lastDigit <= 4 && (lastTwoDigits < 12 || lastTwoDigits > 14) ? 'блюда' : 'блюд');

    return `${count} ${label}`;
}

function renderHomeMealCard(meal) {
    const description = escapeHtml(meal.description || 'Приём пищи');
    const time = meal.parsedDate
        ? meal.parsedDate.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
        : '—';
    const imageUrl = escapeHtml(meal.thumbnail_url || meal.image_url || '');
    const thumbnail = imageUrl
        ? `<img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="" data-image-url="${imageUrl}" loading="lazy">`
        : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8.5h14v10H5zM8 8.5l1.4-2h5.2l1.4 2M9 13l2 2 4-4 3 3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>';

    return `
        <div class="swipe-row home-meal-swipe-row">
            <button class="delete-action history-delete-action" type="button" data-meal-id="${Number(meal.id || 0)}">Удалить</button>
            <div class="meal-card-wrapper home-meal-card-wrapper">
                <article class="meal-food-card home-meal-swipe-card">
                    <span class="meal-food-thumb">${thumbnail}</span>
                    <span class="meal-food-info">
                        <strong>${description}</strong>
                        <small>${escapeHtml(time)}</small>
                        <small>${formatMealMeta(meal)}</small>
                    </span>
                    <span class="meal-food-calories"><strong>${Number(meal.calories || 0)}</strong> ккал</span>
                </article>
            </div>
        </div>
    `;
}

function groupMealsByDate(meals) {
    const groups = new Map();

    meals.forEach(meal => {
        const date = parseMealDate(meal.created_at);
        const key = date ? formatDateKey(date) : 'no-date';
        const label = date ? formatHistoryDateLabel(date) : 'Без даты';

        if (!groups.has(key)) {
            groups.set(key, { key, label, meals: [] });
        }

        groups.get(key).meals.push({ ...meal, parsedDate: date });
    });

    return Array.from(groups.values());
}

// Source: app/js/features/history/list.js
// History list and accordion

function renderHistoryItem(meal) {
    const description = escapeHtml(meal.description);
    const meta = formatMealMeta(meal);
    const formattedTime = meal.parsedDate
        ? meal.parsedDate.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
        : '—';

    return `
        <div class="swipe-row history-swipe-row">
            <button class="delete-action history-delete-action" type="button" data-meal-id="${meal.id}">Удалить</button>
            <div class="meal-card-wrapper history-card-wrapper">
                <article class="meal-card history-item" data-meal-id="${meal.id}" aria-expanded="false">
                    <div class="history-card-main">
                        <div class="history-thumbnail">
                            ${renderHistoryThumbnail(meal, description)}
                        </div>
                        <div class="history-info">
                            <div class="history-date">${formattedTime}</div>
                            <div class="history-description">${description}</div>
                            <div class="history-macros">${meta}</div>
                        </div>
                        <div class="history-calories">
                            <span class="calories-value">${meal.calories}</span>
                            <span class="calories-label">ккал</span>
                            <span class="history-expand-indicator" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path opacity="0.18" d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" fill="currentColor"/>
                                    <path d="M10.74 16.2802C10.55 16.2802 10.36 16.2102 10.21 16.0602C9.91999 15.7702 9.91999 15.2902 10.21 15.0002L13.21 12.0002L10.21 9.00016C9.91999 8.71016 9.91999 8.23016 10.21 7.94016C10.5 7.65016 10.98 7.65016 11.27 7.94016L14.8 11.4702C15.09 11.7602 15.09 12.2402 14.8 12.5302L11.27 16.0602C11.12 16.2102 10.93 16.2802 10.74 16.2802Z" fill="currentColor"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div class="history-accordion" aria-hidden="true">
                        <div class="history-accordion-inner"></div>
                    </div>
                </article>
            </div>
        </div>
    `;
}

function renderHistoryThumbnail(meal, description) {
    const imageUrl = escapeHtml(meal.thumbnail_url || meal.image_url || '');

    if (imageUrl) {
        return `<img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="${description}" data-image-url="${imageUrl}" loading="lazy" decoding="async">`;
    }

    return `
        <span class="history-thumbnail-placeholder" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" focusable="false">
                <path d="M18.2295 10.2H5.76953L5.88953 9.36999C6.00953 8.49999 6.74953 7.85999 7.62953 7.85999H16.3695C17.2495 7.85999 17.9895 8.49999 18.1095 9.36999L18.2295 10.2Z"/>
                <g opacity="0.4">
                    <path d="M19.0697 16.14H4.92969L5.76969 10.2H11.9997C12.6497 10.2 13.1697 10.73 13.1697 11.37C13.1697 10.73 13.6997 10.2 14.3397 10.2C14.9797 10.2 15.5197 10.73 15.5197 11.37C15.5197 10.73 16.0397 10.2 16.6897 10.2H18.2297L19.0697 16.14Z"/>
                </g>
                <path d="M13.1701 6.68999C13.1701 7.32999 12.6501 7.85999 12.0001 7.85999C11.3501 7.85999 10.8301 7.32999 10.8301 6.68999C10.8301 6.04999 11.3501 5.51999 12.0001 5.51999C12.6501 5.51999 13.1701 6.03999 13.1701 6.68999Z"/>
                <path d="M20.2398 16.92V17.31C20.2398 17.96 19.7198 18.48 19.0698 18.48H4.92977C4.27977 18.48 3.75977 17.96 3.75977 17.31V16.92C3.75977 16.49 4.10977 16.14 4.53977 16.14H19.4598C19.8898 16.14 20.2398 16.49 20.2398 16.92Z"/>
            </svg>
        </span>
    `;
}

function bindHistoryInteractions() {
    const historyList = document.getElementById('history-list');

    if (!historyList || historyList.dataset.historyInteractionsBound === '1') {
        return;
    }

    historyList.dataset.historyInteractionsBound = '1';
    historyList.addEventListener('click', handleHistoryClick);
    historyList.addEventListener('pointerdown', handleHistoryPointerDown);
    document.addEventListener('pointermove', handleHistoryPointerMove);
    document.addEventListener('pointerup', handleHistoryPointerUp);
    document.addEventListener('pointercancel', handleHistoryPointerCancel);
}

function handleHistoryClick(event) {
    const homeMealHeader = event.target.closest('#screen-main .meal-header');
    if (homeMealHeader) {
        const selectedItem = homeMealHeader.closest('.meal-item');
        const shouldExpand = !selectedItem.classList.contains('is-expanded');

        document.querySelectorAll('#screen-main .meal-item').forEach(item => {
            setHomeMealItemExpanded(item, false);
        });

        if (shouldExpand) {
            setHomeMealItemExpanded(selectedItem, true);
        }

        return;
    }

    const deleteButton = event.target.closest('.history-delete-action');
    if (deleteButton) {
        const row = deleteButton.closest('.swipe-row');
        if (!row?.classList.contains('is-open')) {
            return;
        }

        deleteMeal(Number(deleteButton.dataset.mealId));
        return;
    }

    if (event.target.closest('.home-meal-swipe-card')) {
        return;
    }

    const item = event.target.closest('.meal-card');
    if (!item || event.target.closest('.history-accordion')) {
        return;
    }

    const row = item.closest('.swipe-row');
    if (!row) {
        return;
    }

    if (row.dataset.swipeHandled === '1' || row.classList.contains('is-open')) {
        row.dataset.swipeHandled = '';
        return;
    }

    toggleHistoryAccordion(row, Number(item.dataset.mealId));
}

function setHomeMealItemExpanded(item, expanded) {
    const panel = item?.querySelector('.meal-panel');
    const header = item?.querySelector('.meal-header');

    if (!item || !panel || !header) {
        return;
    }

    item.classList.toggle('is-expanded', expanded);
    header.setAttribute('aria-expanded', String(expanded));
    panel.style.maxHeight = expanded ? `${panel.scrollHeight}px` : '0px';
}

function handleHistoryPointerDown(event) {
    const item = event.target.closest('.meal-card, .home-meal-swipe-card');
    if (!item || event.target.closest('.history-accordion')) {
        return;
    }

    const row = item.closest('.swipe-row');
    const wrapper = item.closest('.meal-card-wrapper');
    if (!row || !wrapper || row.classList.contains('is-deleting')) {
        return;
    }

    closeHistorySwipeRows(row);
    historySwipeGesture = {
        row,
        item,
        wrapper,
        pointerId: event.pointerId,
        startX: event.clientX,
        startY: event.clientY,
        currentX: row.classList.contains('is-open') ? HISTORY_MAX_SWIPE_X : 0,
        wasOpen: row.classList.contains('is-open'),
        isHorizontalSwipe: false,
        isVerticalScroll: false,
        lastTranslateX: row.classList.contains('is-open') ? HISTORY_MAX_SWIPE_X : 0,
    };
    wrapper.style.transition = 'none';
}

function handleHistoryPointerMove(event) {
    const gesture = historySwipeGesture;
    if (!gesture || gesture.pointerId !== event.pointerId || gesture.isVerticalScroll) {
        return;
    }

    const deltaX = event.clientX - gesture.startX;
    const deltaY = event.clientY - gesture.startY;
    const absX = Math.abs(deltaX);
    const absY = Math.abs(deltaY);

    if (!gesture.isHorizontalSwipe) {
        if (absX < HISTORY_INTENT_THRESHOLD && absY < HISTORY_INTENT_THRESHOLD) {
            return;
        }

        if (absY > absX * 1.4) {
            gesture.isVerticalScroll = true;
            gesture.wrapper.style.transition = '';
            return;
        }

        if (deltaX > 0 && !gesture.row.classList.contains('is-open')) {
            historySwipeGesture = null;
            gesture.wrapper.style.transition = '';
            return;
        }

        gesture.isHorizontalSwipe = true;
        gesture.row.classList.add('is-horizontal-dragging', 'is-swiping', 'swiping');
        gesture.item.setPointerCapture?.(event.pointerId);
    }

    event.preventDefault();

    const nextX = Math.max(HISTORY_MAX_SWIPE_X, Math.min(0, gesture.currentX + deltaX));
    gesture.lastTranslateX = nextX;
    gesture.row.classList.toggle('is-swiping', nextX < -4);
    gesture.row.classList.toggle('swiping', nextX < -4);
    gesture.wrapper.style.transform = `translate3d(${nextX}px, 0, 0)`;
}

function handleHistoryPointerUp(event) {
    finishHistoryPointerGesture(event, false);
}

function handleHistoryPointerCancel(event) {
    finishHistoryPointerGesture(event, true);
}

function finishHistoryPointerGesture(event, isCanceled) {
    const gesture = historySwipeGesture;
    if (!gesture || gesture.pointerId !== event.pointerId) {
        return;
    }

    historySwipeGesture = null;

    if (gesture.isHorizontalSwipe) {
        gesture.item.releasePointerCapture?.(event.pointerId);
    }

    gesture.row.classList.remove('swiping', 'is-swiping');
    gesture.row.classList.remove('is-horizontal-dragging');
    gesture.wrapper.style.transition = '';

    if (isCanceled) {
        const shouldRemainOpen = gesture.isHorizontalSwipe && gesture.lastTranslateX < HISTORY_OPEN_THRESHOLD;
        setHistorySwipeRowOpen(gesture.row, shouldRemainOpen || gesture.row.classList.contains('is-open'));
        return;
    }

    const totalDeltaX = event.clientX - gesture.startX;
    const shouldOpen = gesture.isHorizontalSwipe
        && (gesture.lastTranslateX < HISTORY_OPEN_THRESHOLD || totalDeltaX < HISTORY_OPEN_THRESHOLD);
    closeHistorySwipeRows(gesture.row);
    setHistorySwipeRowOpen(gesture.row, shouldOpen);
    gesture.row.dataset.swipeHandled = shouldOpen || gesture.isHorizontalSwipe || gesture.wasOpen ? '1' : '';
}

async function toggleHistoryAccordion(row, mealId) {
    if (!mealId) return;

    if (row.classList.contains('detail-open')) {
        closeHistoryAccordions();
        return;
    }

    closeHistoryAccordions(row);
    closeHistorySwipeRows(row);

    setHistoryAccordionContent(row, '<div class="history-detail-content"><p class="history-detail-loading">Загрузка деталей...</p></div>');
    openHistoryAccordion(row);

    try {
        const meal = await fetchMealDetail(mealId);
        if (!row.isConnected) {
            return;
        }

        setHistoryAccordionContent(row, renderHistoryAccordionDetail(meal));
    } catch (error) {
        if (!row.isConnected) {
            return;
        }

        console.error('Ошибка загрузки детализации приема:', error);
        setHistoryAccordionContent(row, '<div class="history-detail-content"><p class="history-detail-loading">Не удалось загрузить детализацию</p></div>');
    }
}

function closeHistoryAccordions(exceptRow = null) {
    document.querySelectorAll('.history-swipe-row.detail-open').forEach(row => {
        if (row === exceptRow) return;

        closeHistoryAccordion(row);
    });
}

function setHistoryAccordionContent(row, html) {
    const accordion = row.querySelector('.history-accordion');
    const accordionInner = row.querySelector('.history-accordion-inner');

    if (!accordion || !accordionInner) {
        return;
    }

    const isOpen = row.classList.contains('detail-open');
    if (isOpen) {
        accordion.style.height = `${accordion.offsetHeight}px`;
    }

    accordionInner.innerHTML = html;

    if (isOpen) {
        requestAnimationFrame(() => animateHistoryAccordionHeight(row));
    }
}

function openHistoryAccordion(row) {
    const accordion = row.querySelector('.history-accordion');
    if (!accordion) {
        return;
    }

    accordion.setAttribute('aria-hidden', 'false');
    accordion.style.height = '0px';
    row.classList.add('detail-open');
    row.querySelector('.history-item')?.setAttribute('aria-expanded', 'true');

    requestAnimationFrame(() => animateHistoryAccordionHeight(row));
}

function closeHistoryAccordion(row) {
    const accordion = row.querySelector('.history-accordion');
    if (!accordion) {
        return;
    }

    accordion.style.height = `${accordion.offsetHeight}px`;
    row.classList.remove('detail-open');
    accordion.setAttribute('aria-hidden', 'true');
    row.querySelector('.history-item')?.setAttribute('aria-expanded', 'false');

    requestAnimationFrame(() => {
        accordion.style.height = '0px';
    });
}

function animateHistoryAccordionHeight(row) {
    const accordion = row.querySelector('.history-accordion');
    const accordionInner = row.querySelector('.history-accordion-inner');

    if (!accordion || !accordionInner || !row.classList.contains('detail-open')) {
        return;
    }

    accordion.style.height = `${accordionInner.scrollHeight}px`;
}

async function fetchMealDetail(mealId) {
    if (historyMealDetailsCache.has(mealId)) {
        return historyMealDetailsCache.get(mealId);
    }

    const result = await apiRequestJson(`/api/meals/${mealId}`);

    historyMealDetailsCache.set(mealId, result.data);
    return result.data;
}

function renderHistoryAccordionDetail(meal) {
    return `
        <div class="history-detail-content">
            <div class="history-detail-totals">
                <div><strong>${meal.weight ? Number(meal.weight) : '—'}</strong><span>граммы</span></div>
                <div><strong>${formatMacro(meal.proteins)}</strong><span>белки</span></div>
                <div><strong>${formatMacro(meal.fats)}</strong><span>жиры</span></div>
                <div><strong>${formatMacro(meal.carbs)}</strong><span>углеводы</span></div>
            </div>
            <div class="history-detail-products">
                ${renderHistoryAccordionProducts(meal.products || [])}
            </div>
        </div>
    `;
}

function renderHistoryAccordionProducts(products) {
    if (products.length === 0) {
        return '<p class="history-detail-empty">Детализация недоступна для старых записей</p>';
    }

    return products.map(product => `
        <article class="history-detail-product">
            <div>
                <strong>${escapeHtml(product.name || 'Продукт')}</strong>
                <small>${formatMealProductMeta(product)}</small>
            </div>
            <span>${Number(product.calories || 0)} ккал</span>
        </article>
    `).join('');
}

// Source: app/js/features/history/details.js
// Meal details view

async function openMealDetail(mealId, options = {}) {
    if (!mealId) return;

    try {
        const meal = await fetchMealDetail(mealId);
        renderMealDetail(meal);
        document.getElementById('meal-detail-sheet').classList.remove('hidden');
        document.body.classList.add('sheet-open');

        mealDetailParentBackHandler = options.parentBackHandler || null;
        mealDetailKeepBodyLockedOnClose = Boolean(options.keepBodyLockedOnClose);

        if (mealDetailParentBackHandler) {
            tg.BackButton.offClick(mealDetailParentBackHandler);
        }

        mealDetailBackHandler = closeMealDetail;
        tg.BackButton.show();
        tg.BackButton.onClick(mealDetailBackHandler);
    } catch (error) {
        console.error('Ошибка загрузки детализации приема:', error);
        tg.showAlert(error?.message || 'Ошибка загрузки детализации');
    }
}

function closeMealDetail() {
    document.getElementById('meal-detail-sheet').classList.add('hidden');

    if (mealDetailBackHandler) {
        tg.BackButton.offClick(mealDetailBackHandler);
        mealDetailBackHandler = null;
    }

    if (mealDetailParentBackHandler) {
        tg.BackButton.show();
        tg.BackButton.onClick(mealDetailParentBackHandler);
        mealDetailParentBackHandler = null;
    } else {
        tg.BackButton.hide();
    }

    if (!mealDetailKeepBodyLockedOnClose) {
        document.body.classList.remove('sheet-open');
    }

    mealDetailKeepBodyLockedOnClose = false;
}

function renderMealDetail(meal) {
    const date = parseMealDate(meal.created_at);
    const formattedTime = date
        ? date.toLocaleString('ru-RU', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })
        : 'Прием пищи';

    document.getElementById('meal-detail-time').textContent = formattedTime;
    document.getElementById('meal-detail-title').textContent = meal.description || 'Прием пищи';
    document.getElementById('meal-detail-calories').textContent = Number(meal.calories || 0);
    document.getElementById('meal-detail-weight').textContent = meal.weight ? Number(meal.weight) : '—';
    document.getElementById('meal-detail-proteins').textContent = formatMacro(meal.proteins);
    document.getElementById('meal-detail-fats').textContent = formatMacro(meal.fats);
    document.getElementById('meal-detail-carbs').textContent = formatMacro(meal.carbs);
    document.getElementById('meal-detail-products-list').innerHTML = renderMealDetailProducts(meal.products || []);
}

function renderMealDetailProducts(products) {
    if (products.length === 0) {
        return '<p class="meal-detail-empty">Детализация недоступна для старых записей</p>';
    }

    return products.map(product => `
        <article class="meal-detail-product">
            <div>
                <strong>${escapeHtml(product.name || 'Продукт')}</strong>
                <small>${formatMealProductMeta(product)}</small>
            </div>
            <span>${Number(product.calories || 0)} ккал</span>
        </article>
    `).join('');
}

function formatMealProductMeta(product) {
    const parts = [];
    const processingLabel = getProcessingLabel(product.processing || '');

    if (product.weight) {
        parts.push(`${Number(product.weight)} г`);
    }

    if (processingLabel) {
        parts.push(processingLabel);
    }

    parts.push(`Б ${formatMacro(product.proteins)}`);
    parts.push(`Ж ${formatMacro(product.fats)}`);
    parts.push(`У ${formatMacro(product.carbs)}`);

    return escapeHtml(parts.join(' · '));
}

function getProcessingLabel(processing) {
    const labels = {
        fry: 'Жарка',
        bake: 'Запекание',
        boil: 'Варка',
        stew: 'Тушение',
        grill: 'Гриль',
        steam: 'На пару',
        deep_fry: 'Фритюр',
        no_oil_fry: 'Жарка без масла'
    };

    return labels[processing] || '';
}

document.querySelector('.meal-detail-overlay').onclick = closeMealDetail;
document.querySelector('.meal-detail-panel').onclick = event => event.stopPropagation();
document.getElementById('btn-meal-detail-close').onclick = closeMealDetail;

// Source: app/js/features/history/support.js
// History gestures, images and deletion

document.addEventListener('pointerdown', event => {
    const row = event.target.closest('.swipe-row');
    if (row) {
        closeHistorySwipeRows(row);
        return;
    }

    closeHistorySwipeRows();
});

function setHistorySwipeRowOpen(row, isOpen) {
    const wrapper = row.querySelector('.meal-card-wrapper');
    if (!wrapper) {
        return;
    }

    row.classList.remove('is-closing');

    if (isOpen) {
        row.classList.add('is-open', 'open');
        wrapper.style.transform = `translate3d(${HISTORY_MAX_SWIPE_X}px, 0, 0)`;
        return;
    }

    const wasOpen = row.classList.contains('is-open') || row.classList.contains('open');
    row.classList.remove('is-open', 'open');
    row.classList.toggle('is-closing', wasOpen);
    wrapper.style.transform = '';

    if (wasOpen) {
        window.setTimeout(() => row.classList.remove('is-closing'), 340);
    }
}

function closeHistorySwipeRows(exceptRow = null) {
    document.querySelectorAll('.swipe-row.is-open, .history-swipe-row.open').forEach(row => {
        if (row === exceptRow) return;

        row.classList.remove('swiping', 'is-swiping');
        row.querySelector('.meal-card-wrapper')?.style.removeProperty('transition');
        setHistorySwipeRowOpen(row, false);
    });
}

function formatMealMeta(meal) {
    const parts = [];

    if (meal.weight) {
        parts.push(`${meal.weight} г`);
    }

    parts.push(`Б ${formatMacro(meal.proteins)}`);
    parts.push(`Ж ${formatMacro(meal.fats)}`);
    parts.push(`У ${formatMacro(meal.carbs)}`);

    return escapeHtml(parts.join(' · '));
}

function parseMealDate(value) {
    if (!value) {
        return null;
    }

    const rawValue = String(value).trim();
    let date = new Date(rawValue);

    if (Number.isNaN(date.getTime()) && rawValue.includes(' ')) {
        date = new Date(rawValue.replace(' ', 'T'));
    }

    return Number.isNaN(date.getTime()) ? null : date;
}

function formatDateKey(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function formatHistoryDateLabel(date) {
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);

    const shortDate = date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long'
    });

    if (formatDateKey(date) === formatDateKey(today)) {
        return 'Сегодня';
    }

    if (formatDateKey(date) === formatDateKey(yesterday)) {
        return `Вчера, ${shortDate}`;
    }

    return date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
}

function disconnectProtectedImagesObserver() {
    if (!protectedImagesObserver) {
        return;
    }

    protectedImagesObserver.disconnect();
    protectedImagesObserver = null;
}

function revokeProtectedImageUrls(root = document) {
    root.querySelectorAll('img[data-object-url]').forEach(image => {
        URL.revokeObjectURL(image.dataset.objectUrl);
        delete image.dataset.objectUrl;
    });
}

function observeProtectedImages() {
    const images = Array.from(document.querySelectorAll('img[data-image-url]'))
        .filter(image => image.dataset.imageUrl && image.dataset.imageLoaded !== '1' && image.dataset.imageLoading !== '1');
    const renderId = String(protectedImagesRenderId);

    if (images.length === 0) {
        return;
    }

    images.forEach(image => {
        image.dataset.imageRenderId = renderId;
    });

    if (!('IntersectionObserver' in window)) {
        loadProtectedImages(images, renderId);
        return;
    }

    disconnectProtectedImagesObserver();
    protectedImagesObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) {
                return;
            }

            observer.unobserve(entry.target);
            loadProtectedImage(entry.target, renderId);
        });
    }, {
        rootMargin: '300px 0px',
        threshold: 0.01
    });

    images.forEach(image => protectedImagesObserver.observe(image));
}

async function loadProtectedImages(images = document.querySelectorAll('img[data-image-url]'), renderId = String(protectedImagesRenderId)) {
    for (const image of images) {
        await loadProtectedImage(image, renderId);
    }
}

async function loadProtectedImage(image, renderId = String(protectedImagesRenderId)) {
    if (image.dataset.imageLoading === '1' || image.dataset.imageLoaded === '1') {
        return;
    }

    if (!image.isConnected || image.dataset.imageRenderId !== renderId) {
        return;
    }

    const url = image.dataset.imageUrl;
    if (!url) {
        return;
    }

    image.dataset.imageLoading = '1';

    try {
        const blob = await apiRequestBlob(url);

        if (!image.isConnected || image.dataset.imageRenderId !== renderId) {
            return;
        }

        const objectUrl = URL.createObjectURL(blob);

        if (image.dataset.objectUrl) {
            URL.revokeObjectURL(image.dataset.objectUrl);
        }

        image.src = objectUrl;
        image.dataset.objectUrl = objectUrl;
        image.dataset.imageLoaded = '1';
    } catch (error) {
        console.error('Ошибка загрузки изображения:', error);
    } finally {
        delete image.dataset.imageLoading;
    }
}

// Удаление записи о приеме пищи
async function deleteMeal(mealId) {
    const confirmed = confirm('Вы уверены, что хотите удалить эту запись?');

    if (!confirmed) {
        return;
    }

    const row = document.querySelector(`.swipe-row .history-delete-action[data-meal-id="${mealId}"]`)?.closest('.swipe-row');
    const deleteButton = row?.querySelector('.history-delete-action');

    row?.classList.add('is-deleting');
    if (deleteButton) {
        deleteButton.disabled = true;
    }

    try {
        await apiRequestJson('/api/delete-meal', {
            method: 'POST',
            json: { meal_id: mealId }
        });

        tg.showAlert('Запись успешно удалена');
        updateMealHistoryAfterDelete(mealId);
        loadProgress();
        refreshHistoryCalendar();
        refreshSummary();
        refreshDailyNutritionInsight();
    } catch (error) {
        row?.classList.remove('is-deleting');
        if (deleteButton) {
            deleteButton.disabled = false;
        }
        tg.showAlert(error?.message || 'Ошибка соединения с сервером');
    }
}

bindHistoryInteractions();

// Source: app/js/features/meal-draft/state.js
// Meal draft DOM references and state

const bottomSheet = document.getElementById('bottom-sheet');
const appShell = document.getElementById('app');

if (bottomSheet && appShell && bottomSheet.parentElement !== appShell) {
    appShell.append(bottomSheet);
}

const methodScreen = document.getElementById('sheet-screen-method');
const photoScreen = document.getElementById('sheet-screen-photo');
const editorScreen = document.getElementById('sheet-screen-editor');
const cameraInput = document.getElementById('camera-input');
const galleryInput = document.getElementById('gallery-input');
const draftPhotoImg = document.getElementById('draft-photo-img');
const draftPhotoPreview = document.getElementById('draft-photo-preview');
const photoActions = document.querySelector('.photo-actions');
const btnCamera = document.getElementById('btn-camera');
const btnGallery = document.getElementById('btn-gallery');
const btnManualEntry = document.getElementById('btn-manual-entry');
const btnAnalyzePhoto = document.getElementById('btn-analyze-photo');
const btnAnalyzePhotoText = btnAnalyzePhoto.querySelector('.photo-analyze-text');
const btnChangePhoto = document.getElementById('btn-change-photo');
const btnCancelDraft = document.getElementById('btn-cancel-draft');
const btnAddProduct = document.getElementById('btn-add-product');
const btnSaveMeal = document.getElementById('btn-save-meal');
const btnAddManualPhoto = document.getElementById('btn-add-manual-photo');
const attachmentPhotoInput = document.getElementById('manual-photo-input');
const manualPhotoPreview = document.getElementById('manual-photo-preview');
const manualPhotoImg = document.getElementById('manual-photo-img');
const draftImageStatus = document.getElementById('draft-image-status');
const productsList = document.getElementById('products-list');
const mainProductContainer = document.getElementById('draft-main-product-container');
const mealNameInput = document.getElementById('meal-name');
const draftSourceLabel = document.getElementById('draft-source-label');
const draftPhotoHero = document.getElementById('draft-photo-hero');
const draftPhotoEmpty = document.getElementById('draft-photo-empty');
const btnDraftPhotoSelect = document.getElementById('btn-draft-photo-select');
const btnRemoveMainPhoto = document.getElementById('btn-remove-main-photo');
const draftMealType = document.getElementById('draft-meal-type');
const draftProductsCount = document.getElementById('draft-products-count');

const MAX_PRODUCTS_PER_MEAL = 6;
const MAX_DRAFT_SCANS_PER_MEAL = 3;
const MAX_UPLOAD_SIZE_BYTES = 10 * 1024 * 1024;
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const PHOTO_INTENTS = {
    AI_SCAN: 'ai_scan',
    ATTACH_ONLY: 'attach_only',
    APPEND_SCAN: 'append_scan'
};

let PROCESSING_OPTIONS = [
    { value: '', label: 'Не указано - КБЖУ готового продукта', coefficient: 1 },
    { value: 'fry', label: 'Жарка', coefficient: 1.4 },
    { value: 'bake', label: 'Запекание', coefficient: 1.3 },
    { value: 'boil', label: 'Варка', coefficient: 1.2 },
    { value: 'stew', label: 'Тушение', coefficient: 1.1 },
    { value: 'grill', label: 'Гриль', coefficient: 1.25 },
    { value: 'steam', label: 'На пару', coefficient: 1.05 },
    { value: 'deep_fry', label: 'Фритюр', coefficient: 1.6 },
    { value: 'no_oil_fry', label: 'Жарка без масла', coefficient: 1.15 }
];

async function loadProcessingOptions() {
    try {
        const result = await apiRequestJson('/api/processing-options');

        if (Array.isArray(result.data) && result.data.length > 0) {
            PROCESSING_OPTIONS = result.data;
        }
    } catch (error) {
        console.error('Ошибка загрузки вариантов термообработки:', error);
    }
}

let nextDraftProductId = 1;
const additionalProductPhotos = new Map();
let mealDraft = createEmptyDraft();
let selectedPhotoFile = null;
let selectedPhotoUrl = null;
let manualPhotoUrl = null;
let currentMainButtonHandler = null;
let photoIntent = PHOTO_INTENTS.AI_SCAN;
let nextScanBatchNumber = 1;
let mealNameEditedByUser = false;
let isAnalyzingPhoto = false;
let isAnalyzingAdditionalProduct = false;
let isSavingMealDraft = false;
let isFillingKbju = false;

function isDraftAiBusy() {
    return isAnalyzingPhoto || isAnalyzingAdditionalProduct || isFillingKbju;
}

function createEmptyDraft() {
    return {
        source: 'manual',
        mealName: '',
        products: [createEmptyProduct()],
        draftImagePath: null
    };
}

function createEmptyProduct() {
    return {
        clientId: `draft-product-${nextDraftProductId++}`,
        name: '',
        weight: '',
        portions: 1,
        calories: '',
        proteins: '',
        fats: '',
        carbs: '',
        processing: '',
        scanId: ''
    };
}

function getDraftProductCards() {
    return [
        ...mainProductContainer.querySelectorAll('.product-card'),
        ...productsList.querySelectorAll('.product-card')
    ];
}

function clearAdditionalProductPhotos() {
    additionalProductPhotos.forEach(photo => {
        if (photo.url) URL.revokeObjectURL(photo.url);
    });
    additionalProductPhotos.clear();
}

function haptic(type = 'light') {
    try {
        if (type === 'error') {
            tg.HapticFeedback?.notificationOccurred('error');
        } else if (type === 'success') {
            tg.HapticFeedback?.notificationOccurred('success');
        } else {
            tg.HapticFeedback?.impactOccurred(type);
        }
    } catch (error) {
        console.debug('Haptic feedback unavailable', error);
    }
}

// Source: app/js/features/meal-draft/sheet.js
// Meal draft sheet navigation

function setMainAction(text, handler) {
    clearMainAction();
    currentMainButtonHandler = handler;
    tg.MainButton.setText(text).show();
    tg.MainButton.onClick(currentMainButtonHandler);
}

function clearMainAction() {
    if (currentMainButtonHandler) {
        tg.MainButton.offClick(currentMainButtonHandler);
        currentMainButtonHandler = null;
    }
    tg.MainButton.hide();
}

function setBackAction(handler) {
    tg.BackButton.show();
    tg.BackButton.offClick(handleSheetBack);
    tg.BackButton.onClick(handler);
}

function clearBackAction() {
    tg.BackButton.offClick(handleSheetBack);
    tg.BackButton.hide();
}

function openMealSheet() {
    mealDraft = createEmptyDraft();
    selectedPhotoFile = null;
    photoIntent = PHOTO_INTENTS.AI_SCAN;
    nextScanBatchNumber = 1;
    mealNameEditedByUser = false;
    isAnalyzingPhoto = false;
    isAnalyzingAdditionalProduct = false;
    isSavingMealDraft = false;
    isFillingKbju = false;
    clearAdditionalProductPhotos();
    if (selectedPhotoUrl) URL.revokeObjectURL(selectedPhotoUrl);
    if (manualPhotoUrl) URL.revokeObjectURL(manualPhotoUrl);
    selectedPhotoUrl = null;
    manualPhotoUrl = null;
    bottomSheet.classList.remove('hidden');
    document.body.classList.add('sheet-open');
    draftMealType.value = getMealTypeByTime();
    showMealStep('editor');
    haptic('light');
}

function closeMealSheet() {
    bottomSheet.classList.add('hidden');
    document.body.classList.remove('sheet-open');
    clearMainAction();
    clearBackAction();
    cameraInput.value = '';
    galleryInput.value = '';
    attachmentPhotoInput.value = '';
    photoIntent = PHOTO_INTENTS.AI_SCAN;
    isAnalyzingPhoto = false;
    isAnalyzingAdditionalProduct = false;
    isSavingMealDraft = false;
    isFillingKbju = false;
    if (selectedPhotoUrl) URL.revokeObjectURL(selectedPhotoUrl);
    if (manualPhotoUrl) URL.revokeObjectURL(manualPhotoUrl);
    selectedPhotoFile = null;
    selectedPhotoUrl = null;
    manualPhotoUrl = null;
    clearAdditionalProductPhotos();
    renderMainDraftPhoto();
}

function showMealStep(step) {
    methodScreen.classList.toggle('hidden', step !== 'method');
    photoScreen.classList.toggle('hidden', step !== 'photo');
    editorScreen.classList.toggle('hidden', step !== 'editor');

    if (step === 'editor') {
        setBackAction(handleSheetBack);
        clearMainAction();
        renderDraftEditor();
        renderMainDraftPhoto();
    }
}

function handleSheetBack() {
    if (!editorScreen.classList.contains('hidden')) {
        closeMealSheet();
        return;
    }

    closeMealSheet();
}

// Source: app/js/features/meal-draft/photo.js
// Main photo analysis and attachment handling

function handleAiPhotoSelected(file) {
    if (!file) return;

    if (!validateImageFileBeforeUpload(file)) {
        resetPhotoInputs();
        return;
    }

    selectedPhotoFile = file;
    if (selectedPhotoUrl) URL.revokeObjectURL(selectedPhotoUrl);
    selectedPhotoUrl = URL.createObjectURL(file);
    draftPhotoImg.src = selectedPhotoUrl;
    renderMainDraftPhoto();

    if (photoIntent === PHOTO_INTENTS.ATTACH_ONLY) {
        syncDraftFromEditor();
        mealDraft.draftImagePath = null;
        showMealStep('editor');
        return;
    }

    if (photoIntent === PHOTO_INTENTS.APPEND_SCAN) {
        syncDraftFromEditor();
        mealDraft.source = 'photo';
        showMealStep('editor');
        return;
    }

    if (photoIntent !== PHOTO_INTENTS.APPEND_SCAN) {
        mealDraft = createEmptyDraft();
        mealDraft.source = 'photo';
        mealNameEditedByUser = false;
    }

    showMealStep('editor');
    updateAnalyzePhotoButton();
}

async function analyzeSelectedPhoto() {
    if (isDraftAiBusy()) {
        tg.showAlert('Дождитесь завершения текущего AI-запроса');
        haptic('light');
        return;
    }

    if (!selectedPhotoFile) {
        tg.showAlert('Выберите фото');
        haptic('error');
        return;
    }

    if (getDraftScanCount() >= MAX_DRAFT_SCANS_PER_MEAL) {
        tg.showAlert(`В одном приеме можно сделать до ${MAX_DRAFT_SCANS_PER_MEAL} AI-сканов`);
        haptic('error');
        return;
    }

    if (photoIntent === PHOTO_INTENTS.APPEND_SCAN && getCurrentProductCount() >= MAX_PRODUCTS_PER_MEAL) {
        showProductLimitAlert();
        return;
    }

    const isAppendScan = photoIntent === PHOTO_INTENTS.APPEND_SCAN;
    const isMainProductScan = photoIntent === PHOTO_INTENTS.ATTACH_ONLY;
    const appendScanId = isAppendScan ? createScanBatchId() : null;

    if (isMainProductScan) {
        syncDraftFromEditor();
    }

    isAnalyzingPhoto = true;
    updateAnalyzePhotoButton();
    updateDraftLimitControls();
    tg.MainButton.showProgress(false);

    if (isAppendScan && appendScanId) {
        addScanLoadingCard(appendScanId);
        updateDraftSourceLabel();
    }

    const formData = new FormData();
    formData.append('photo', selectedPhotoFile);

    try {
        const result = await apiRequestJson('/api/analyze-draft', {
            method: 'POST',
            body: formData,
            timeoutMs: API_TIMEOUT.AI
        });

        const scanId = appendScanId || createScanBatchId();
        const analyzedDraft = normalizeDraft(result.data, 'photo');
        analyzedDraft.products = analyzedDraft.products.map(product => ({
            ...product,
            scanId
        }));

        if (photoIntent === PHOTO_INTENTS.APPEND_SCAN) {
            appendAnalyzedDraft(analyzedDraft);
        } else if (isMainProductScan) {
            replaceMainProductFromAnalyzedDraft(analyzedDraft);
        } else {
            mealDraft = limitDraftProducts(analyzedDraft);
        }

        photoIntent = PHOTO_INTENTS.AI_SCAN;
        haptic('success');
        showMealStep('editor');
    } catch (error) {
        if (appendScanId) {
            removeScanLoadingCard(appendScanId);
        }
        tg.showAlert(error instanceof ApiError ? getAnalyzePhotoErrorMessage(error) : 'Ошибка при анализе фото');
        haptic('error');
    } finally {
        if (isAppendScan) {
            photoIntent = PHOTO_INTENTS.AI_SCAN;
        }
        isAnalyzingPhoto = false;
        updateAnalyzePhotoButton();
        if (!editorScreen.classList.contains('hidden')) {
            updateDraftSourceLabel();
            updateDraftLimitControls();
        }
        tg.MainButton.hideProgress();
    }
}

function replaceMainProductFromAnalyzedDraft(analyzedDraft) {
    const currentProducts = Array.isArray(mealDraft.products) ? mealDraft.products : [];
    const analyzedMainProduct = analyzedDraft.products?.[0] || createEmptyProduct();

    mealDraft = {
        ...mealDraft,
        source: 'photo',
        products: [analyzedMainProduct, ...currentProducts.slice(1)].slice(0, MAX_PRODUCTS_PER_MEAL),
        draftImagePath: analyzedDraft.draftImagePath || mealDraft.draftImagePath || null
    };
}

function getAnalyzePhotoErrorMessage(error) {
    if (error.status === 413) {
        return 'Фото слишком большое. Максимум 10 МБ';
    }

    if (error.status === 504 || error.code === 'timeout') {
        return 'AI не успел ответить. Попробуйте меньшее фото или другую модель';
    }

    if (error.status === 502) {
        return error.message || 'AI сейчас недоступен. Попробуйте позже';
    }

    return error.message || 'Не удалось распознать фото';
}

function updateAnalyzePhotoButton() {
    const aiBusy = isDraftAiBusy();
    btnAnalyzePhoto.disabled = aiBusy;
    btnAnalyzePhotoText.textContent = isAnalyzingPhoto ? 'AI анализирует фото' : 'Отсканировать фото';
    btnAnalyzePhoto.classList.toggle('is-loading', isAnalyzingPhoto);
    btnAnalyzePhoto.classList.add('hidden');
    btnChangePhoto.disabled = aiBusy;
    btnRemoveMainPhoto.disabled = aiBusy;
    btnDraftPhotoSelect.disabled = aiBusy;
    btnChangePhoto.setAttribute('aria-hidden', String(isAnalyzingPhoto));
    photoActions?.classList.toggle('is-analyzing', isAnalyzingPhoto);
    draftPhotoPreview?.classList.toggle('is-processing', isAnalyzingPhoto);
    setMainProductScanState(isAnalyzingPhoto);
}

function setMainProductScanState(isLoading) {
    const card = mainProductContainer.querySelector('.draft-main-product-form');
    if (!card) {
        return;
    }

    card.classList.toggle('main-product-scan-loading', isLoading);
    card.setAttribute('aria-busy', String(isLoading));
    card.inert = isLoading;
}

function renderMainDraftPhoto() {
    const hasPhoto = Boolean(selectedPhotoUrl);

    draftPhotoHero?.classList.toggle('has-photo', hasPhoto);
    draftPhotoEmpty?.classList.toggle('hidden', hasPhoto);
    draftPhotoPreview?.classList.toggle('hidden', !hasPhoto);

    if (hasPhoto) {
        draftPhotoImg.src = selectedPhotoUrl;
    } else {
        draftPhotoImg.removeAttribute('src');
    }

    updateAnalyzePhotoButton();
    updateAllProductKbjuActionStates();
}

function removeMainDraftPhoto() {
    if (selectedPhotoUrl) {
        URL.revokeObjectURL(selectedPhotoUrl);
    }

    selectedPhotoFile = null;
    selectedPhotoUrl = null;
    mealDraft.draftImagePath = null;
    resetPhotoInputs();
    renderMainDraftPhoto();
    haptic('light');
}

function normalizeDraft(data, source) {
    const products = Array.isArray(data?.products) && data.products.length > 0
        ? data.products.map(product => ({
            clientId: `draft-product-${nextDraftProductId++}`,
            name: product.name || '',
            weight: product.weight || 100,
            portions: product.portions || 1,
            calories: product.calories || '',
            proteins: product.proteins || '',
            fats: product.fats || '',
            carbs: product.carbs || '',
            processing: product.processing || '',
            scanId: product.scanId || ''
        }))
        : [createEmptyProduct()];

    return {
        source,
        mealName: data?.meal_name || '',
        products,
        draftImagePath: data?.draft_image_path || null
    };
}

function startManualDraft() {
    mealDraft = createEmptyDraft();
    mealDraft.source = 'manual';
    mealNameEditedByUser = false;
    showMealStep('editor');
}

function renderDraftEditor() {
    mealNameInput.value = mealNameEditedByUser
        ? mealDraft.mealName || ''
        : buildGeneratedMealName(mealDraft.products);
    const products = Array.isArray(mealDraft.products) && mealDraft.products.length > 0
        ? mealDraft.products
        : [createEmptyProduct()];
    const mainProduct = products[0];
    const additionalProducts = products.slice(1, MAX_PRODUCTS_PER_MEAL);

    mainProductContainer.innerHTML = createProductCard(mainProduct, 0);
    productsList.innerHTML = additionalProducts
        .map((product, index) => createProductCard(product, index + 1))
        .join('');
    updateDraftSourceLabel();
    renderDraftImageField();
    bindProductCardEvents();
    recalculateDraftTotal();
    updateDraftLimitControls();
    draftMealType.value = draftMealType.value || getMealTypeByTime();
}

function renderDraftImageField() {
    const hasImage = Boolean(mealDraft.draftImagePath || manualPhotoUrl);

    manualPhotoPreview.classList.toggle('hidden', !manualPhotoUrl);
    btnAddManualPhoto.setAttribute('aria-label', hasImage ? 'Заменить фото' : 'Добавить фото для истории');
    btnAddManualPhoto.title = hasImage ? 'Заменить фото' : 'Добавить фото для истории';
    draftImageStatus.textContent = hasImage
        ? 'Фото будет показано в истории после сохранения'
        : 'Можно добавить миниатюру для истории';

    if (manualPhotoUrl) {
        manualPhotoImg.src = manualPhotoUrl;
    }
}

async function uploadAttachmentPhoto(file) {
    if (!file) return;

    if (!validateImageFileBeforeUpload(file)) {
        attachmentPhotoInput.value = '';
        return;
    }

    photoIntent = PHOTO_INTENTS.ATTACH_ONLY;

    if (manualPhotoUrl) URL.revokeObjectURL(manualPhotoUrl);
    manualPhotoUrl = URL.createObjectURL(file);
    renderDraftImageField();

    const previousPath = mealDraft.draftImagePath;
    const formData = new FormData();
    formData.append('photo', file);

    btnAddManualPhoto.disabled = true;
    btnAddManualPhoto.textContent = 'Загружаю...';

    try {
        const result = await apiRequestJson('/api/upload-draft-image', {
            method: 'POST',
            body: formData,
            timeoutMs: API_TIMEOUT.UPLOAD
        });

        mealDraft.draftImagePath = result.data?.draft_image_path || null;
        haptic('success');
    } catch (error) {
        mealDraft.draftImagePath = previousPath;
        tg.showAlert(error?.message || 'Ошибка загрузки фото');
        haptic('error');
    } finally {
        btnAddManualPhoto.disabled = false;
        renderDraftImageField();
        attachmentPhotoInput.value = '';
    }
}

function validateImageFileBeforeUpload(file) {
    if (file.type && !ALLOWED_IMAGE_TYPES.includes(file.type)) {
        tg.showAlert('Поддерживаются только JPEG, PNG и WebP');
        haptic('error');
        return false;
    }

    if (file.size > MAX_UPLOAD_SIZE_BYTES) {
        tg.showAlert(`Фото слишком большое: ${formatFileSize(file.size)}. Максимум 10 МБ`);
        haptic('error');
        return false;
    }

    return true;
}

function formatFileSize(bytes) {
    return `${(bytes / 1024 / 1024).toFixed(1)} МБ`;
}

// Source: app/js/features/meal-draft/products-render.js
// Product card rendering and visual state

function resetPhotoInputs() {
    cameraInput.value = '';
    galleryInput.value = '';
}

function setProductKbjuExpanded(card, isExpanded) {
    const panel = card.querySelector('.kbju-panel');
    const button = card.querySelector('.kbju-toggle');

    if (!panel || !button) {
        return;
    }

    panel.classList.toggle('hidden', !isExpanded);
    card.classList.toggle('kbju-open', isExpanded);
    button.setAttribute('aria-expanded', String(isExpanded));
    const compactLabel = card.dataset.index !== '0';
    button.innerHTML = isExpanded
        ? `<span aria-hidden="true">▴</span><span>${compactLabel ? 'Скрыть' : 'Скрыть БЖУ'}</span>`
        : `<span aria-hidden="true">▾</span><span>${compactLabel ? 'Показать' : 'Показать БЖУ'}</span>`;
}

function toggleProductKbju(card) {
    setProductKbjuExpanded(card, !card.classList.contains('kbju-open'));
    haptic('light');
}

function productCardCanAutofillKbju(card) {
    const name = card.querySelector('.product-name')?.value.trim() || '';
    const weight = Number(card.querySelector('.product-weight')?.value || 0);

    return name !== '' && weight > 0;
}

function productCardHasAnyKbju(card) {
    return [
        '.product-calories',
        '.product-proteins',
        '.product-fats',
        '.product-carbs'
    ].some(selector => String(card.querySelector(selector)?.value || '').trim() !== '');
}

function updateProductKbjuActionState(card) {
    const button = card.querySelector('.kbju-autofill-button');
    const helper = card.querySelector('.kbju-autofill-helper');

    if (!button || !helper) {
        return;
    }

    const isLoading = card.classList.contains('product-card-kbju-loading');
    const isMainProduct = card.dataset.index === '0';
    const productPhoto = additionalProductPhotos.get(card.dataset.productId);
    const hasPhoto = isMainProduct ? Boolean(selectedPhotoFile) : Boolean(productPhoto?.file);
    const canAutofill = productCardCanAutofillKbju(card);
    const hasKbju = productCardHasAnyKbju(card);
    const isBusy = isLoading || isDraftAiBusy() || isSavingMealDraft;

    const shouldHideDuringProcessing = isLoading || (isMainProduct && isAnalyzingPhoto);
    button.hidden = hasKbju || shouldHideDuringProcessing;
    helper.hidden = hasKbju || shouldHideDuringProcessing;

    if (hasKbju || shouldHideDuringProcessing) {
        return;
    }

    if (isBusy) {
        button.disabled = true;
        button.dataset.aiAction = '';
        button.querySelector('.kbju-autofill-label').textContent = 'AI заполняет данные...';
        helper.textContent = 'Дождитесь завершения обработки';
        return;
    }

    if (canAutofill) {
        button.disabled = false;
        button.dataset.aiAction = 'nutrition';
        button.querySelector('.kbju-autofill-label').textContent = 'Автозаполнить КБЖУ';
        helper.textContent = hasPhoto
            ? 'AI использует указанное название и вес, фото останется для истории'
            : 'AI предложит значения по названию и весу';
        return;
    }

    if (hasPhoto) {
        button.disabled = false;
        button.dataset.aiAction = 'photo';
        button.querySelector('.kbju-autofill-label').textContent = 'Отсканировать фото';
        helper.textContent = 'AI заполнит название, вес и КБЖУ';
        return;
    }

    button.dataset.aiAction = isMainProduct ? '' : 'select_photo';
    button.disabled = isMainProduct;
    button.hidden = isMainProduct;
    helper.hidden = isMainProduct;
    button.querySelector('.kbju-autofill-label').textContent = 'Заполнить по фото';
    helper.textContent = 'Или укажите название и вес для автозаполнения';
}

function updateAllProductKbjuActionStates() {
    getDraftProductCards()
        .filter(card => card.dataset.loading !== 'true')
        .forEach(updateProductKbjuActionState);
}

function createProductCard(product, index) {
    const processingOptions = PROCESSING_OPTIONS.map(option => {
        const selected = option.value === (product.processing || '') ? ' selected' : '';
        return `<option value="${option.value}"${selected}>${option.label}</option>`;
    }).join('');
    const productPhoto = additionalProductPhotos.get(product.clientId);
    const productThumb = productPhoto?.url
        ? `<img src="${escapeHtml(productPhoto.url)}" alt="">`
        : String(index);
    const productName = escapeHtml(product.name || 'Новый продукт');
    const productCalories = Math.round(
        (Number(product.calories) || 0)
        * (Number(product.weight) || 100)
        * (Number(product.portions) || 1)
        / 100
    );

    return `
        <div class="product-card${index === 0 ? ' draft-main-product-form' : ' draft-product-card'}"
             data-index="${index}"
             data-product-id="${escapeHtml(product.clientId || `draft-product-${nextDraftProductId++}`)}"
             data-scan-id="${escapeHtml(product.scanId || '')}">
            <div class="product-card-header">
                ${index === 0 ? `
                    <div class="draft-main-product-heading">
                        <h2>${productName}</h2>
                        <p>Основной продукт</p>
                    </div>
                    <strong class="draft-main-total"><span id="draft-total-calories">${productCalories}</span> ккал</strong>
                ` : `
                    <span class="draft-product-thumb">${productThumb}</span>
                    <strong class="draft-product-name-summary">${productName}</strong>
                    <span class="draft-product-card-total">${productCalories} ккал</span>
                    <button class="product-delete" type="button" title="Удалить продукт" aria-label="Удалить продукт">
                        <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                            <path d="M6.5 6.5h7l-.6 9H7.1l-.6-9ZM5 6.5h10M8 4h4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/>
                        </svg>
                    </button>
                `}
            </div>
            ${index === 0 ? `
                <div class="kbju-autofill">
                    <button class="kbju-autofill-button draft-main-autofill" type="button">
                        <span class="kbju-autofill-label">Автозаполнить КБЖУ</span>
                    </button>
                    <p class="kbju-autofill-helper"></p>
                </div>
                <p class="draft-kbju-caption">На порцию</p>
                <div class="draft-nutrition-summary" aria-label="КБЖУ на одну порцию">
                    <div><strong id="draft-summary-calories">0</strong><span>ккал</span></div>
                    <div><strong id="draft-summary-protein">0</strong><span>белки</span></div>
                    <div><strong id="draft-summary-fat">0</strong><span>жиры</span></div>
                    <div><strong id="draft-summary-carbs">0</strong><span>углеводы</span></div>
                </div>
                <div class="draft-main-form-grid">
                    <label class="draft-main-field wide">
                        <span>Название</span>
                        <input type="text" class="product-name" value="${escapeHtml(product.name)}" placeholder="Например, куриный салат">
                    </label>
                    <div class="draft-main-portion-row">
                        <label class="draft-main-field draft-main-weight-field">
                            <span>Вес порции, г</span>
                            <input type="number" class="product-weight" value="${escapeHtml(product.weight ?? '')}" min="1" max="5000" placeholder="0">
                        </label>
                        <div class="draft-main-portions">
                            <span>Порции</span>
                            <div class="draft-portion-stepper">
                                <button class="draft-portions-minus" type="button" aria-label="Уменьшить количество порций">−</button>
                                <output class="draft-portions-value">${Math.max(1, Number(product.portions) || 1)}</output>
                                <button class="draft-portions-plus" type="button" aria-label="Увеличить количество порций">+</button>
                            </div>
                            <input class="product-portions" type="hidden" value="${Math.max(1, Number(product.portions) || 1)}">
                        </div>
                    </div>
                </div>
                <p class="draft-kbju-caption">На 100 г продукта</p>
                <div class="draft-main-kbju-grid">
                    <label class="draft-main-field">
                        <span>Ккал</span>
                        <input type="number" class="product-calories" value="${product.calories}" min="0" placeholder="0">
                    </label>
                    <label class="draft-main-field">
                        <span>Белки</span>
                        <input type="number" class="product-proteins kbju-field" value="${product.proteins}" min="0" placeholder="0">
                    </label>
                    <label class="draft-main-field">
                        <span>Жиры</span>
                        <input type="number" class="product-fats kbju-field" value="${product.fats}" min="0" placeholder="0">
                    </label>
                    <label class="draft-main-field">
                        <span>Углеводы</span>
                        <input type="number" class="product-carbs kbju-field" value="${product.carbs}" min="0" placeholder="0">
                    </label>
                </div>
            ` : `
                <div class="name-wrap">
                    <label class="field-label">Название</label>
                    <input type="text" class="product-name" value="${escapeHtml(product.name)}" placeholder="Например, хлеб">
                </div>
            `}
            ${index === 0 ? '' : `<label class="draft-processing-row">
                <span>Термообработка</span>
                <select class="processing-select">
                    ${processingOptions}
                </select>
            </label>`}
            ${index === 0 ? '' : `<div class="kbju-autofill">
                <button class="kbju-autofill-button" type="button">
                    <svg class="kbju-autofill-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M11 2.75a.75.75 0 0 1 1.43-.32l1.77 3.92 3.92 1.77a.75.75 0 0 1 0 1.36l-3.92 1.77-1.77 3.92a.75.75 0 0 1-1.36 0L9.3 11.25 5.38 9.48a.75.75 0 0 1 0-1.36L9.3 6.35l1.77-3.92A.75.75 0 0 1 11 2.75Zm.75 1.8-1.2 2.66a.75.75 0 0 1-.38.38l-2.66 1.2 2.66 1.2c.17.08.3.21.38.38l1.2 2.66 1.2-2.66c.08-.17.21-.3.38-.38l2.66-1.2-2.66-1.2a.75.75 0 0 1-.38-.38l-1.2-2.66ZM18.5 13.25a.75.75 0 0 1 .68.43l.66 1.48 1.48.66a.75.75 0 0 1 0 1.36l-1.48.66-.66 1.48a.75.75 0 0 1-1.36 0l-.66-1.48-1.48-.66a.75.75 0 0 1 0-1.36l1.48-.66.66-1.48a.75.75 0 0 1 .68-.43Zm0 2.58-.08.17a.75.75 0 0 1-.38.38l-.17.08.17.08c.17.08.3.21.38.38l.08.17.08-.17c.08-.17.21-.3.38-.38l.17-.08-.17-.08a.75.75 0 0 1-.38-.38l-.08-.17Z" fill="currentColor"/>
                    </svg>
                    <span class="kbju-autofill-label">Автозаполнить КБЖУ</span>
                </button>
                <p class="kbju-autofill-helper">Сначала укажите название и вес</p>
                <input class="draft-product-photo-input" type="file" accept="image/*" hidden>
            </div>
            <div class="draft-product-base-row">
                    <div class="weight-wrap">
                        <label class="field-label">Вес, г</label>
                        <input type="number" class="product-weight" value="${Number(product.weight) || 100}" min="1" max="5000">
                    </div>
                <div>
                    <label class="field-label">Ккал / 100г</label>
                    <input type="number" class="product-calories" value="${product.calories}" min="0" placeholder="0">
                </div>
                <div class="draft-kbju-toggle-wrap">
                    <span class="field-label">БЖУ</span>
                    <button class="kbju-toggle" type="button" aria-expanded="false">
                        <span aria-hidden="true">▾</span><span>Показать</span>
                    </button>
                </div>
            </div>
            <div class="kbju-panel hidden">
                <div class="kbju-field-wrap">
                    <input type="number" class="product-proteins kbju-field" value="${product.proteins}" min="0" placeholder="0">
                    <span class="kbju-label">бел</span>
                </div>
                <div class="kbju-field-wrap">
                    <input type="number" class="product-fats kbju-field" value="${product.fats}" min="0" placeholder="0">
                    <span class="kbju-label">жир</span>
                </div>
                <div class="kbju-field-wrap">
                    <input type="number" class="product-carbs kbju-field" value="${product.carbs}" min="0" placeholder="0">
                    <span class="kbju-label">угл</span>
                </div>
            </div>
            <div class="draft-product-loading-overlay" aria-hidden="true">
                <span class="draft-product-loading-spinner" aria-hidden="true"></span>
                <strong class="draft-product-loading-label">AI заполняет КБЖУ</strong>
                <small>Карточка обновится автоматически</small>
            </div>`}
            ${index === 0 ? `<div class="draft-main-product-loading-overlay" aria-hidden="true">
                <span class="draft-main-product-loading-spinner" aria-hidden="true"></span>
                <strong>AI автозаполняет блюдо</strong>
                <small>Название и КБЖУ появятся автоматически</small>
            </div>` : ''}
        </div>
    `;
}

function createScanLoadingCard(scanId, index) {
    return `
        <div class="product-card product-card-loading" data-index="${index}" data-scan-id="${escapeHtml(scanId)}" data-loading="true" aria-live="polite">
            <div class="product-card-header">
                <span class="input-label">Продукт ${index + 1}</span>
                <span class="scan-loading-badge">AI</span>
            </div>
            <div class="scan-loading-content">
                <span class="scan-loading-spinner" aria-hidden="true"></span>
                <div>
                    <strong class="shimmer-text">Распознаю блюдо</strong>
                    <p>Заполню название, вес и КБЖУ после ответа AI</p>
                </div>
            </div>
        </div>
    `;
}

function addScanLoadingCard(scanId) {
    productsList.insertAdjacentHTML(
        'beforeend',
        createScanLoadingCard(scanId, getCurrentProductCount())
    );
    renumberProductCards();
    updateDraftLimitControls();
    productsList.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function removeScanLoadingCard(scanId) {
    const card = Array.from(productsList.querySelectorAll('.product-card-loading'))
        .find(item => item.dataset.scanId === scanId);

    if (!card) {
        return;
    }

    card.remove();
    renumberProductCards();
    updateDraftLimitControls();
    recalculateDraftTotal();
}

function renumberProductCards() {
    productsList.querySelectorAll('.product-card').forEach((card, index) => {
        const productIndex = index + 1;
        card.dataset.index = String(productIndex);

        const thumb = card.querySelector('.draft-product-thumb');
        if (thumb && !thumb.querySelector('img')) {
            thumb.textContent = String(productIndex);
        }
    });
}

// Source: app/js/features/meal-draft/products-draft.js
// Product draft data, limits and generated names

function collectDraftProducts() {
    return getDraftProductCards().filter(card => card.dataset.loading !== 'true').slice(0, MAX_PRODUCTS_PER_MEAL).map(card => {
        const calories = card.querySelector('.product-calories').value;
        const proteins = card.querySelector('.product-proteins').value;
        const fats = card.querySelector('.product-fats').value;
        const carbs = card.querySelector('.product-carbs').value;

        const baseWeight = parseInt(card.querySelector('.product-weight').value, 10) || 0;
        const portions = Math.max(1, parseInt(card.querySelector('.product-portions')?.value || '1', 10) || 1);

        return {
            clientId: card.dataset.productId,
            name: card.querySelector('.product-name').value.trim(),
            weight: baseWeight * portions,
            baseWeight,
            portions,
            processing: card.dataset.index === '0'
                ? ''
                : card.querySelector('.processing-select')?.value || '',
            scanId: card.dataset.scanId || '',
            kbju: {
                calories,
                proteins,
                fats,
                carbs
            }
        };
    });
}

function getDraftScanCount() {
    const products = !editorScreen.classList.contains('hidden')
        ? getDraftProductCards().map(card => ({ scanId: card.dataset.scanId || '' }))
        : Array.isArray(mealDraft.products) ? mealDraft.products : [];

    const scanIds = products
        .map(product => String(product.scanId || '').trim())
        .filter(Boolean);

    return new Set(scanIds).size;
}

function createScanBatchId() {
    return `scan-${Date.now()}-${nextScanBatchNumber++}`;
}

function getCurrentProductCount() {
    if (!editorScreen.classList.contains('hidden')) {
        return getDraftProductCards().length;
    }

    return Array.isArray(mealDraft.products) ? mealDraft.products.length : 0;
}

function showProductLimitAlert() {
    tg.showAlert(`В одном приеме можно добавить до ${MAX_PRODUCTS_PER_MEAL} продуктов`);
    haptic('error');
}

function productsMissingKbju(products) {
    return products.filter(product => ['calories', 'proteins', 'fats', 'carbs'].some(
        key => !String(product.kbju?.[key] || '').trim()
    ));
}

function productCardMissingKbju(card) {
    return [
        '.product-calories',
        '.product-proteins',
        '.product-fats',
        '.product-carbs'
    ].some(selector => !card.querySelector(selector).value.trim());
}

function fillMissingCardKbju(card, nutrients) {
    const fields = {
        calories: card.querySelector('.product-calories'),
        proteins: card.querySelector('.product-proteins'),
        fats: card.querySelector('.product-fats'),
        carbs: card.querySelector('.product-carbs')
    };

    Object.entries(fields).forEach(([key, input]) => {
        if (input.value.trim() !== '') {
            return;
        }

        const value = nutrients?.[key];
        input.value = value === undefined || value === null ? 0 : value;
    });
}

function setKbjuLoadingState(card, isLoading, mode = 'nutrition') {
    const button = card.querySelector('.kbju-autofill-button');
    const overlay = card.querySelector('.draft-product-loading-overlay, .draft-main-product-loading-overlay');
    const label = card.querySelector('.draft-product-loading-label');
    card.classList.toggle('product-card-kbju-loading', isLoading);
    card.setAttribute('aria-busy', String(isLoading));
    card.inert = isLoading;
    if (isLoading) {
        card.dataset.loadingMode = mode;
    } else {
        delete card.dataset.loadingMode;
    }

    if (overlay) {
        overlay.setAttribute('aria-hidden', String(!isLoading));
    }

    if (label) {
        label.textContent = mode === 'photo' ? 'AI анализирует фото' : 'AI заполняет КБЖУ';
    }

    if (button) {
        button.disabled = isLoading;
    }

    updateProductKbjuActionState(card);
}

function confirmAsync(message) {
    return new Promise(resolve => {
        if (typeof tg.showConfirm === 'function') {
            tg.showConfirm(message, confirmed => resolve(Boolean(confirmed)));
            return;
        }

        resolve(window.confirm(message));
    });
}

function syncDraftFromEditor() {
    if (editorScreen.classList.contains('hidden')) return;

    const products = collectDraftProducts();
    mealDraft.mealName = mealNameEditedByUser
        ? mealNameInput.value.trim()
        : buildGeneratedMealName(products);
    mealDraft.products = products.map(product => ({
        clientId: product.clientId,
        name: product.name,
        weight: product.baseWeight,
        portions: product.portions,
        processing: product.processing,
        calories: product.kbju.calories,
        proteins: product.kbju.proteins,
        fats: product.kbju.fats,
        carbs: product.kbju.carbs,
        scanId: product.scanId
    }));

    if (!mealNameEditedByUser) {
        mealNameInput.value = mealDraft.mealName;
    }
}

function isEmptyDraftProduct(product) {
    return !String(product?.name || '').trim()
        && !String(product?.calories || '').trim()
        && !String(product?.proteins || '').trim()
        && !String(product?.fats || '').trim()
        && !String(product?.carbs || '').trim();
}

function productBaseForAppend(products) {
    if (products.length === 1 && isEmptyDraftProduct(products[0])) {
        return [];
    }

    return products;
}

function limitDraftProducts(draft) {
    const products = Array.isArray(draft.products) ? draft.products : [];
    const limitedProducts = products.slice(0, MAX_PRODUCTS_PER_MEAL);

    if (products.length > MAX_PRODUCTS_PER_MEAL) {
        tg.showAlert(`AI нашел больше ${MAX_PRODUCTS_PER_MEAL} продуктов, лишние не добавлены`);
        haptic('error');
    }

    return {
        ...draft,
        products: limitedProducts.length > 0 ? limitedProducts : [createEmptyProduct()]
    };
}

function appendAnalyzedDraft(analyzedDraft) {
    syncDraftFromEditor();

    const currentProducts = productBaseForAppend(Array.isArray(mealDraft.products) ? mealDraft.products : []);
    const incomingProducts = Array.isArray(analyzedDraft.products) ? analyzedDraft.products : [];
    const remainingSlots = MAX_PRODUCTS_PER_MEAL - currentProducts.length;

    if (remainingSlots <= 0) {
        showProductLimitAlert();
        return;
    }

    const productsToAdd = incomingProducts.slice(0, remainingSlots);
    const nextProducts = currentProducts.concat(productsToAdd);

    mealDraft = {
        ...mealDraft,
        source: 'photo',
        mealName: mealDraft.mealName || analyzedDraft.mealName || '',
        products: nextProducts.length > 0 ? nextProducts : [createEmptyProduct()],
        draftImagePath: mealDraft.draftImagePath || analyzedDraft.draftImagePath || null
    };

    if (incomingProducts.length > productsToAdd.length) {
        tg.showAlert(`Добавлено ${productsToAdd.length} продуктов. Лимит приема: ${MAX_PRODUCTS_PER_MEAL}`);
        haptic('error');
    }
}

function updateDraftLimitControls() {
    const productCount = getCurrentProductCount();
    const scanCount = getDraftScanCount();
    const productsLimitReached = productCount >= MAX_PRODUCTS_PER_MEAL;
    const mainProductCard = mainProductContainer.querySelector('.product-card');
    const mainProductComplete = mainProductCard
        && productCardCanAutofillKbju(mainProductCard)
        && !productCardMissingKbju(mainProductCard);

    const aiBusy = isDraftAiBusy();
    btnAddProduct.disabled = productsLimitReached || aiBusy;
    const addProductTitle = btnAddProduct.querySelector('.draft-add-product-copy strong');
    const addProductHint = btnAddProduct.querySelector('.draft-add-product-copy small');
    const addProductIcon = btnAddProduct.querySelector('.draft-add-product-icon');
    if (addProductTitle) {
        addProductTitle.textContent = productsLimitReached ? 'Лимит продуктов' : 'Добавить продукт';
    }
    if (addProductHint) {
        addProductHint.textContent = productsLimitReached
            ? `В одном приеме доступно до ${MAX_PRODUCTS_PER_MEAL} продуктов`
            : 'Хлеб, овощи, соус или другую часть приема';
    }
    if (addProductIcon) {
        addProductIcon.textContent = productsLimitReached ? '✓' : '+';
    }
    draftProductsCount.textContent = `${Math.max(0, productCount - 1)} / ${MAX_PRODUCTS_PER_MEAL - 1}`;

    updateAllProductKbjuActionStates();

    btnSaveMeal.classList.toggle('is-analyzing', aiBusy && !isSavingMealDraft);

    if (!isSavingMealDraft) {
        btnSaveMeal.disabled = aiBusy || !mainProductComplete;
        btnSaveMeal.textContent = aiBusy
            ? 'Идет анализ...'
            : `Добавить в ${draftMealType.value || getMealTypeByTime()}`;
    }

    updateAnalyzePhotoButton();
}

function updateDraftSourceLabel() {
    if (isAnalyzingPhoto && !editorScreen.classList.contains('hidden')) {
        draftSourceLabel.textContent = 'AI распознает блюдо, можно дождаться результата прямо здесь';
        return;
    }

    const scanInfo = `AI-сканы: ${getDraftScanCount()}/${MAX_DRAFT_SCANS_PER_MEAL}`;
    const hasScannedProducts = getDraftScanCount() > 0;

    draftSourceLabel.textContent = mealDraft.source === 'photo' && hasScannedProducts
        ? `AI заполнил черновик, проверьте перед сохранением. ${scanInfo}`
        : `Заполните продукты вручную. ${scanInfo}`;
}

function getMealTypeByTime(date = new Date()) {
    const hour = date.getHours();

    if (hour >= 5 && hour < 11) {
        return 'Завтрак';
    }

    if (hour >= 11 && hour < 16) {
        return 'Обед';
    }

    if (hour >= 16 && hour < 22) {
        return 'Ужин';
    }

    return 'Перекус';
}

function buildGeneratedMealName(products) {
    const productNames = (Array.isArray(products) ? products : [])
        .map(product => String(product.name || '').trim())
        .filter(Boolean);
    const mealType = draftMealType?.value || getMealTypeByTime();
    const name = productNames.length > 0
        ? `${mealType}: ${productNames.join(', ')}`
        : mealType;

    return name.length > 120 ? `${name.slice(0, 117)}...` : name;
}

function updateAutoMealNameFromProducts() {
    if (mealNameEditedByUser || editorScreen.classList.contains('hidden')) return;

    mealNameInput.value = buildGeneratedMealName(collectDraftProducts());
}

function startAiPhotoSelection(intent, input) {
    if (isDraftAiBusy() || isSavingMealDraft) {
        tg.showAlert('Дождитесь завершения текущего AI-запроса');
        haptic('light');
        return;
    }

    if (intent === PHOTO_INTENTS.APPEND_SCAN) {
        syncDraftFromEditor();

        if (getDraftScanCount() >= MAX_DRAFT_SCANS_PER_MEAL) {
            tg.showAlert(`В одном приеме можно сделать до ${MAX_DRAFT_SCANS_PER_MEAL} AI-сканов`);
            haptic('error');
            return;
        }

        if (getCurrentProductCount() >= MAX_PRODUCTS_PER_MEAL) {
            showProductLimitAlert();
            return;
        }
    }

    photoIntent = intent;
    input.value = '';
    input.click();
}

// Source: app/js/features/meal-draft/products-interactions.js
// Product card events and additional photo analysis

function bindProductCardEvents() {
    getDraftProductCards().forEach(card => {
        if (card.dataset.loading === 'true') {
            return;
        }

        card.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', () => {
                recalculateDraftTotal();
                updateProductCardSummary(card);
                updateProductKbjuActionState(card);
                updateDraftLimitControls();
            });
        });

        card.querySelector('.processing-select')?.addEventListener('change', () => {
            recalculateDraftTotal();
            updateProductCardSummary(card);
            updateProductKbjuActionState(card);
            updateDraftLimitControls();
            haptic('light');
        });

        const deleteButton = card.querySelector('.product-delete');
        if (deleteButton) {
            deleteButton.onclick = () => {
            const productPhoto = additionalProductPhotos.get(card.dataset.productId);
            if (productPhoto?.url) URL.revokeObjectURL(productPhoto.url);
            additionalProductPhotos.delete(card.dataset.productId);
            card.remove();
            renumberProductCards();
            syncDraftFromEditor();
            recalculateDraftTotal();
            updateDraftLimitControls();
            updateDraftSourceLabel();
            haptic('light');
            };
        }

        const portionsInput = card.querySelector('.product-portions');
        const portionsOutput = card.querySelector('.draft-portions-value');
        const changePortions = delta => {
            if (!portionsInput || !portionsOutput) return;

            const nextValue = Math.min(20, Math.max(1, Number(portionsInput.value || 1) + delta));
            portionsInput.value = String(nextValue);
            portionsOutput.textContent = String(nextValue);
            recalculateDraftTotal();
            updateProductCardSummary(card);
            updateDraftLimitControls();
            haptic('light');
        };

        card.querySelector('.draft-portions-minus')?.addEventListener('click', () => changePortions(-1));
        card.querySelector('.draft-portions-plus')?.addEventListener('click', () => changePortions(1));
        card.querySelector('.draft-product-photo-input')?.addEventListener('change', event => {
            selectAdditionalProductPhoto(card, event.target.files?.[0]);
        });

        updateProductKbjuActionState(card);
        updateProductCardSummary(card);
    });
}

function updateProductCardSummary(card) {
    const name = card.querySelector('.product-name')?.value.trim() || 'Новый продукт';
    const weight = Number(card.querySelector('.product-weight')?.value || 0);
    const portions = Number(card.querySelector('.product-portions')?.value || 1);
    const calories = Number(card.querySelector('.product-calories')?.value || 0);
    const total = Math.round(calories * weight * portions / 100);

    const summaryName = card.querySelector('.draft-product-name-summary');
    const mainName = card.querySelector('.draft-main-product-heading h2');
    const summaryCalories = card.querySelector('.draft-product-card-total');
    const mainCalories = card.querySelector('#draft-total-calories');

    if (summaryName) summaryName.textContent = name;
    if (mainName) mainName.textContent = name;
    if (mainCalories) {
        mainCalories.textContent = String(total);
    } else if (summaryCalories) {
        summaryCalories.textContent = `${total} ккал`;
    }
}

function handleProductEditorClick(event) {
    const autofillButton = event.target.closest('.kbju-autofill-button');
    if (autofillButton) {
        const card = autofillButton.closest('.product-card');
        if (!card) return;

        event.preventDefault();
        const action = autofillButton.dataset.aiAction;

        if (action === 'photo') {
            if (card.dataset.index === '0') {
                analyzeSelectedPhoto();
            } else {
                analyzeAdditionalProductPhoto(card);
            }
            return;
        }

        if (action === 'select_photo') {
            card.querySelector('.draft-product-photo-input')?.click();
            return;
        }

        if (action === 'nutrition') {
            fillProductKbjuWithAi(card);
        }
        return;
    }

    const toggle = event.target.closest('.kbju-toggle');
    if (!toggle) return;

    const card = toggle.closest('.product-card');
    if (!card) return;

    event.preventDefault();
    toggleProductKbju(card);
}

mainProductContainer.addEventListener('click', handleProductEditorClick);
productsList.addEventListener('click', handleProductEditorClick);

function selectAdditionalProductPhoto(card, file) {
    if (!file || !validateImageFileBeforeUpload(file)) {
        return;
    }

    const previousPhoto = additionalProductPhotos.get(card.dataset.productId);
    if (previousPhoto?.url) URL.revokeObjectURL(previousPhoto.url);

    additionalProductPhotos.set(card.dataset.productId, {
        file,
        url: URL.createObjectURL(file),
        draftImagePath: null
    });
    const thumb = card.querySelector('.draft-product-thumb');
    if (thumb) {
        thumb.innerHTML = `<img src="${escapeHtml(additionalProductPhotos.get(card.dataset.productId).url)}" alt="">`;
    }
    updateProductKbjuActionState(card);
    haptic('light');
}

async function analyzeAdditionalProductPhoto(card) {
    const photo = additionalProductPhotos.get(card.dataset.productId);
    if (!photo?.file || card.classList.contains('product-card-kbju-loading')) {
        return;
    }

    if (isDraftAiBusy() || isSavingMealDraft) {
        tg.showAlert('Дождитесь завершения текущего AI-запроса');
        haptic('light');
        return;
    }

    isAnalyzingAdditionalProduct = true;
    setKbjuLoadingState(card, true, 'photo');
    updateDraftLimitControls();
    const formData = new FormData();
    formData.append('photo', photo.file);

    try {
        const result = await apiRequestJson('/api/analyze-draft', {
            method: 'POST',
            body: formData,
            timeoutMs: API_TIMEOUT.AI
        });
        const product = result?.data?.products?.[0];

        if (!product) {
            throw new ApiError('AI вернул некорректные данные блюда', {
                code: 'invalid_ai_response',
                data: result
            });
        }

        photo.draftImagePath = result.data?.draft_image_path || null;

        card.querySelector('.product-name').value = product.name || '';
        card.querySelector('.product-weight').value = Number(product.weight) || 100;
        card.querySelector('.product-calories').value = product.calories ?? '';
        card.querySelector('.product-proteins').value = product.proteins ?? '';
        card.querySelector('.product-fats').value = product.fats ?? '';
        card.querySelector('.product-carbs').value = product.carbs ?? '';
        const processingSelect = card.querySelector('.processing-select');
        if (processingSelect) {
            processingSelect.value = product.processing || '';
        }
        card.dataset.scanId = createScanBatchId();
        setProductKbjuExpanded(card, true);
        updateProductCardSummary(card);
        syncDraftFromEditor();
        recalculateDraftTotal();
        haptic('success');
    } catch (error) {
        tg.showAlert(error instanceof ApiError ? getAnalyzePhotoErrorMessage(error) : 'Ошибка при анализе фото');
        haptic('error');
    } finally {
        setKbjuLoadingState(card, false);
        isAnalyzingAdditionalProduct = false;
        updateDraftLimitControls();
    }
}

// Source: app/js/features/meal-draft/ai.js
// Meal draft AI actions

function startAttachmentPhotoSelection() {
    photoIntent = PHOTO_INTENTS.ATTACH_ONLY;
    attachmentPhotoInput.value = '';
    attachmentPhotoInput.click();
}

async function fillMissingKbjuWithAi() {
    if (isDraftAiBusy() || isSavingMealDraft) {
        tg.showAlert('Дождитесь завершения текущего AI-запроса');
        haptic('light');
        return;
    }

    const cards = getDraftProductCards();
    const cardsToFill = cards.filter(card => {
        const name = card.querySelector('.product-name').value.trim();
        return name !== '' && productCardMissingKbju(card);
    });

    if (cardsToFill.length === 0) {
        tg.showAlert('Нет продуктов с названием и пустыми полями КБЖУ');
        haptic('light');
        return;
    }

    isFillingKbju = true;
    const previousDraftSourceText = draftSourceLabel.textContent;
    draftSourceLabel.textContent = 'AI заполняет недостающие калории и БЖУ';
    updateDraftLimitControls();

    try {
        for (const card of cardsToFill) {
            setKbjuLoadingState(card, true);
            const productName = card.querySelector('.product-name').value.trim();
            const processing = card.querySelector('.processing-select')?.value || '';
            const result = await apiRequestJson('/api/product-nutrition', {
                method: 'POST',
                json: { product_name: productName, processing },
                timeoutMs: API_TIMEOUT.AI
            });

            fillMissingCardKbju(card, result.data || {});
            setKbjuLoadingState(card, false);
        }

        syncDraftFromEditor();
        recalculateDraftTotal();
        haptic('success');
    } catch (error) {
        tg.showAlert(error?.message || 'Ошибка при заполнении КБЖУ');
        haptic('error');
    } finally {
        cardsToFill.forEach(card => setKbjuLoadingState(card, false));
        isFillingKbju = false;
        draftSourceLabel.textContent = previousDraftSourceText;
        updateDraftLimitControls();
    }
}

async function fillProductKbjuWithAi(card) {
    if (isDraftAiBusy() || isSavingMealDraft) {
        tg.showAlert('Дождитесь завершения текущего AI-запроса');
        haptic('light');
        return;
    }

    if (!productCardCanAutofillKbju(card)) {
        tg.showAlert('Сначала укажите название и вес');
        haptic('error');
        updateProductKbjuActionState(card);
        return;
    }

    isFillingKbju = true;
    const previousDraftSourceText = draftSourceLabel.textContent;
    draftSourceLabel.textContent = 'AI заполняет КБЖУ продукта';
    updateDraftLimitControls();
    setKbjuLoadingState(card, true);

    try {
        const productName = card.querySelector('.product-name').value.trim();
        const processing = card.querySelector('.processing-select')?.value || '';
        const result = await apiRequestJson('/api/product-nutrition', {
            method: 'POST',
            json: { product_name: productName, processing },
            timeoutMs: API_TIMEOUT.AI
        });

        fillMissingCardKbju(card, result.data || {});
        setProductKbjuExpanded(card, true);
        syncDraftFromEditor();
        recalculateDraftTotal();
        haptic('success');
    } catch (error) {
        tg.showAlert(error?.message || 'Ошибка при заполнении КБЖУ');
        haptic('error');
    } finally {
        setKbjuLoadingState(card, false);
        isFillingKbju = false;
        draftSourceLabel.textContent = previousDraftSourceText;
        updateDraftLimitControls();
    }
}

// Source: app/js/features/meal-draft/save.js
// Meal totals, persistence and image uploads

function recalculateDraftTotal() {
    const totals = collectDraftProducts().reduce((result, product) => {
        const caloriesPer100g = parseFloat(product.kbju.calories) || 0;
        const proteinsPer100g = parseFloat(product.kbju.proteins) || 0;
        const fatsPer100g = parseFloat(product.kbju.fats) || 0;
        const carbsPer100g = parseFloat(product.kbju.carbs) || 0;
        const weight = parseFloat(product.weight) || 0;
        const coefficient = PROCESSING_OPTIONS.find(option => option.value === product.processing)?.coefficient ?? 1;
        const ratio = coefficient * weight / 100;

        result.calories += caloriesPer100g * ratio;
        result.proteins += proteinsPer100g * ratio;
        result.fats += fatsPer100g * ratio;
        result.carbs += carbsPer100g * ratio;
        return result;
    }, { calories: 0, proteins: 0, fats: 0, carbs: 0 });

    const mainProduct = collectDraftProducts()[0];
    const mainWeight = Number(mainProduct?.weight || 0);
    const mainRatio = mainWeight / 100;
    const mainCalories = Number(mainProduct?.kbju.calories || 0) * mainRatio;
    const mainProteins = Number(mainProduct?.kbju.proteins || 0) * mainRatio;
    const mainFats = Number(mainProduct?.kbju.fats || 0) * mainRatio;
    const mainCarbs = Number(mainProduct?.kbju.carbs || 0) * mainRatio;

    const summaryElements = {
        total: document.getElementById('draft-total-calories'),
        calories: document.getElementById('draft-summary-calories'),
        proteins: document.getElementById('draft-summary-protein'),
        fats: document.getElementById('draft-summary-fat'),
        carbs: document.getElementById('draft-summary-carbs')
    };

    if (summaryElements.total) summaryElements.total.textContent = Math.round(mainCalories);
    if (summaryElements.calories) summaryElements.calories.textContent = Math.round(mainCalories);
    if (summaryElements.proteins) summaryElements.proteins.textContent = formatMacro(mainProteins);
    if (summaryElements.fats) summaryElements.fats.textContent = formatMacro(mainFats);
    if (summaryElements.carbs) summaryElements.carbs.textContent = formatMacro(mainCarbs);
    updateAutoMealNameFromProducts();
}

async function saveMealDraft() {
    if (isSavingMealDraft) {
        return;
    }

    if (isDraftAiBusy()) {
        tg.showAlert('Дождитесь завершения текущего AI-запроса');
        haptic('error');
        return;
    }

    const products = collectDraftProducts();
    const mealName = mealNameInput.value.trim() || buildGeneratedMealName(products);

    if (products.some(product => !product.name || product.weight <= 0)) {
        tg.showAlert('Заполните название и вес каждого продукта');
        haptic('error');
        return;
    }

    if (products.length === 0) {
        tg.showAlert('Добавьте хотя бы один продукт');
        haptic('error');
        return;
    }

    const productsWithMissingKbju = productsMissingKbju(products);
    if (productsWithMissingKbju.length > 0) {
        const confirmed = await confirmAsync(
            `У ${productsWithMissingKbju.length} продукт(ов) не полностью заполнены КБЖУ. Недостающие значения сохранятся как 0. Продолжить?`
        );

        if (!confirmed) {
            haptic('light');
            return;
        }
    }

    isSavingMealDraft = true;
    btnSaveMeal.disabled = true;
    const previousSaveButtonText = btnSaveMeal.textContent;
    const previousDraftSourceText = draftSourceLabel.textContent;
    btnSaveMeal.textContent = 'Сохраняю...';
    draftSourceLabel.textContent = 'Сохраняю прием пищи';
    tg.MainButton.showProgress(false);

    try {
        await ensureMainDraftImageUploaded();
        await ensureAdditionalDraftImagesUploaded(products);

        const result = await apiRequestJson('/api/save-meal', {
            method: 'POST',
            json: {
                meal_name: mealName || 'Прием пищи',
                products: products.map(({ baseWeight, portions, clientId, ...product }) => product),
                draft_image_path: mealDraft.draftImagePath,
                split_products: true
            }
        });

        refreshDailyNutritionInsight({ invalidate: true });
        closeMealSheet();
        await refreshMealHistory();
        await loadProgress();
        await refreshHistoryCalendar();
        await refreshSummary();
        haptic('success');
        tg.showAlert(`Добавлено: ${mealName}, ${result.meal?.calories || 0} ккал`);
    } catch (error) {
        tg.showAlert(error?.message || 'Ошибка соединения');
        haptic('error');
    } finally {
        isSavingMealDraft = false;
        btnSaveMeal.disabled = false;
        btnSaveMeal.textContent = previousSaveButtonText;
        draftSourceLabel.textContent = previousDraftSourceText;
        tg.MainButton.hideProgress();
    }
}

async function ensureAdditionalDraftImagesUploaded(products) {
    for (const product of products.slice(1)) {
        const photo = additionalProductPhotos.get(product.clientId);
        if (!photo?.file) {
            continue;
        }

        if (!photo.draftImagePath) {
            const formData = new FormData();
            formData.append('photo', photo.file);

            const result = await apiRequestJson('/api/upload-draft-image', {
                method: 'POST',
                body: formData,
                timeoutMs: API_TIMEOUT.UPLOAD
            });
            const imagePath = result?.data?.draft_image_path;

            if (!imagePath) {
                throw new Error(`Не удалось сохранить фото продукта «${product.name}»`);
            }

            photo.draftImagePath = imagePath;
        }

        product.draft_image_path = photo.draftImagePath;
    }
}

async function ensureMainDraftImageUploaded() {
    if (mealDraft.draftImagePath || !selectedPhotoFile) {
        return;
    }

    const formData = new FormData();
    formData.append('photo', selectedPhotoFile);

    const result = await apiRequestJson('/api/upload-draft-image', {
        method: 'POST',
        body: formData,
        timeoutMs: API_TIMEOUT.UPLOAD
    });
    const imagePath = result?.data?.draft_image_path;

    if (!imagePath) {
        throw new Error('Не удалось сохранить главное фото');
    }

    mealDraft.draftImagePath = imagePath;
}

// Source: app/js/features/meal-draft/bindings.js
// Meal draft event bindings

document.getElementById('btn-add-food').onclick = openMealSheet;
document.querySelector('.sheet-overlay').onclick = closeMealSheet;
btnManualEntry.onclick = startManualDraft;
btnCancelDraft.onclick = closeMealSheet;
btnAnalyzePhoto.onclick = analyzeSelectedPhoto;
btnSaveMeal.onclick = saveMealDraft;
btnAddManualPhoto.onclick = startAttachmentPhotoSelection;
btnDraftPhotoSelect.onclick = () => startAiPhotoSelection(PHOTO_INTENTS.ATTACH_ONLY, galleryInput);
btnRemoveMainPhoto.onclick = removeMainDraftPhoto;
draftMealType.addEventListener('change', () => {
    mealNameEditedByUser = false;
    updateAutoMealNameFromProducts();
    updateDraftLimitControls();
    haptic('light');
});
mealNameInput.addEventListener('input', () => {
    mealNameEditedByUser = true;
    mealDraft.mealName = mealNameInput.value.trim();
});
btnAddProduct.onclick = () => {
    if (getCurrentProductCount() >= MAX_PRODUCTS_PER_MEAL) {
        showProductLimitAlert();
        updateDraftLimitControls();
        return;
    }

    const product = createEmptyProduct();
    productsList.insertAdjacentHTML('beforeend', createProductCard(product, getCurrentProductCount()));
    renumberProductCards();
    bindProductCardEvents();
    recalculateDraftTotal();
    updateDraftLimitControls();
    haptic('light');
};

btnCamera.onclick = () => startAiPhotoSelection(PHOTO_INTENTS.AI_SCAN, cameraInput);
btnGallery.onclick = () => startAiPhotoSelection(PHOTO_INTENTS.AI_SCAN, galleryInput);
btnChangePhoto.onclick = () => {
    const nextIntent = [PHOTO_INTENTS.APPEND_SCAN, PHOTO_INTENTS.ATTACH_ONLY].includes(photoIntent)
        ? photoIntent
        : PHOTO_INTENTS.AI_SCAN;

    startAiPhotoSelection(nextIntent, galleryInput);
};
cameraInput.onchange = event => handleAiPhotoSelected(event.target.files[0]);
galleryInput.onchange = event => handleAiPhotoSelected(event.target.files[0]);
attachmentPhotoInput.onchange = event => uploadAttachmentPhoto(event.target.files[0]);

// Source: app/js/features/profile/profile.js
// Profile display, settings and persistence

function updateUserUI() {
    if (!userData) return;

    document.getElementById('user-body-params').innerText = formatBodyParams(userData);

    const displayName = user?.first_name || user?.username || 'Пользователь';
    const avatarLetter = Array.from(displayName.trim())[0]?.toUpperCase() || 'П';

    setElementText('profile-user-name', displayName);
    setElementText('profile-avatar', avatarLetter);
    setElementText('profile-user-meta', formatProfileUserMeta(userData));
    document.getElementById('settings-age').innerText = userData.age || '-';
    document.getElementById('settings-height').innerText = userData.height ? `${userData.height} см` : '-';
    document.getElementById('settings-weight').innerText = userData.weight ? `${userData.weight} кг` : '-';
    document.getElementById('settings-gender').innerText = userData.gender === 'male' ? 'Мужчина' : 'Женщина';
    document.getElementById('settings-goal').innerText = userData.daily_goal ? `${userData.daily_goal} ккал` : '-';
    setElementText('settings-target', formatGoalLabel(userData.goal));
    setElementText('settings-activity', formatActivityLabel(userData.activity_level));
    fillSettingsForm();

    loadProgress();
}

let aiUsageLoadId = 0;

async function loadAiUsage() {
    const loadId = ++aiUsageLoadId;
    setAiUsageLoading();

    try {
        const result = await apiRequestJson('/api/ai-usage');

        if (loadId !== aiUsageLoadId) {
            return;
        }

        renderAiUsage(result.data);
    } catch (error) {
        if (loadId === aiUsageLoadId) {
            renderAiUsageError(error?.message);
        }
    }
}

function renderAiUsage(data) {
    const general = normalizeAiQuota(data?.general);
    const insights = normalizeAiQuota(data?.insights);

    if (!general || !insights) {
        renderAiUsageError('Сервер вернул некорректные данные о лимитах');
        return;
    }

    setAiUsageState('ready');
    setElementText('profile-ai-usage-badge', 'актуально');
    setElementText('profile-ai-general-remaining', `${general.remaining} из ${general.limit}`);
    setElementText('profile-ai-insights-remaining', `${insights.remaining} из ${insights.limit}`);
    setElementText(
        'profile-ai-usage-description',
        `Основной лимит обновится через ${formatAiQuotaReset(general.resetsInSeconds)}.`
    );
}

function normalizeAiQuota(value) {
    const limit = Number(value?.limit);
    const remaining = Number(value?.remaining);
    const resetsInSeconds = Number(value?.resets_in_seconds);

    if (![limit, remaining, resetsInSeconds].every(Number.isFinite) || limit < 0) {
        return null;
    }

    return {
        limit: Math.round(limit),
        remaining: Math.max(0, Math.min(Math.round(remaining), Math.round(limit))),
        resetsInSeconds: Math.max(0, Math.round(resetsInSeconds))
    };
}

function setAiUsageLoading() {
    setAiUsageState('loading');
    setElementText('profile-ai-usage-badge', 'загрузка');
    setElementText('profile-ai-usage-description', 'Загружаем актуальные лимиты...');
    setElementText('profile-ai-general-remaining', '—');
    setElementText('profile-ai-insights-remaining', '—');
}

function renderAiUsageError(message = '') {
    setAiUsageState('error');
    setElementText('profile-ai-usage-badge', 'ошибка');
    setElementText('profile-ai-usage-description', message || 'Не удалось загрузить AI-лимиты');
    setElementText('profile-ai-general-remaining', '—');
    setElementText('profile-ai-insights-remaining', '—');
}

function setAiUsageState(state) {
    document.getElementById('profile-ai-usage-card')?.setAttribute('data-state', state);
    document.getElementById('btn-retry-ai-usage')?.classList.toggle('hidden', state !== 'error');
}

function formatAiQuotaReset(seconds) {
    if (seconds < 60) {
        return 'меньше минуты';
    }

    const totalMinutes = Math.ceil(seconds / 60);
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    if (hours === 0) {
        return `${minutes} мин`;
    }

    return minutes > 0 ? `${hours} ч ${minutes} мин` : `${hours} ч`;
}

document.getElementById('btn-retry-ai-usage')?.addEventListener('click', loadAiUsage);

function formatProfileUserMeta(data) {
    const age = data.age ? `${data.age} лет` : null;
    const gender = data.gender === 'male' ? 'мужчина' : data.gender === 'female' ? 'женщина' : null;

    return [age, gender].filter(Boolean).join(' · ') || 'Параметры не указаны';
}

function formatBodyParams(data) {
    const age = data.age ? `${data.age} лет` : null;
    const height = data.height ? `${data.height} см` : null;
    const weight = data.weight ? `${data.weight} кг` : null;
    const gender = data.gender === 'male' ? 'мужчина' : data.gender === 'female' ? 'женщина' : null;

    return [age, height, weight, gender].filter(Boolean).join(' · ') || 'Параметры не указаны';
}

function fillSettingsForm() {
    if (!userData) return;

    document.getElementById('settings-input-age').value = userData.age || '';
    document.getElementById('settings-input-height').value = userData.height || '';
    document.getElementById('settings-input-weight').value = userData.weight || '';
    document.getElementById('settings-input-gender').value = userData.gender || 'male';

    const activityInput = document.querySelector(`input[name="settings_activity_level"][value="${userData.activity_level || 'medium'}"]`);
    if (activityInput) activityInput.checked = true;

    const goalInput = document.querySelector(`input[name="settings_goal"][value="${userData.goal || 'maintenance'}"]`);
    if (goalInput) goalInput.checked = true;
}

function collectPersonalFormData() {
    return {
        age: document.getElementById('settings-input-age').value,
        height: document.getElementById('settings-input-height').value,
        weight: document.getElementById('settings-input-weight').value,
        gender: document.getElementById('settings-input-gender').value,
        activity_level: document.querySelector('input[name="settings_activity_level"]:checked')?.value || 'medium'
    };
}

function isProfileFormDirty() {
    if (!userData) return false;

    const formData = collectPersonalFormData();

    return Number(formData.age) !== Number(userData.age)
        || Number(formData.height) !== Number(userData.height)
        || Number(formData.weight) !== Number(userData.weight)
        || formData.gender !== userData.gender
        || formData.activity_level !== (userData.activity_level || 'medium');
}

function getSelectedGoal() {
    return document.querySelector('input[name="settings_goal"]:checked')?.value || 'maintenance';
}

function isGoalFormDirty() {
    return Boolean(userData) && getSelectedGoal() !== (userData.goal || 'maintenance');
}

function confirmTelegram(message) {
    return new Promise(resolve => {
        if (typeof tg.showConfirm === 'function') {
            tg.showConfirm(message, confirmed => resolve(Boolean(confirmed)));
            return;
        }

        resolve(window.confirm(message));
    });
}


const deleteProfileButton = document.getElementById('btn-delete-profile');

deleteProfileButton.onclick = async () => {
    const confirmed = await confirmTelegram('Вы уверены, что хотите удалить профиль? Это действие нельзя отменить.');

    if (!confirmed) {
        return;
    }

    deleteProfileButton.disabled = true;

    try {
        await apiRequestJson('/api/delete-profile', {
            method: 'POST',
            json: {}
        });

        tg.showAlert('Профиль успешно удален');
        userData = null;
        showScreen('welcome');
    } catch (error) {
        tg.showAlert(error?.message || 'Ошибка соединения с сервером');
    } finally {
        deleteProfileButton.disabled = false;
    }
};

document.getElementById('btn-edit-profile').onclick = () => {
    fillSettingsForm();
    showScreen('profileEdit');
};

document.getElementById('btn-edit-goal')?.addEventListener('click', () => {
    fillSettingsForm();
    showScreen('goalEdit');
});

const profileSettingsCard = document.querySelector('#screen-settings .profile-settings-card');
const profileSettingsToggle = document.getElementById('profile-settings-toggle');
const profileSettingsPanel = document.getElementById('profile-settings-panel');
const profileThemeSelect = document.getElementById('profile-theme-select');

profileSettingsToggle?.addEventListener('click', () => {
    const isOpen = !profileSettingsCard.classList.contains('is-open');

    profileSettingsCard.classList.toggle('is-open', isOpen);
    profileSettingsToggle.setAttribute('aria-expanded', String(isOpen));
    profileSettingsPanel.style.maxHeight = isOpen ? `${profileSettingsPanel.scrollHeight}px` : '0px';
});

if (profileThemeSelect) {
    profileThemeSelect.value = window.appTheme?.get?.() || 'system';
    profileThemeSelect.addEventListener('change', () => {
        window.appTheme?.set?.(profileThemeSelect.value);
    });
}

document.getElementById('btn-profile-edit-back').onclick = async () => {
    if (!isProfileFormDirty()) {
        showScreen('settings');
        return;
    }

    const shouldSave = await confirmTelegram('Сохранить изменения профиля?');
    if (shouldSave) {
        await saveProfileChanges();
        return;
    }

    fillSettingsForm();
    showScreen('settings');
};

document.getElementById('btn-goal-edit-back').onclick = async () => {
    if (!isGoalFormDirty()) {
        showScreen('settings');
        return;
    }

    const shouldSave = await confirmTelegram('Сохранить новую цель?');
    if (shouldSave) {
        await saveGoalChanges();
        return;
    }

    fillSettingsForm();
    showScreen('settings');
};

document.getElementById('settings-profile-form').onsubmit = async event => {
    event.preventDefault();
    await saveProfileChanges();
};

document.getElementById('settings-goal-form').onsubmit = async event => {
    event.preventDefault();
    await saveGoalChanges();
};

async function saveProfileChanges() {
    const formData = collectPersonalFormData();

    if (!formData.age || !formData.height || !formData.weight || !formData.gender) {
        tg.showAlert('Заполните возраст, рост, вес и пол');
        return false;
    }

    return saveProfilePayload({
        age: parseInt(formData.age, 10),
        height: parseInt(formData.height, 10),
        weight: parseFloat(formData.weight),
        gender: formData.gender,
        activity_level: formData.activity_level,
        goal: userData.goal || 'maintenance'
    }, document.getElementById('btn-save-profile'), 'Сохранить данные');
}

async function saveGoalChanges() {
    return saveProfilePayload({
        age: Number(userData.age),
        height: Number(userData.height),
        weight: Number(userData.weight),
        gender: userData.gender,
        activity_level: userData.activity_level || 'medium',
        goal: getSelectedGoal()
    }, document.getElementById('btn-save-goal'), 'Сохранить цель');
}

async function saveProfilePayload(payload, saveButton, defaultButtonText) {
    saveButton.disabled = true;
    saveButton.textContent = 'Сохраняю...';

    try {
        const result = await apiRequestJson('/api/profile', {
            method: 'POST',
            json: payload
        });

        userData = {
            registered: true,
            daily_goal: result.daily_goal,
            age: result.age,
            height: result.height,
            weight: result.weight,
            gender: result.gender,
            activity_level: result.activity_level,
            goal: result.goal
        };
        updateUserUI();
        await loadProgress();
        refreshDailyNutritionInsight();
        showScreen('settings');
        tg.showAlert(`Изменения сохранены. Новая норма: ${result.daily_goal} ккал`);
        return true;
    } catch (error) {
        const validationErrors = error instanceof ApiError ? error.data?.errors : null;
        tg.showAlert(
            validationErrors
                ? Object.values(validationErrors).join('\n')
                : (error?.message || 'Ошибка соединения с сервером')
        );
        return false;
    } finally {
        saveButton.disabled = false;
        saveButton.textContent = defaultButtonText;
    }
}

// Source: app/js/features/onboarding/onboarding.js
// Registration flow and validation

document.getElementById('btn-start').onclick = () => {
    startRegisterFlow();
};

const registerSteps = {
    1: document.getElementById('register-step-1'),
    2: document.getElementById('register-step-2'),
    3: document.getElementById('register-step-3')
};

const registerStepIndicators = document.querySelectorAll('[data-register-step-indicator]');
const registerGenderButtons = Array.from(document.querySelectorAll('[data-register-gender]'));
const registerGenderControl = registerGenderButtons[0]?.closest('.register-gender-control');
const btnNext1 = document.getElementById('btn-next-1');
const btnNext2 = document.getElementById('btn-next-2');
const registerSaveButton = document.getElementById('btn-save');
const registerSaveLabel = registerSaveButton.querySelector('.register-button-label');
const registerActivityInputs = Array.from(document.querySelectorAll('input[name="activity_level"]'));
const registerGoalInputs = Array.from(document.querySelectorAll('input[name="goal"]'));
let activeRegisterStep = 1;
let isRegisterStepTransitioning = false;

function setRegisterGender(value) {
    const genderInput = document.getElementById('gender');

    genderInput.value = value;

    if (registerGenderControl) {
        const activeGenderIndex = registerGenderButtons.findIndex(button => button.dataset.registerGender === value);

        if (activeGenderIndex >= 0) {
            registerGenderControl.style.setProperty('--active-gender-index', activeGenderIndex);
        }

        registerGenderControl.dataset.activeGender = value;
    }

    registerGenderButtons.forEach(button => {
        const isActive = button.dataset.registerGender === value;

        button.classList.toggle('active', isActive);
        button.setAttribute('aria-pressed', String(isActive));
    });
    genderInput.dispatchEvent(new Event('change', { bubbles: true }));
}

registerGenderButtons.forEach(button => {
    button.addEventListener('click', () => {
        setRegisterGender(button.dataset.registerGender);
    });
});

function startRegisterFlow() {
    const welcomeScreen = document.getElementById('screen-welcome');
    const registerScreen = document.getElementById('screen-register');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (document.body.classList.contains('register-transitioning')) {
        return;
    }

    showRegisterStep(1, { instant: true });
    updateRegisterFirstStepState();

    if (reduceMotion) {
        showScreen('register');
        return;
    }

    document.body.classList.add('register-transitioning');
    registerScreen.classList.remove('hidden');
    welcomeScreen.classList.add('register-swipe-out');
    registerScreen.classList.add('register-swipe-in');
    updateTabBar('register');

    window.setTimeout(() => {
        welcomeScreen.classList.add('hidden');
        welcomeScreen.classList.remove('register-swipe-out');
        registerScreen.classList.remove('register-swipe-in');
        document.body.classList.remove('register-transitioning');
    }, 680);
}

function showRegisterStep(step, options = {}) {
    const nextStep = parseInt(step, 10);
    const nextEl = registerSteps[nextStep];
    const currentEl = registerSteps[activeRegisterStep];
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!nextEl || isRegisterStepTransitioning) {
        return;
    }

    if (options.instant || reduceMotion || !currentEl || currentEl === nextEl) {
        Object.values(registerSteps).forEach(el => {
            clearRegisterStepClasses(el);
            el.classList.add('hidden');
        });

        nextEl.classList.remove('hidden');
        activeRegisterStep = nextStep;
        updateRegisterStepper(nextStep);
        return;
    }

    isRegisterStepTransitioning = true;
    const direction = nextStep > activeRegisterStep ? 'forward' : 'back';

    clearRegisterStepClasses(currentEl);
    clearRegisterStepClasses(nextEl);
    nextEl.classList.remove('hidden');

    currentEl.classList.add('is-step-leaving', `is-step-leaving-${direction}`);
    nextEl.classList.add('is-step-entering', `is-step-entering-${direction}`);
    updateRegisterStepper(nextStep);

    window.setTimeout(() => {
        currentEl.classList.add('hidden');
        clearRegisterStepClasses(currentEl);
        clearRegisterStepClasses(nextEl);
        activeRegisterStep = nextStep;
        isRegisterStepTransitioning = false;
    }, 360);
}

function clearRegisterStepClasses(el) {
    el.classList.remove(
        'is-step-entering',
        'is-step-leaving',
        'is-step-entering-forward',
        'is-step-leaving-forward',
        'is-step-entering-back',
        'is-step-leaving-back'
    );
}

function updateRegisterStepper(activeStep) {
    registerStepIndicators.forEach(indicator => {
        const step = parseInt(indicator.dataset.registerStepIndicator, 10);
        const isActive = step === activeStep;
        const isCompleted = step < activeStep;

        indicator.classList.toggle('active', isActive);
        indicator.classList.toggle('completed', isCompleted);
        if (isActive) {
            indicator.setAttribute('aria-current', 'step');
        } else {
            indicator.removeAttribute('aria-current');
        }
    });
}

const registerBodyRules = [
    { field: document.getElementById('age'), errorId: 'register-age-error', label: 'Возраст', integer: true },
    { field: document.getElementById('height'), errorId: 'register-height-error', label: 'Рост', integer: true },
    { field: document.getElementById('weight'), errorId: 'register-weight-error', label: 'Вес', integer: false }
];

function validateRegisterNumber(rule, showError = false) {
    const rawValue = String(rule.field.value || '').trim();
    const value = Number(rawValue);
    const min = Number(rule.field.min);
    const max = Number(rule.field.max);
    let error = '';

    if (rawValue === '') {
        error = `${rule.label}: заполните поле`;
    } else if (!Number.isFinite(value)) {
        error = `${rule.label}: введите число`;
    } else if (rule.integer && !Number.isInteger(value)) {
        error = `${rule.label}: укажите целое число`;
    } else if (value < min || value > max) {
        error = `${rule.label}: допустимо от ${min} до ${max}`;
    }

    if (showError || rule.field.dataset.touched === 'true' || error === '') {
        renderRegisterFieldError(rule.field, rule.errorId, error);
    }

    return error === '';
}

function validateRegisterGender(showError = false) {
    const field = document.getElementById('gender');
    const isValid = ['male', 'female'].includes(field.value);
    const error = isValid ? '' : 'Выберите пол';

    if (showError || field.dataset.touched === 'true' || isValid) {
        renderRegisterFieldError(field, 'register-gender-error', error);
    }

    registerGenderControl?.classList.toggle('has-error', !isValid && (showError || field.dataset.touched === 'true'));
    registerGenderControl?.setAttribute('aria-invalid', String(!isValid && (showError || field.dataset.touched === 'true')));

    return isValid;
}

function validateRegisterBodyStep({ showErrors = false } = {}) {
    const fieldResults = registerBodyRules.map(rule => validateRegisterNumber(rule, showErrors));
    const genderValid = validateRegisterGender(showErrors);
    const firstInvalidRule = registerBodyRules.find((rule, index) => !fieldResults[index]);

    return {
        valid: fieldResults.every(Boolean) && genderValid,
        firstInvalid: firstInvalidRule?.field || (!genderValid ? document.getElementById('gender') : null)
    };
}

function validateRegisterChoice(inputs, allowedValues, groupId, errorId, message, showError = false) {
    const selected = inputs.find(input => input.checked);
    const isValid = Boolean(selected && allowedValues.includes(selected.value));
    const group = document.getElementById(groupId);
    const errorElement = document.getElementById(errorId);
    const shouldShowError = !isValid && showError;

    group?.classList.toggle('has-error', shouldShowError);
    group?.setAttribute('aria-invalid', String(shouldShowError));
    if (errorElement) {
        errorElement.textContent = shouldShowError ? message : '';
        errorElement.classList.toggle('hidden', !shouldShowError);
    }

    return { valid: isValid, firstInvalid: inputs[0] || null };
}

function validateRegisterActivityStep(options = {}) {
    return validateRegisterChoice(
        registerActivityInputs,
        ['minimal', 'low', 'medium', 'high', 'extra'],
        'register-activity-options',
        'register-activity-error',
        'Выберите уровень активности',
        options.showErrors === true
    );
}

function validateRegisterGoalStep(options = {}) {
    return validateRegisterChoice(
        registerGoalInputs,
        ['deficit', 'maintenance', 'surplus'],
        'register-goal-options',
        'register-goal-error',
        'Выберите цель',
        options.showErrors === true
    );
}

function renderRegisterFieldError(field, errorId, message) {
    const errorElement = document.getElementById(errorId);
    const hasError = message !== '';

    field.setAttribute('aria-invalid', String(hasError));
    field.closest('.form-group')?.classList.toggle('has-error', hasError);
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.classList.toggle('hidden', !hasError);
    }
}

function focusRegisterInvalidField(field) {
    if (!field) return;

    if (field.type === 'hidden') {
        registerGenderButtons[0]?.focus();
        return;
    }

    field.focus();
}

function updateRegisterFirstStepState() {
    btnNext1.disabled = !validateRegisterBodyStep().valid;
}

function updateRegisterActivityStepState() {
    btnNext2.disabled = !validateRegisterActivityStep().valid;
}

function updateRegisterGoalStepState() {
    registerSaveButton.disabled = !validateRegisterGoalStep().valid;
}

registerBodyRules.forEach(rule => {
    rule.field.addEventListener('blur', () => {
        rule.field.dataset.touched = 'true';
        validateRegisterNumber(rule, true);
    });
    rule.field.addEventListener('input', updateRegisterFirstStepState);
});

document.getElementById('gender').addEventListener('change', event => {
    event.currentTarget.dataset.touched = 'true';
    updateRegisterFirstStepState();
});

registerActivityInputs.forEach(input => input.addEventListener('change', () => {
    validateRegisterActivityStep({ showErrors: true });
    updateRegisterActivityStepState();
}));

registerGoalInputs.forEach(input => input.addEventListener('change', () => {
    validateRegisterGoalStep({ showErrors: true });
    updateRegisterGoalStepState();
}));

btnNext1.onclick = () => {
    const validation = validateRegisterBodyStep({ showErrors: true });
    if (!validation.valid) {
        focusRegisterInvalidField(validation.firstInvalid);
        return;
    }

    showRegisterStep(2);
};

btnNext2.onclick = () => {
    const validation = validateRegisterActivityStep({ showErrors: true });
    if (!validation.valid) {
        focusRegisterInvalidField(validation.firstInvalid);
        return;
    }

    showRegisterStep(3);
};

document.getElementById('btn-back-2').onclick = () => {
    showRegisterStep(1);
};

document.getElementById('btn-back-3').onclick = () => {
    showRegisterStep(2);
};

updateRegisterFirstStepState();
updateRegisterActivityStepState();
updateRegisterGoalStepState();

registerSaveButton.onclick = async () => {
    if (registerSaveButton.disabled) {
        return;
    }

    const bodyValidation = validateRegisterBodyStep({ showErrors: true });
    if (!bodyValidation.valid) {
        showRegisterStep(1);
        window.setTimeout(() => focusRegisterInvalidField(bodyValidation.firstInvalid), 380);
        return;
    }

    const activityValidation = validateRegisterActivityStep({ showErrors: true });
    if (!activityValidation.valid) {
        showRegisterStep(2);
        window.setTimeout(() => focusRegisterInvalidField(activityValidation.firstInvalid), 380);
        return;
    }

    if (!validateRegisterGoalStep({ showErrors: true }).valid) {
        return;
    }

    const age = document.getElementById('age').value;
    const height = document.getElementById('height').value;
    const weight = document.getElementById('weight').value;
    const gender = document.getElementById('gender').value;
    const activityLevel = document.querySelector('input[name="activity_level"]:checked')?.value || '';
    const goal = document.querySelector('input[name="goal"]:checked')?.value || '';

    const data = {
        age: parseInt(age),
        height: parseInt(height),
        weight: parseFloat(weight),
        gender,
        activity_level: activityLevel,
        goal
    };

    registerSaveButton.disabled = true;
    registerSaveLabel.textContent = 'Рассчитываем...';
    tg.MainButton.setText('Рассчитываем...').show();

    try {
        const result = await apiRequestJson('/api/register', {
            method: 'POST',
            json: data
        });

        userData = {
            registered: true,
            daily_goal: result.daily_goal,
            age: data.age,
            height: data.height,
            weight: data.weight,
            gender: data.gender,
            activity_level: data.activity_level,
            goal: data.goal,
            macro_goals: result.macro_goals || {}
        };
        renderRegistrationSuccess(result, data);
        showScreen('registerSuccess');
        haptic('success');
    } catch (error) {
        const validationErrors = error instanceof ApiError ? error.data?.errors : null;
        tg.showAlert(
            validationErrors
                ? Object.values(validationErrors).join('\n')
                : (error?.message || 'Ошибка соединения с сервером')
        );
    } finally {
        registerSaveLabel.textContent = 'Рассчитать';
        updateRegisterGoalStepState();
        tg.MainButton.hide();
    }
};

function renderRegistrationSuccess(result, registrationData) {
    const macroGoals = result.macro_goals || {};

    setElementText('register-success-calories', Math.round(Number(result.daily_goal || 0)));
    setElementText('register-success-proteins', Math.round(Number(macroGoals.proteins_goal || 0)));
    setElementText('register-success-fats', Math.round(Number(macroGoals.fats_goal || 0)));
    setElementText('register-success-carbs', Math.round(Number(macroGoals.carbs_goal || 0)));
    setElementText('register-success-goal', formatGoalLabel(registrationData.goal));
    setElementText('register-success-activity', formatActivityLabel(registrationData.activity_level));
}

document.getElementById('btn-register-finish').onclick = () => {
    updateUserUI();
    showScreen('main');
    haptic('light');
};

// Source: app/js/summary.js
let summaryLoadId = 0;

async function loadSummary() {
    const loadId = ++summaryLoadId;
    const card = document.querySelector('#screen-summary .summary-progress-card');

    updateSummaryIntroText();
    card?.classList.add('is-loading');
    card?.classList.remove('is-error');

    try {
        const result = await apiRequestJson(`/api/progress?tz_offset=${getTimezoneOffsetMinutes()}`);

        if (loadId !== summaryLoadId) {
            return;
        }

        renderSummaryProgress(result.data);
    } catch (error) {
        if (loadId !== summaryLoadId) {
            return;
        }

        console.error('Ошибка загрузки прогресса сводки:', error);
        card?.classList.add('is-error');
    } finally {
        if (loadId === summaryLoadId) {
            card?.classList.remove('is-loading');
        }
    }
}

function updateSummaryIntroText() {
    const intro = document.getElementById('summary-intro-text');
    if (!intro) {
        return;
    }

    const verb = userData?.gender === 'female' ? 'начала' : 'начал';
    intro.textContent = `Ты хорошо ${verb}. Пока данных мало, но уже виден первый прогресс.`;
}

async function refreshSummary() {
    await loadSummary();
}

function renderSummaryProgress(data) {
    const dailyGoal = Math.max(0, Number(data.daily_goal || 0));
    const todayCalories = Math.max(0, Number(data.today_sum || 0));
    const remainingCalories = Math.max(0, Number(data.remaining_calories || 0));
    const calculatedPercentage = dailyGoal > 0 ? (todayCalories / dailyGoal) * 100 : 0;
    const apiPercentage = Number(data.percentage);
    const percentage = Number.isFinite(apiPercentage) ? apiPercentage : calculatedPercentage;
    const roundedPercentage = Math.max(0, Math.round(percentage));
    const visualPercentage = Math.min(Math.max(percentage, 0), 100);

    document.getElementById('summary-progress-percent').textContent = `${roundedPercentage}%`;
    document.getElementById('summary-today-calories').textContent = String(Math.round(todayCalories));
    document.getElementById('summary-daily-goal').textContent = String(Math.round(dailyGoal));
    document.getElementById('summary-remaining-calories').textContent = String(Math.round(remainingCalories));
    document.getElementById('summary-progress-fill').style.width = `${visualPercentage}%`;
    renderSummaryStreak(data.streak || {});
    renderSummaryMacroBalance(data);
}

function renderSummaryMacroBalance(data) {
    const goals = data.macro_goals || {};
    const consumed = data.today_macros || {};

    setSummaryMacroBalance('proteins', consumed.proteins, goals.proteins_goal);
    setSummaryMacroBalance('fats', consumed.fats, goals.fats_goal);
    setSummaryMacroBalance('carbs', consumed.carbs, goals.carbs_goal);
}

function setSummaryMacroBalance(key, consumedValue, goalValue) {
    const element = document.getElementById(`summary-balance-${key}`);
    const consumed = Math.max(0, Number(consumedValue || 0));
    const goal = Math.max(0, Number(goalValue || 0));

    if (!element) {
        return;
    }

    const status = getSummaryMacroBalanceStatus(consumed, goal);
    element.textContent = status.label;
    element.dataset.balance = status.key;
}

function getSummaryMacroBalanceStatus(consumed, goal) {
    if (goal <= 0) {
        return { key: 'empty', label: 'нет данных' };
    }

    const percentage = (consumed / goal) * 100;
    if (percentage < 80) {
        return { key: 'low', label: 'мало' };
    }

    if (percentage > 105) {
        return { key: 'over', label: 'выше' };
    }

    return { key: 'ok', label: 'ок' };
}

function renderSummaryStreak(streak) {
    const card = document.getElementById('summary-streak-card');
    const value = document.getElementById('summary-streak-value');
    const message = document.getElementById('summary-streak-message');
    const days = Math.max(0, Number(streak.current_days || 0));
    const todayCompleted = Boolean(streak.today_completed);
    const streakText = `${days} ${getSummaryStreakDayLabel(days)} подряд`;

    card.dataset.streakStage = getSummaryStreakStage(days);
    card.setAttribute('aria-label', `Серия питания: ${streakText}`);
    value.textContent = streakText;
    message.textContent = getSummaryStreakMessage(days, todayCompleted);
}

function getSummaryStreakDayLabel(days) {
    const lastDigit = days % 10;
    const lastTwoDigits = days % 100;

    if (lastDigit === 1 && lastTwoDigits !== 11) {
        return 'день';
    }

    if (lastDigit >= 2 && lastDigit <= 4 && (lastTwoDigits < 12 || lastTwoDigits > 14)) {
        return 'дня';
    }

    return 'дней';
}

function getSummaryStreakStage(days) {
    if (days <= 0) return 'seed';
    if (days <= 2) return 'sprout';
    if (days <= 6) return 'leafy';
    if (days <= 13) return 'plant';
    if (days <= 29) return 'large';
    return 'tree';
}

function getSummaryStreakMessage(days, todayCompleted) {
    if (!todayCompleted) {
        return days > 0
            ? `Добавь приём сегодня, чтобы продолжить серию в ${days} ${getSummaryStreakDayLabel(days)}.`
            : 'Добавь первый приём сегодня, чтобы начать серию.';
    }

    if (days <= 2) return 'Росток привычки уже появился. Продолжай завтра.';
    if (days <= 6) return 'Росток дал листья. Продолжай вести питание каждый день.';
    if (days <= 13) return 'Привычка крепнет — не прерывай серию.';
    if (days <= 29) return 'Серия стала заметной. Поддержи её завтра.';
    return 'Привычка выросла. Продолжай держать ритм.';
}

// Source: app/js/features/history/calendar-data.js
// History calendar state, loading and date calculations

let historyCalendarCurrentMonth = historyCalendarGetCurrentMonthKey();
let historyCalendarDailyGoal = 0;
let historyCalendarDaysByDate = new Map();
let historyCalendarSelectedDate = '';
let historyCalendarDayExpanded = false;
let historyCalendarLoadId = 0;
let historyCalendarInitialized = false;
let historyCalendarImageRenderId = 0;

async function loadHistoryCalendar(month = historyCalendarCurrentMonth) {
    const loadId = ++historyCalendarLoadId;
    const timezoneOffset = getTimezoneOffsetMinutes();

    historyCalendarSetLoading(true);

    try {
        const result = await apiRequestJson(`/api/summary?month=${month}&tz_offset=${timezoneOffset}`);

        if (loadId !== historyCalendarLoadId) {
            return;
        }

        historyCalendarRender(result.data);
    } catch (error) {
        if (loadId !== historyCalendarLoadId) {
            return;
        }

        console.error('Ошибка загрузки истории:', error);
        historyCalendarRenderError(error?.message || 'Не удалось загрузить историю');
    } finally {
        if (loadId === historyCalendarLoadId) {
            historyCalendarSetLoading(false);
        }
    }
}

async function refreshHistoryCalendar() {
    if (!historyCalendarInitialized) {
        return;
    }

    await loadHistoryCalendar(historyCalendarCurrentMonth);
}

function historyCalendarGetCurrentMonthKey() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function historyCalendarGetTodayDateKey() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function historyCalendarShiftMonth(month, delta) {
    const [year, monthNumber] = month.split('-').map(Number);
    const date = new Date(year, monthNumber - 1 + delta, 1);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function historyCalendarFormatMonth(month) {
    const [year, monthNumber] = month.split('-').map(Number);
    return new Date(year, monthNumber - 1, 1).toLocaleDateString('ru-RU', {
        month: 'long',
        year: 'numeric'
    });
}

function historyCalendarFormatDate(dateKey) {
    const [year, month, day] = dateKey.split('-').map(Number);
    return new Date(year, month - 1, day).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
}

function historyCalendarBuildMonthDates(month) {
    const [year, monthNumber] = month.split('-').map(Number);
    const firstDay = new Date(year, monthNumber - 1, 1);
    const lastDay = new Date(year, monthNumber, 0);
    const firstWeekday = firstDay.getDay() || 7;
    const dates = [];

    for (let index = 1; index < firstWeekday; index += 1) {
        dates.push({ date: null });
    }

    for (let day = 1; day <= lastDay.getDate(); day += 1) {
        dates.push({
            date: `${year}-${String(monthNumber).padStart(2, '0')}-${String(day).padStart(2, '0')}`,
            day
        });
    }

    return dates;
}

function historyCalendarBuildWeekDates(dateKey) {
    const [year, month, day] = dateKey.split('-').map(Number);
    const selectedDate = new Date(year, month - 1, day);
    const weekday = selectedDate.getDay() || 7;
    const monday = new Date(selectedDate);
    const dates = [];

    monday.setDate(selectedDate.getDate() - weekday + 1);

    for (let index = 0; index < 7; index += 1) {
        const date = new Date(monday);
        date.setDate(monday.getDate() + index);
        dates.push({
            date: `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`,
            day: date.getDate()
        });
    }

    return dates;
}

function historyCalendarGetDay(date) {
    return historyCalendarDaysByDate.get(date) || {
        date,
        calories: 0,
        percentage: 0,
        color: 'empty',
        proteins: 0,
        fats: 0,
        carbs: 0,
        meals: []
    };
}

function historyCalendarDayHasRecords(day) {
    return Number(day.calories || 0) > 0
        || Number(day.proteins || 0) > 0
        || Number(day.fats || 0) > 0
        || Number(day.carbs || 0) > 0
        || (Array.isArray(day.meals) && day.meals.length > 0);
}

// Source: app/js/features/history/calendar-view.js
// History calendar rendering

function historyCalendarGetRingVisual(rawPercentage) {
    const percentage = Math.max(Number(rawPercentage || 0), 0);
    const progressValue = Math.min(percentage, 100);

    return {
        className: [
            percentage > 0 ? 'has-progress' : '',
            progressValue >= 99.5 ? 'is-full' : '',
            percentage > 100 ? 'has-over' : ''
        ].filter(Boolean).join(' '),
        progressValue: Math.round(progressValue * 100) / 100,
        rotationValue: Math.round(percentage * 100) / 100
    };
}

function historyCalendarRenderRingSvg() {
    return `
        <svg class="history-ring-svg" viewBox="0 0 44 44" aria-hidden="true" focusable="false">
            <circle class="history-ring-track" cx="22" cy="22" r="18" pathLength="100"></circle>
            <circle class="history-ring-progress" cx="22" cy="22" r="18" pathLength="100"></circle>
        </svg>
    `;
}

function historyCalendarRender(data) {
    const monthDates = historyCalendarBuildMonthDates(data.month);

    historyCalendarCurrentMonth = data.month;
    historyCalendarDailyGoal = Number(data.daily_goal || 0);
    historyCalendarDaysByDate = new Map((data.days || []).map(day => [day.date, day]));
    historyCalendarSelectedDate = historyCalendarResolveSelectedDate(data.month, monthDates);

    document.getElementById('history-month-label').textContent = historyCalendarFormatMonth(data.month);
    historyCalendarRenderGrid();
    historyCalendarRenderDayDetail(historyCalendarGetDay(historyCalendarSelectedDate));
}

function historyCalendarResolveSelectedDate(month, dates) {
    const availableDates = dates.filter(dateInfo => Boolean(dateInfo.date));
    const selectedStillVisible = availableDates.some(dateInfo => dateInfo.date === historyCalendarSelectedDate);
    const today = historyCalendarGetTodayDateKey();

    if (selectedStillVisible) {
        return historyCalendarSelectedDate;
    }

    if (today.startsWith(`${month}-`)) {
        return today;
    }

    return availableDates[0]?.date || '';
}

function historyCalendarRenderGrid() {
    const grid = document.getElementById('history-calendar-grid');
    const dates = historyCalendarDayExpanded
        ? historyCalendarBuildWeekDates(historyCalendarSelectedDate)
        : historyCalendarBuildMonthDates(historyCalendarCurrentMonth);

    if (!grid) {
        return;
    }

    grid.classList.remove('is-calendar-entering');
    void grid.offsetWidth;
    grid.classList.toggle('is-week-mode', historyCalendarDayExpanded);
    grid.innerHTML = dates.map(dateInfo => {
        if (!dateInfo.date) {
            return '<div class="history-calendar-day history-calendar-day-empty-slot"></div>';
        }

        return historyCalendarRenderDay(dateInfo, historyCalendarGetDay(dateInfo.date));
    }).join('');
    grid.classList.add('is-calendar-entering');
    historyCalendarAnimateRings(grid);
}

function historyCalendarRenderDay(dateInfo, day) {
    const ringVisual = historyCalendarGetRingVisual(day.percentage);
    const selectedClass = day.date === historyCalendarSelectedDate ? ' is-selected' : '';

    return `
        <button class="history-calendar-day day-${day.color || 'empty'}${selectedClass}" type="button" data-date="${escapeHtml(day.date)}">
            <span
                class="history-calendar-ring history-progress-ring ${ringVisual.className}"
                data-ring-progress-value="${ringVisual.progressValue}"
                data-ring-rotation-value="${ringVisual.rotationValue}"
            >
                ${historyCalendarRenderRingSvg()}
                <span class="history-ring-dot" aria-hidden="true"></span>
                <span class="history-calendar-day-number">${dateInfo.day}</span>
            </span>
        </button>
    `;
}

function historyCalendarSetRingStroke(ring, progressValue, rotationValue) {
    const progressCircle = ring.querySelector('.history-ring-progress');
    const dot = ring.querySelector('.history-ring-dot');
    const progress = Math.min(Math.max(Number(progressValue || 0), 0), 100);
    const rotation = Math.max(Number(rotationValue || 0), 0);

    if (progressCircle) {
        progressCircle.style.strokeDashoffset = String(100 - progress);
    }

    if (dot) {
        dot.style.transform = `rotate(${rotation * 3.6}deg)`;
    }
}

function historyCalendarAnimateRings(container) {
    const rings = container.matches?.('.history-progress-ring')
        ? [container]
        : Array.from(container.querySelectorAll('.history-progress-ring'));

    rings.forEach(ring => {
        ring.classList.remove('is-ring-animated');
        ring.style.removeProperty('transition-delay');
        historyCalendarSetRingStroke(ring, 0, 0);
    });

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            rings.forEach((ring, index) => {
                ring.style.setProperty('transition-delay', `${Math.min(index * 16, 160)}ms`);
                historyCalendarSetRingStroke(
                    ring,
                    ring.dataset.ringProgressValue,
                    ring.dataset.ringRotationValue
                );
                ring.classList.add('is-ring-animated');
            });
        });
    });
}

function historyCalendarApplyRingVisual(ring, rawPercentage) {
    const ringVisual = historyCalendarGetRingVisual(rawPercentage);

    ring.classList.toggle('has-progress', ringVisual.className.includes('has-progress'));
    ring.classList.toggle('is-full', ringVisual.className.includes('is-full'));
    ring.classList.toggle('has-over', ringVisual.className.includes('has-over'));
    ring.dataset.ringProgressValue = String(ringVisual.progressValue);
    ring.dataset.ringRotationValue = String(ringVisual.rotationValue);
    historyCalendarSetRingStroke(ring, 0, 0);
}

function historyCalendarRenderDayDetail(day) {
    const detail = document.getElementById('history-day-detail');
    const ring = document.getElementById('history-day-ring');
    const expandButton = document.getElementById('history-day-expand');
    const expandedContent = detail?.querySelector('.history-day-expanded');
    const hasRecords = historyCalendarDayHasRecords(day);
    const percentage = Number(day.percentage || 0);

    if (!detail || !ring || !expandButton || !expandedContent) {
        return;
    }

    ring.className = `history-day-ring history-progress-ring day-${day.color || 'empty'}`;
    detail.classList.toggle('is-empty', !hasRecords);
    detail.classList.toggle('is-expanded', hasRecords && historyCalendarDayExpanded);
    historyCalendarApplyRingVisual(ring, percentage);
    historyCalendarAnimateRings(ring);

    document.getElementById('history-day-date').textContent = historyCalendarFormatDate(day.date);
    document.getElementById('history-day-percent').textContent = `${Math.round(percentage)}%`;
    document.getElementById('history-day-calories').textContent = Math.round(Number(day.calories || 0));
    document.getElementById('history-day-goal-calories').textContent = Math.round(historyCalendarDailyGoal);
    document.getElementById('history-day-proteins').textContent = formatMacro(day.proteins);
    document.getElementById('history-day-fats').textContent = formatMacro(day.fats);
    document.getElementById('history-day-carbs').textContent = formatMacro(day.carbs);
    const mealsList = document.getElementById('history-day-meals-list');
    historyCalendarRevokeImageUrls(mealsList);
    historyCalendarImageRenderId++;
    mealsList.innerHTML = historyCalendarRenderMeals(day.meals || []);
    historyCalendarLoadImages(mealsList, String(historyCalendarImageRenderId));

    expandButton.hidden = !hasRecords;
    expandButton.setAttribute('aria-expanded', String(hasRecords && historyCalendarDayExpanded));
    document.getElementById('history-day-expand-label').textContent = historyCalendarDayExpanded
        ? 'Свернуть'
        : 'Развернуть';

    if (!hasRecords) {
        expandedContent.style.removeProperty('max-height');
        return;
    }

    if (!historyCalendarDayExpanded) {
        expandedContent.style.maxHeight = '0px';
        return;
    }

    requestAnimationFrame(() => {
        expandedContent.style.maxHeight = `${expandedContent.scrollHeight}px`;
    });
}

function historyCalendarGetMealSlot(time) {
    const hour = Number(String(time || '').split(':')[0]);

    if (hour >= 5 && hour < 12) {
        return 'breakfast';
    }

    if (hour >= 12 && hour < 16) {
        return 'lunch';
    }

    if (hour >= 16 && hour < 22) {
        return 'dinner';
    }

    return 'snacks';
}

function historyCalendarGetMealSlots() {
    return [
        { key: 'breakfast', title: 'Завтрак' },
        { key: 'lunch', title: 'Обед' },
        { key: 'dinner', title: 'Ужин' },
        { key: 'snacks', title: 'Перекусы' }
    ];
}

function historyCalendarRenderMeals(meals) {
    if (meals.length === 0) {
        return historyCalendarRenderEmptyDay();
    }

    return historyCalendarGetMealSlots().map(slot => {
        const slotMeals = meals.filter(meal => historyCalendarGetMealSlot(meal.time) === slot.key);

        if (slotMeals.length === 0) {
            return `
                <section class="history-meal-slot is-empty">
                    <h3>${slot.title}</h3>
                    <p>Не добавлено</p>
                </section>
            `;
        }

        return `
            <section class="history-meal-slot">
                <h3>${slot.title}</h3>
                ${slotMeals.map(historyCalendarRenderMeal).join('')}
            </section>
        `;
    }).join('');
}

function historyCalendarRenderMeal(meal) {
    const meta = [];
    const thumbnailUrl = String(meal.thumbnail_url || '');
    const thumbnail = thumbnailUrl
        ? `<img
            src="data:image/gif;base64,R0lGODlhAQABAAAAACw="
            alt=""
            data-history-image-url="${escapeHtml(thumbnailUrl)}"
            data-history-image-render-id="${historyCalendarImageRenderId}"
            loading="lazy"
        >`
        : '';

    if (Number(meal.weight || 0) > 0) {
        meta.push(`${formatMacro(meal.weight)} г`);
    }

    meta.push(`Б ${formatMacro(meal.proteins)}`);
    meta.push(`Ж ${formatMacro(meal.fats)}`);
    meta.push(`У ${formatMacro(meal.carbs)}`);

    return `
        <article class="history-day-meal">
            <span class="history-day-meal-thumb history-food-generic" aria-hidden="true">${thumbnail}</span>
            <div class="history-day-meal-info">
                <strong>${escapeHtml(meal.description || 'Приём пищи')}</strong>
                <small class="history-day-meal-time">${escapeHtml(meal.time || '')}</small>
                <small>${escapeHtml(meta.join(' · '))}</small>
            </div>
            <span class="history-day-meal-calories">
                <strong>${Math.round(Number(meal.calories || 0))}</strong> ккал
            </span>
        </article>
    `;
}

function historyCalendarRevokeImageUrls(root) {
    root?.querySelectorAll('img[data-history-object-url]').forEach(image => {
        URL.revokeObjectURL(image.dataset.historyObjectUrl);
        delete image.dataset.historyObjectUrl;
    });
}

function historyCalendarLoadImages(root, renderId) {
    root.querySelectorAll('img[data-history-image-url]').forEach(image => {
        historyCalendarLoadImage(image, renderId);
    });
}

async function historyCalendarLoadImage(image, renderId) {
    if (!image.isConnected || image.dataset.historyImageRenderId !== renderId) {
        return;
    }

    try {
        const blob = await apiRequestBlob(image.dataset.historyImageUrl);
        if (!image.isConnected || image.dataset.historyImageRenderId !== renderId) {
            return;
        }

        const objectUrl = URL.createObjectURL(blob);
        image.src = objectUrl;
        image.dataset.historyObjectUrl = objectUrl;
    } catch (error) {
        console.error('Ошибка загрузки миниатюры истории:', error);
    }
}

function historyCalendarRenderEmptyDay() {
    return `
        <div class="history-empty-day">
            <strong>В этот день ещё ничего не добавлено</strong>
            <p>Нажми на плюс, чтобы добавить первый приём.</p>
            <svg class="history-add-pointer" viewBox="0 0 64 74" aria-hidden="true" focusable="false">
                <path class="history-add-pointer-line" d="M18 12c13-10 33 1 23 16-6 9-22 3-15-6 5-6 18-2 19 11 .8 10-5 19-13 26" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="3"/>
                <path class="history-add-pointer-head" d="M32 59l9-2M32 59l2-9" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="3"/>
            </svg>
        </div>
    `;
}

// Source: app/js/features/history/calendar-bindings.js
// History calendar interactions and initialization

function historyCalendarSelectDay(date) {
    historyCalendarSelectedDate = date;
    historyCalendarDayExpanded = false;
    historyCalendarRenderGrid();
    historyCalendarRenderDayDetail(historyCalendarGetDay(date));
}

function historyCalendarToggleDayExpanded() {
    historyCalendarDayExpanded = !historyCalendarDayExpanded;
    historyCalendarRenderGrid();
    historyCalendarRenderDayDetail(historyCalendarGetDay(historyCalendarSelectedDate));
}

function historyCalendarSetLoading(isLoading) {
    const previousButton = document.getElementById('btn-history-prev-month');
    const nextButton = document.getElementById('btn-history-next-month');

    if (previousButton) {
        previousButton.disabled = isLoading;
    }

    if (nextButton) {
        nextButton.disabled = isLoading;
    }
}

function historyCalendarRenderError(message) {
    const grid = document.getElementById('history-calendar-grid');

    if (!grid) {
        return;
    }

    grid.classList.remove('is-week-mode');
    grid.innerHTML = `
        <div class="history-calendar-error">
            ${escapeHtml(message)}
            <button type="button" data-history-retry>Повторить</button>
        </div>
    `;
}

function initializeHistoryCalendar() {
    if (historyCalendarInitialized) {
        return;
    }

    const grid = document.getElementById('history-calendar-grid');
    const previousButton = document.getElementById('btn-history-prev-month');
    const nextButton = document.getElementById('btn-history-next-month');
    const expandButton = document.getElementById('history-day-expand');

    if (!grid || !previousButton || !nextButton || !expandButton) {
        return;
    }

    historyCalendarInitialized = true;

    grid.addEventListener('click', event => {
        const retryButton = event.target.closest('[data-history-retry]');
        if (retryButton) {
            loadHistoryCalendar(historyCalendarCurrentMonth);
            return;
        }

        const dayButton = event.target.closest('.history-calendar-day[data-date]');
        if (dayButton) {
            historyCalendarSelectDay(dayButton.dataset.date);
        }
    });

    previousButton.addEventListener('click', () => {
        historyCalendarDayExpanded = false;
        loadHistoryCalendar(historyCalendarShiftMonth(historyCalendarCurrentMonth, -1));
    });

    nextButton.addEventListener('click', () => {
        historyCalendarDayExpanded = false;
        loadHistoryCalendar(historyCalendarShiftMonth(historyCalendarCurrentMonth, 1));
    });

    expandButton.addEventListener('click', historyCalendarToggleDayExpanded);
}

initializeHistoryCalendar();

// Source: app/js/app.js
const screens = {
    loading: document.getElementById('screen-loading'),
    welcome: document.getElementById('screen-welcome'),
    register: document.getElementById('screen-register'),
    registerSuccess: document.getElementById('screen-register-success'),
    main: document.getElementById('screen-main'),
    summary: document.getElementById('screen-summary'),
    history: document.getElementById('screen-history'),
    settings: document.getElementById('screen-settings'),
    profileEdit: document.getElementById('screen-profile-edit'),
    goalEdit: document.getElementById('screen-goal-edit')
};

const appTabBar = document.getElementById('app-tab-bar');
const addFoodFab = document.getElementById('btn-add-food');
const tabItems = Array.from(document.querySelectorAll('.tab-item'));
const loadingTitle = document.getElementById('app-loading-title');
const loadingMessage = document.getElementById('app-loading-message');
const retryStatusButton = document.getElementById('btn-retry-status');

let userData = null;

document.addEventListener('DOMContentLoaded', async () => {
    if (!tgId) {
        showLoadingError('Пожалуйста, запустите приложение через Telegram.');
        return;
    }

    recordAppOpened();
    await initializeApp();
});

retryStatusButton.onclick = () => {
    initializeApp();
};

async function initializeApp() {
    setLoadingState('Загружаем профиль...');
    showScreen('loading');

    try {
        await loadProcessingOptions();
        await checkUserStatus();
    } catch (error) {
        console.error('Ошибка связи с бэкендом', error);
        showLoadingError(
            error instanceof ApiError
                ? error.message
                : 'Не удалось загрузить профиль. Проверьте соединение и попробуйте снова.'
        );
    }
}

async function recordAppOpened() {
    try {
        await apiRequestJson('/api/events/app-opened', { method: 'POST' });
    } catch (error) {
        console.warn('Не удалось записать открытие приложения', error);
    }
}

async function checkUserStatus() {
    const result = await apiRequestJson('/api/user-status', {
        requireSuccessStatus: false
    });

    if (result.registered) {
        userData = result;
        updateUserUI();
        updateHomeGreeting();
        showScreen('main');
        return;
    }

    showScreen('welcome');
}

function setLoadingState(message) {
    loadingTitle.textContent = 'FoodTracker AI';
    loadingMessage.textContent = message;
    retryStatusButton.classList.add('hidden');
}

function showLoadingError(message) {
    loadingTitle.textContent = 'Не удалось запустить приложение';
    loadingMessage.textContent = message;
    retryStatusButton.classList.remove('hidden');
    showScreen('loading');
}

tabItems.forEach(tab => {
    tab.onclick = () => {
        if (tab.classList.contains('active')) {
            return;
        }

        showScreen(tabToScreenName(tab.dataset.tab));
    };
});

function showScreen(screenName) {
    Object.keys(screens).forEach(key => {
        screens[key].classList.add('hidden');
    });
    screens[screenName].classList.remove('hidden');
    updateTabBar(screenName);

    if (screenName === 'main' && userData) {
        updateHomeGreeting();
        loadMealHistory();
        loadDailyNutritionInsight();
    }

    if (screenName === 'summary' && userData) {
        loadSummary();
        loadDailyNutritionInsight();
    }

    if (screenName === 'history' && userData) {
        loadHistoryCalendar(historyCalendarCurrentMonth);
    }

    if (screenName === 'settings' && userData) {
        loadAiUsage();
    }
}

function tabToScreenName(tabName) {
    return {
        home: 'main',
        summary: 'summary',
        history: 'history',
        profile: 'settings'
    }[tabName] || 'main';
}

function screenNameToTab(screenName) {
    return {
        main: 'home',
        summary: 'summary',
        history: 'history',
        settings: 'profile'
    }[screenName] || '';
}

function updateTabBar(screenName) {
    const activeTab = screenNameToTab(screenName);
    appTabBar.classList.toggle('hidden', !activeTab);
    addFoodFab?.classList.toggle('hidden', !activeTab);

    const activeIndex = tabItems.findIndex(item => item.dataset.tab === activeTab);

    if (activeIndex >= 0) {
        appTabBar.style.setProperty('--active-tab-index', activeIndex);
    }

    tabItems.forEach(item => {
        item.classList.toggle('active', item.dataset.tab === activeTab);
    });
}

document.getElementById('btn-home-profile')?.addEventListener('click', () => {
    showScreen('settings');
});

document.getElementById('btn-history-profile')?.addEventListener('click', () => {
    showScreen('settings');
});

document.getElementById('btn-summary-profile')?.addEventListener('click', () => {
    showScreen('settings');
});

function updateHomeGreeting() {
    document.getElementById('home-greeting-text').textContent = getTimeGreeting();
    document.getElementById('home-greeting-name').textContent = getTelegramDisplayName();
}

function getTimeGreeting() {
    const hour = new Date().getHours();

    if (hour >= 5 && hour < 12) {
        return 'Доброе утро';
    }

    if (hour >= 12 && hour < 18) {
        return 'Добрый день';
    }

    if (hour >= 18 && hour < 23) {
        return 'Добрый вечер';
    }

    return 'Доброй ночи';
}

function getTelegramDisplayName() {
    return user?.first_name || user?.username || 'пользователь';
}

