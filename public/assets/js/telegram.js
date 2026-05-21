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

var realTg = window.Telegram?.WebApp;
var tg = hasTelegramSession(realTg) ? realTg : createTelegramWebAppMock();
tg.expand();

var user = tg.initDataUnsafe?.user;
var tgId = user?.id || 0;
var telegramInitData = tg.initData || '';
