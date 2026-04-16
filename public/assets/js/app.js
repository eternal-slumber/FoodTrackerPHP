
// 1. Инициализация Telegram WebApp
const tg = window.Telegram.WebApp;
tg.expand(); // Разворачиваем приложение на весь экран

// Получаем данные пользователя из Telegram
const user = tg.initDataUnsafe?.user;
const tgId = user?.id || 0; // На случай тестов в браузере

// Элементы интерфейса
const screens = {
    welcome: document.getElementById('screen-welcome'),
    register: document.getElementById('screen-register'),
    main: document.getElementById('screen-main'),
    settings: document.getElementById('screen-settings')
};

// Храним данные пользователя
let userData = null;

// При загрузке страницы
document.addEventListener('DOMContentLoaded', async () => {
    if (!tgId) {
        alert("Пожалуйста, запустите приложение через Telegram");
        return;
    }

    // Проверяем статус пользователя на бэкенде
    await checkUserStatus();
});

// Функция проверки: зарегистрирован ли юзер?
async function checkUserStatus() {
    try {
        // Мы отправляем GET запрос, чтобы узнать норму калорий и статус
        const response = await fetch(`/api/user-status?tg_id=${tgId}`);
        const result = await response.json();

        if (result.registered) {
            userData = result;
            updateUserUI();
            showScreen('main');
        } else {
            showScreen('welcome');
        }
    } catch (e) {
        console.error("Ошибка связи с бэкендом", e);
        // Если бэкенд еще не готов, покажем приветствие для теста
        showScreen('welcome');
    }
}

// Обновление UI данными пользователя
function updateUserUI() {
    if (!userData) return;
    
    document.getElementById('user-goal').innerText = userData.daily_goal || 0;
    document.getElementById('user-weight').innerText = userData.weight || 0;
    document.getElementById('user-height').innerText = userData.height || 0;
    
    // Обновляем настройки
    document.getElementById('settings-age').innerText = userData.age || '-';
    document.getElementById('settings-height').innerText = userData.height ? `${userData.height} см` : '-';
    document.getElementById('settings-weight').innerText = userData.weight ? `${userData.weight} кг` : '-';
    document.getElementById('settings-gender').innerText = userData.gender === 'male' ? 'Мужчина' : 'Женщина';
    document.getElementById('settings-goal').innerText = userData.daily_goal ? `${userData.daily_goal} ккал` : '-';
    
    // Обновляем прогресс
    loadProgress();
}

// Загрузка прогресса
async function loadProgress() {
    try {
        const response = await fetch(`/api/progress?tg_id=${tgId}`);
        const result = await response.json();
        
        if (result.status === 'success') {
            updateProgressUI(result.data);
        }
    } catch (error) {
        console.error('Ошибка загрузки прогресса:', error);
    }
}

// Обновление UI прогресса
function updateProgressUI(data) {
    const dailyGoal = data.daily_goal || 0;
    const todaySum = data.today_sum || 0;
    const percentage = data.percentage || 0;
    
    document.getElementById('daily-goal').innerText = dailyGoal;
    document.getElementById('today-calories').innerText = todaySum;
    document.getElementById('progress-percentage').innerText = `${Math.round(percentage)}%`;
    document.getElementById('progress-fill').style.width = `${Math.min(percentage, 100)}%`;
}

// Обработка кнопки "Начать" на экране приветствия
document.getElementById('btn-start').onclick = () => {
    showScreen('register');
};

// Переключение шагов регистрации
const registerSteps = {
    1: document.getElementById('register-step-1'),
    2: document.getElementById('register-step-2'),
    3: document.getElementById('register-step-3')
};

function showRegisterStep(step) {
    Object.values(registerSteps).forEach(el => el.classList.add('hidden'));
    registerSteps[step].classList.remove('hidden');
}

// Шаг 1 -> Шаг 2
document.getElementById('btn-next-1').onclick = () => {
    const age = document.getElementById('age').value;
    const height = document.getElementById('height').value;
    const weight = document.getElementById('weight').value;
    const gender = document.getElementById('gender').value;

    if (!age || !height || !weight || !gender) {
        tg.showAlert("Пожалуйста, заполните все поля");
        return;
    }

    showRegisterStep(2);
};

// Шаг 2 -> Шаг 3
document.getElementById('btn-next-2').onclick = () => {
    showRegisterStep(3);
};

// Шаг 2 -> Назад
document.getElementById('btn-back-2').onclick = () => {
    showRegisterStep(1);
};

// Шаг 3 -> Назад
document.getElementById('btn-back-3').onclick = () => {
    showRegisterStep(2);
};

// Обработка регистрации (финальный шаг)
document.getElementById('btn-save').onclick = async () => {
    const age = document.getElementById('age').value;
    const height = document.getElementById('height').value;
    const weight = document.getElementById('weight').value;
    const gender = document.getElementById('gender').value;
    
    const activityLevel = document.querySelector('input[name="activity_level"]:checked')?.value || 'medium';
    const goal = document.querySelector('input[name="goal"]:checked')?.value || 'maintenance';

    const data = {
        tg_id: tgId,
        age: parseInt(age),
        height: parseInt(height),
        weight: parseFloat(weight),
        gender: gender,
        activity_level: activityLevel,
        goal: goal
    };

    tg.MainButton.setText("Регистрация...").show();

    try {
        const response = await fetch('/api/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        
        if (result.status === 'success') {
            userData = {
                registered: true,
                daily_goal: result.daily_goal,
                age: data.age,
                height: data.height,
                weight: data.weight,
                gender: data.gender,
                activity_level: data.activity_level,
                goal: data.goal
            };
            updateUserUI();
            showScreen('main');
        } else if (result.errors) {
            const errorMessages = Object.values(result.errors).join('\n');
            tg.showAlert(errorMessages);
        } else {
            tg.showAlert(result.message || "Ошибка при регистрации");
        }
    } catch (error) {
        tg.showAlert("Ошибка соединения с сервером");
    } finally {
        tg.MainButton.hide();
    }
};

// Обработка сканирования еды
const photoInput = document.getElementById('photo-input');
document.getElementById('btn-scan').onclick = () => photoInput.click();

photoInput.onchange = async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    // Показываем стандартную синюю кнопку Telegram как индикатор загрузки
    tg.MainButton.setText("Анализирую фото...").show();

    const formData = new FormData();
    formData.append('photo', file);
    formData.append('tg_id', tgId);

    try {
        const response = await fetch('/api/upload', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.status === 'success') {
            tg.showAlert(`Это ${result.data.food}! \nПримерно ${result.data.kcal} ккал.`);
            // Обновляем историю и прогресс после успешной загрузки
            loadMealHistory();
            loadProgress();
        } else {
            tg.showAlert("Упс, не удалось распознать еду.");
        }
    } catch (error) {
        tg.showAlert("Ошибка при отправке на сервер.");
    } finally {
        tg.MainButton.hide();
    }
};

// Навигация по табам
document.querySelectorAll('.tab-item').forEach(tab => {
    tab.onclick = () => {
        const tabName = tab.dataset.tab;
        
        // Обновляем активный таб
        document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        // Переключаем экран
        if (tabName === 'home') {
            showScreen('main');
        } else if (tabName === 'settings') {
            showScreen('settings');
        }
    };
});

// Удаление профиля
document.getElementById('btn-delete-profile').onclick = async () => {
    // Запрашиваем подтверждение
    const confirmed = confirm("Вы уверены, что хотите удалить профиль? Это действие нельзя отменить.");
    
    if (!confirmed) {
        return;
    }

    try {
        const response = await fetch('/api/delete-profile', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tg_id: tgId })
        });

        const result = await response.json();
        
        if (result.status === 'success') {
            tg.showAlert("Профиль успешно удален");
            // Сбрасываем данные и показываем приветствие
            userData = null;
            showScreen('welcome');
        } else {
            tg.showAlert(result.message || "Ошибка при удалении профиля");
        }
    } catch (error) {
        tg.showAlert("Ошибка соединения с сервером");
    }
};

// Загрузка истории приемов пищи
async function loadMealHistory() {
    try {
        const response = await fetch(`/api/history?tg_id=${tgId}`);
        const result = await response.json();
        
        if (result.status === 'success') {
            renderMealHistory(result.data);
        }
    } catch (error) {
        console.error('Ошибка загрузки истории:', error);
    }
}

// Отрисовка истории приемов пищи
function renderMealHistory(meals) {
    const historyList = document.getElementById('history-list');
    
    if (!meals || meals.length === 0) {
        historyList.innerHTML = '<p class="history-empty">История пуста</p>';
        return;
    }
    
    historyList.innerHTML = meals.map(meal => {
        // Форматируем дату
        const date = new Date(meal.created_at);
        const formattedDate = date.toLocaleDateString('ru-RU', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        return `
            <div class="history-item">
                <div class="history-thumbnail">
                    <img src="${meal.image_url}" alt="${meal.description}" onerror="this.src='/assets/img/placeholder.png'">
                </div>
                <div class="history-info">
                    <div class="history-date">${formattedDate}</div>
                    <div class="history-description">${meal.description}</div>
                </div>
                <div class="history-calories">
                    <span class="calories-value">${meal.calories}</span>
                    <span class="calories-label">ккал</span>
                </div>
                <button class="btn-delete-meal" onclick="deleteMeal(${meal.id})" title="Удалить запись">
                    <span class="delete-icon">🗑️</span>
                </button>
            </div>
        `;
    }).join('');
}

// Удаление записи о приеме пищи
async function deleteMeal(mealId) {
    // Запрашиваем подтверждение
    const confirmed = confirm('Вы уверены, что хотите удалить эту запись?');
    
    if (!confirmed) {
        return;
    }

    try {
        const response = await fetch('/api/delete-meal', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ meal_id: mealId, tg_id: tgId })
        });

        const result = await response.json();
        
        if (result.status === 'success') {
            tg.showAlert('Запись успешно удалена');
            // Обновляем историю и прогресс
            loadMealHistory();
            loadProgress();
        } else {
            tg.showAlert(result.message || 'Ошибка при удалении записи');
        }
    } catch (error) {
        tg.showAlert('Ошибка соединения с сервером');
    }
}

// Утилита для переключения экранов
function showScreen(screenName) {
    Object.keys(screens).forEach(key => {
        screens[key].classList.add('hidden');
    });
    screens[screenName].classList.remove('hidden');
    
    // Загружаем историю при показе главного экрана
    if (screenName === 'main' && userData) {
        loadMealHistory();
    }
}
