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
