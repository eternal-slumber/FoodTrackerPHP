
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
        photoInput.value = '';
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

// ========== BottomSheet "Добавить еду" ==========

const bottomSheet = document.getElementById('bottom-sheet');
const sheetScreenA = document.getElementById('sheet-screen-a');
const sheetScreenB = document.getElementById('sheet-screen-b');
const foodDescription = document.getElementById('food-description');
const foodPhoto = document.getElementById('food-photo');
const photoPreview = document.getElementById('photo-preview');
const previewImg = document.getElementById('preview-img');
const btnAddPhoto = document.getElementById('btn-add-photo');
const btnRemovePhoto = document.getElementById('btn-remove-photo');
const btnToScreenB = document.getElementById('btn-to-screen-b');
const btnBackToA = document.getElementById('btn-back-to-a');
const btnAddProduct = document.getElementById('btn-add-product');
const btnSaveMeal = document.getElementById('btn-save-meal');
const productsList = document.getElementById('products-list');
const mealNameInput = document.getElementById('meal-name');

// Открыть BottomSheet
document.getElementById('btn-add-food').onclick = () => {
    bottomSheet.classList.remove('hidden');
    // Сброс формы
    mealNameInput.value = '';
    foodDescription.value = '';
    foodPhoto.value = '';
    photoPreview.classList.add('hidden');
    btnToScreenB.disabled = true;
    sheetScreenA.classList.remove('hidden');
    sheetScreenB.classList.add('hidden');
};

// Закрыть BottomSheet
document.querySelector('.sheet-overlay').onclick = () => {
    bottomSheet.classList.add('hidden');
};

// Выбор фото
btnAddPhoto.onclick = () => foodPhoto.click();

foodPhoto.onchange = (e) => {
    const file = e.target.files[0];
    if (file) {
        previewImg.src = URL.createObjectURL(file);
        photoPreview.classList.remove('hidden');
    }
};

// Удалить фото
btnRemovePhoto.onclick = () => {
    foodPhoto.value = '';
    photoPreview.classList.add('hidden');
};

// Валидация кнопки Далее
foodDescription.oninput = () => {
    btnToScreenB.disabled = foodDescription.value.trim() === '';
};

// Переход на экран Б
btnToScreenB.onclick = () => {
    const mealName = mealNameInput.value.trim();
    document.getElementById('screen-b-meal-name').textContent = mealName;
    sheetScreenA.classList.add('hidden');
    sheetScreenB.classList.remove('hidden');
    renderProducts();
};

// Вернуться на экран А
btnBackToA.onclick = () => {
    sheetScreenB.classList.add('hidden');
    sheetScreenA.classList.remove('hidden');
};

// Рендер карточек продуктов
function renderProducts() {
    const text = foodDescription.value.trim();
    const products = text.split(/[,;]/).map(p => p.trim()).filter(p => p);
    
    if (products.length === 0) products.push('');
    
    productsList.innerHTML = products.map((name, i) => createProductCard(name, i)).join('');
    
    // Обновить обработчики
    document.querySelectorAll('.kbju-toggle').forEach(btn => {
        btn.onclick = (e) => {
            const panel = e.target.closest('.product-card').querySelector('.kbju-panel');
            panel.classList.toggle('hidden');
        };
    });
    
    document.querySelectorAll('.product-delete').forEach(btn => {
        btn.onclick = (e) => {
            e.target.closest('.product-card').remove();
        };
});
}

function updateCardLabel(input) {
    const card = input.closest('.product-card');
    const label = card.querySelector('.input-label');
    const name = input.value.trim();
    const index = Array.from(productsList.children).indexOf(card);
    label.textContent = `Продукт ${index + 1}${name ? ` (${name})` : ''}`;
}

function createProductCard(name, index) {
    const displayName = name ? ` (${name})` : '';
    return `
        <div class="product-card">
            <span class="input-label">Продукт ${index + 1}${displayName}</span>
            <div class="product-row inline">
                <div class="name-wrap">
                    <label class="field-label">Название</label>
                    <input type="text" class="product-name" value="${name}" placeholder="Название" oninput="updateCardLabel(this)">
                </div>
                <div class="weight-wrap">
                    <label class="field-label">Вес (г)</label>
                    <input type="number" class="product-weight" value="100">
                </div>
            </div>
            <label class="field-label">Обработка</label>
            <select class="processing-select">
                <option value="">Не указано</option>
                <option value="fry">Жарка</option>
                <option value="boil">Варка</option>
                <option value="stew">Тушение</option>
                <option value="bake">Запекание</option>
            </select>
            <button class="kbju-toggle">Свои КБЖУ (на 100г)</button>
            <div class="kbju-panel hidden">
                <div class="kbju-field-wrap">
                    <input type="number" class="kbju-field" placeholder="0">
                    <span class="kbju-label">ккал</span>
                </div>
                <div class="kbju-field-wrap">
                    <input type="number" class="kbju-field" placeholder="0">
                    <span class="kbju-label">бел</span>
                </div>
                <div class="kbju-field-wrap">
                    <input type="number" class="kbju-field" placeholder="0">
                    <span class="kbju-label">жир</span>
                </div>
                <div class="kbju-field-wrap">
                    <input type="number" class="kbju-field" placeholder="0">
                    <span class="kbju-label">угл</span>
                </div>
            </div>
            <button class="product-delete" title="Удалить">🗑️</button>
        </div>
    `;
}

// Добавить еще продукт
btnAddProduct.onclick = () => {
    const card = createProductCard('', productsList.children.length);
    productsList.insertAdjacentHTML('beforeend', card);
    
    // Обновить обработчики новой карточки
    const newCard = productsList.lastElementChild;
    const newDelete = newCard.querySelector('.product-delete');
    newDelete.onclick = (e) => {
        e.target.closest('.product-card').remove();
    };
    
    const newToggle = newCard.querySelector('.kbju-toggle');
    newToggle.onclick = (e) => {
        const panel = e.target.closest('.product-card').querySelector('.kbju-panel');
        panel.classList.toggle('hidden');
    };
};

// Сохранить прием пищи
btnSaveMeal.onclick = async () => {
    const mealName = mealNameInput.value.trim();
    const cards = productsList.querySelectorAll('.product-card');
    const products = [];
    
    let hasEmptyName = false;
    cards.forEach(card => {
        const name = card.querySelector('.product-name').value.trim();
        const weight = parseInt(card.querySelector('.product-weight').value) || 100;
        const processing = card.querySelector('.processing-select').value;
        
        if (!name) {
            hasEmptyName = true;
        }
        
        const kbju = {};
        card.querySelectorAll('.kbju-field').forEach((field, i) => {
            const keys = ['calories', 'proteins', 'fats', 'carbs'];
            if (field.value) kbju[keys[i]] = field.value;
        });
        
        if (name) {
            products.push({ name, weight, processing, kbju });
        }
    });
    
    if (hasEmptyName) {
        tg.showAlert('Заполните названия всех продуктов!');
        return;
    }
    
    if (products.length === 0) {
        tg.showAlert('Добавьте хотя бы один продукт!');
        return;
    }
    
    tg.MainButton.setText('Сохранение...').show();
    
    try {
        const response = await fetch('/api/save-meal', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tg_id: tgId,
                meal_name: mealName || 'Прием пищи',
                products: products
            })
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            bottomSheet.classList.add('hidden');
            loadMealHistory();
            loadProgress();
            
            const mealTitle = mealName || 'Прием пищи';
            const total = result.meal?.calories || 0;
            const today = result.today_calories || 0;
            tg.showAlert(`${mealTitle}: ${total} ккал\nЗа день: ${today} ккал`);
        } else {
            tg.showAlert(result.message || 'Ошибка сохранения');
        }
    } catch (error) {
        tg.showAlert('Ошибка соединения');
    } finally {
        tg.MainButton.hide();
    }
};
