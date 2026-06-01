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

function applyAppTheme(theme, options = {}) {
    const normalizedTheme = normalizeAppTheme(theme);

    if (options.animate) {
        document.documentElement.classList.add('theme-transition');
        window.setTimeout(() => {
            document.documentElement.classList.remove('theme-transition');
        }, 340);
    }

    document.documentElement.dataset.appTheme = normalizedTheme;

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
var tg = hasTelegramSession(realTg) ? realTg : createTelegramWebAppMock();
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
