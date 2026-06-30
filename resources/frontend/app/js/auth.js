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
document.documentElement.classList.toggle('is-telegram-webapp', tg === realTg);
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
