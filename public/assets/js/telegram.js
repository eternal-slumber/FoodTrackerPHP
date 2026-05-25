function createTelegramWebAppMock() {
    const clickHandlers = new Set();
    const backHandlers = new Set();

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
            user: {
                id: 100001,
                first_name: 'Dev',
                username: 'dev_user'
            }
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
    document.documentElement.style.setProperty('--tg-safe-area-top', safeAreaTop + 'px');
}

var realTg = window.Telegram?.WebApp;
var tg = hasTelegramSession(realTg) ? realTg : createTelegramWebAppMock();
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
