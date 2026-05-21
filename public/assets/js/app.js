const screens = {
    welcome: document.getElementById('screen-welcome'),
    register: document.getElementById('screen-register'),
    main: document.getElementById('screen-main'),
    summary: document.getElementById('screen-summary'),
    settings: document.getElementById('screen-settings'),
    profileEdit: document.getElementById('screen-profile-edit')
};

const appTabBar = document.getElementById('app-tab-bar');

let userData = null;

document.addEventListener('DOMContentLoaded', async () => {
    if (!tgId) {
        alert('Пожалуйста, запустите приложение через Telegram');
        return;
    }

    await loadProcessingOptions();
    await checkUserStatus();
});

async function checkUserStatus() {
    try {
        const response = await apiFetch('/api/user-status');
        const result = await response.json();

        if (result.registered) {
            userData = result;
            updateUserUI();
            updateHomeGreeting();
            showScreen('main');
            return;
        }

        showScreen('welcome');
    } catch (error) {
        console.error('Ошибка связи с бэкендом', error);
        showScreen('welcome');
    }
}

document.querySelectorAll('.tab-item').forEach(tab => {
    tab.onclick = () => {
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
    }

    if (screenName === 'summary' && userData) {
        loadSummaryCalendar();
    }
}

function tabToScreenName(tabName) {
    return {
        home: 'main',
        summary: 'summary',
        settings: 'settings'
    }[tabName] || 'main';
}

function screenNameToTab(screenName) {
    return {
        main: 'home',
        summary: 'summary',
        settings: 'settings'
    }[screenName] || '';
}

function updateTabBar(screenName) {
    const activeTab = screenNameToTab(screenName);
    appTabBar.classList.toggle('hidden', !activeTab);

    document.querySelectorAll('.tab-item').forEach(item => {
        item.classList.toggle('active', item.dataset.tab === activeTab);
    });
}

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
