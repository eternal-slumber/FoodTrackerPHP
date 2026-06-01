// Обновление UI данными пользователя
function updateUserUI() {
    if (!userData) return;

    document.getElementById('user-body-params').innerText = formatBodyParams(userData);

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

function setElementText(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.innerText = value;
    }
}

function formatBodyParams(data) {
    const age = data.age ? `${data.age} лет` : null;
    const height = data.height ? `${data.height} см` : null;
    const weight = data.weight ? `${data.weight} кг` : null;
    const gender = data.gender === 'male' ? 'мужчина' : data.gender === 'female' ? 'женщина' : null;

    return [age, height, weight, gender].filter(Boolean).join(' · ') || 'Параметры не указаны';
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

function collectProfileFormData() {
    const age = document.getElementById('settings-input-age').value;
    const height = document.getElementById('settings-input-height').value;
    const weight = document.getElementById('settings-input-weight').value;
    const gender = document.getElementById('settings-input-gender').value;
    const activityLevel = document.querySelector('input[name="settings_activity_level"]:checked')?.value || 'medium';
    const goal = document.querySelector('input[name="settings_goal"]:checked')?.value || 'maintenance';

    return {
        age,
        height,
        weight,
        gender,
        activity_level: activityLevel,
        goal
    };
}

function isProfileFormDirty() {
    if (!userData) return false;

    const formData = collectProfileFormData();

    return Number(formData.age) !== Number(userData.age)
        || Number(formData.height) !== Number(userData.height)
        || Number(formData.weight) !== Number(userData.weight)
        || formData.gender !== userData.gender
        || formData.activity_level !== (userData.activity_level || 'medium')
        || formData.goal !== (userData.goal || 'maintenance');
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

document.getElementById('btn-start').onclick = () => {
    startRegisterFlow();
};

const registerSteps = {
    1: document.getElementById('register-step-1'),
    2: document.getElementById('register-step-2'),
    3: document.getElementById('register-step-3')
};

const registerStepIndicators = document.querySelectorAll('[data-register-step-indicator]');
const registerFirstStepFields = [
    document.getElementById('age'),
    document.getElementById('height'),
    document.getElementById('weight'),
    document.getElementById('gender')
];
const btnNext1 = document.getElementById('btn-next-1');
let activeRegisterStep = 1;
let isRegisterStepTransitioning = false;

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

        indicator.classList.toggle('active', step === activeStep);
        indicator.classList.toggle('completed', step < activeStep);
    });
}

document.getElementById('btn-next-1').onclick = () => {
    const age = document.getElementById('age').value;
    const height = document.getElementById('height').value;
    const weight = document.getElementById('weight').value;
    const gender = document.getElementById('gender').value;

    if (!age || !height || !weight || !gender) {
        tg.showAlert('Пожалуйста, заполните все поля');
        return;
    }

    showRegisterStep(2);
};

function updateRegisterFirstStepState() {
    const isComplete = registerFirstStepFields.every(field => String(field.value).trim() !== '');

    btnNext1.disabled = !isComplete;
}

registerFirstStepFields.forEach(field => {
    field.addEventListener('input', updateRegisterFirstStepState);
    field.addEventListener('change', updateRegisterFirstStepState);
});

updateRegisterFirstStepState();

document.getElementById('btn-next-2').onclick = () => {
    showRegisterStep(3);
};

document.getElementById('btn-back-2').onclick = () => {
    showRegisterStep(1);
};

document.getElementById('btn-back-3').onclick = () => {
    showRegisterStep(2);
};

document.getElementById('btn-save').onclick = async () => {
    const age = document.getElementById('age').value;
    const height = document.getElementById('height').value;
    const weight = document.getElementById('weight').value;
    const gender = document.getElementById('gender').value;
    const activityLevel = document.querySelector('input[name="activity_level"]:checked')?.value || 'medium';
    const goal = document.querySelector('input[name="goal"]:checked')?.value || 'maintenance';

    const data = {
        age: parseInt(age),
        height: parseInt(height),
        weight: parseFloat(weight),
        gender,
        activity_level: activityLevel,
        goal
    };

    tg.MainButton.setText('Регистрация...').show();

    try {
        const response = await apiFetch('/api/register', {
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
            tg.showAlert(Object.values(result.errors).join('\n'));
        } else {
            tg.showAlert(result.message || 'Ошибка при регистрации');
        }
    } catch (error) {
        tg.showAlert('Ошибка соединения с сервером');
    } finally {
        tg.MainButton.hide();
    }
};

document.getElementById('btn-delete-profile').onclick = async () => {
    const confirmed = confirm('Вы уверены, что хотите удалить профиль? Это действие нельзя отменить.');

    if (!confirmed) {
        return;
    }

    try {
        const response = await apiFetch('/api/delete-profile', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
        });
        const result = await response.json();

        if (result.status === 'success') {
            tg.showAlert('Профиль успешно удален');
            userData = null;
            showScreen('welcome');
        } else {
            tg.showAlert(result.message || 'Ошибка при удалении профиля');
        }
    } catch (error) {
        tg.showAlert('Ошибка соединения с сервером');
    }
};

document.getElementById('btn-edit-profile').onclick = () => {
    fillSettingsForm();
    showScreen('profileEdit');
};

const themeChoiceButtons = document.querySelectorAll('[data-theme-choice]');

function updateThemeControls() {
    const currentTheme = window.appTheme?.get?.() || 'system';
    const themeControl = themeChoiceButtons[0]?.closest('.theme-segmented');

    if (themeControl) {
        themeControl.dataset.activeTheme = currentTheme;
    }

    themeChoiceButtons.forEach(button => {
        button.classList.toggle('active', button.dataset.themeChoice === currentTheme);
    });
}

themeChoiceButtons.forEach(button => {
    button.addEventListener('click', () => {
        window.appTheme?.set?.(button.dataset.themeChoice);
        updateThemeControls();
    });
});

updateThemeControls();

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

document.getElementById('settings-profile-form').onsubmit = async event => {
    event.preventDefault();
    await saveProfileChanges();
};

async function saveProfileChanges() {
    const formData = collectProfileFormData();

    if (!formData.age || !formData.height || !formData.weight || !formData.gender) {
        tg.showAlert('Заполните возраст, рост, вес и пол');
        return false;
    }

    const payload = {
        age: parseInt(formData.age, 10),
        height: parseInt(formData.height, 10),
        weight: parseFloat(formData.weight),
        gender: formData.gender,
        activity_level: formData.activity_level,
        goal: formData.goal
    };

    const saveButton = document.getElementById('btn-save-profile');
    saveButton.disabled = true;
    saveButton.textContent = 'Сохраняю...';

    try {
        const response = await apiFetch('/api/profile', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();

        if (!response.ok || result.status !== 'success') {
            const errorMessages = result.errors ? Object.values(result.errors).join('\n') : null;
            tg.showAlert(errorMessages || result.message || result.error || 'Не удалось сохранить профиль');
            return false;
        }

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
        showScreen('settings');
        tg.showAlert(`Профиль обновлен. Новая норма: ${result.daily_goal} ккал`);
        return true;
    } catch (error) {
        tg.showAlert('Ошибка соединения с сервером');
        return false;
    } finally {
        saveButton.disabled = false;
        saveButton.textContent = 'Сохранить изменения';
    }
}
