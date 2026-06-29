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
