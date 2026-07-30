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

const trainerShareCard = document.getElementById('profile-trainer-share-card');
const trainerShareStatus = document.getElementById('profile-trainer-share-status');
const trainerShareCreateButton = document.getElementById('btn-create-trainer-share');
const trainerShareActive = document.getElementById('profile-trainer-share-active');
const trainerShareUrl = document.getElementById('profile-trainer-share-url');
const trainerShareConfig = document.getElementById('profile-trainer-share-config');
const trainerShareCopyStatus = document.getElementById('profile-trainer-share-copy-status');
const trainerShareDefaultVisibility = {
    meals: true,
    nutrition: true,
    history: true,
    ai_analysis: true,
    profile_params: false
};
let trainerShareData = {
    active: false,
    duration: '30',
    visibility: { ...trainerShareDefaultVisibility }
};
let trainerShareCopyStatusTimer = null;

async function loadTrainerShare() {
    if (!trainerShareCard) return;

    setTrainerShareBusy(true);
    try {
        const result = await apiRequestJson('/api/trainer-share');
        renderTrainerShare(result.data);
    } catch (error) {
        trainerShareCard.dataset.state = 'error';
        trainerShareStatus.textContent = 'Ошибка';
    } finally {
        setTrainerShareBusy(false);
    }
}

function renderTrainerShare(data = {}) {
    const active = Boolean(data.active);
    trainerShareData = {
        ...trainerShareData,
        ...data,
        active,
        duration: data.duration || trainerShareData.duration || '30',
        visibility: { ...trainerShareDefaultVisibility, ...(data.visibility || {}) }
    };
    trainerShareCard.dataset.state = active ? 'active' : 'inactive';
    trainerShareStatus.textContent = active ? 'Включён' : 'Выключен';
    trainerShareCreateButton.classList.toggle('hidden', active);
    trainerShareActive.classList.toggle('hidden', !active);
    trainerShareConfig.classList.add('hidden');
    showTrainerShareCopyStatus('');

    if (!active) return;

    trainerShareUrl.value = data.url ? new URL(data.url, window.location.origin).href : '';
    const expiresAt = data.expires_at
        ? new Date(String(data.expires_at).replace(' ', 'T') + 'Z')
        : null;
    document.getElementById('profile-trainer-share-expiry').textContent = expiresAt === null
        ? 'Ссылка активна без ограничения срока.'
        : `Ссылка активна до ${expiresAt.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' })}.`;
    document.getElementById('profile-trainer-share-scope').textContent =
        `Доступ: ${trainerShareVisibilityLabels(trainerShareData.visibility).join(', ')}.`;
}

function setTrainerShareBusy(busy) {
    trainerShareCreateButton.disabled = busy;
    document.getElementById('btn-copy-trainer-share').disabled = busy;
    document.getElementById('btn-configure-trainer-share').disabled = busy;
    document.getElementById('btn-revoke-trainer-share').disabled = busy;
    document.getElementById('btn-save-trainer-share').disabled = busy;
    document.getElementById('btn-cancel-trainer-share').disabled = busy;
}

function openTrainerShareConfig() {
    const visibility = trainerShareData.visibility || trainerShareDefaultVisibility;
    document.querySelectorAll('input[name="trainer-share-duration"]').forEach(input => {
        input.checked = input.value === (trainerShareData.duration || '30');
    });
    document.querySelectorAll('[data-trainer-visibility]').forEach(input => {
        input.checked = Boolean(visibility[input.dataset.trainerVisibility]);
    });
    document.getElementById('btn-save-trainer-share').textContent = trainerShareData.active
        ? 'Сохранить'
        : 'Создать ссылку';
    document.getElementById('profile-trainer-config-error').classList.add('hidden');
    trainerShareCreateButton.classList.add('hidden');
    trainerShareActive.classList.add('hidden');
    trainerShareConfig.classList.remove('hidden');
}

function trainerShareFormData() {
    const duration = document.querySelector('input[name="trainer-share-duration"]:checked')?.value || '30';
    const visibility = {};
    document.querySelectorAll('[data-trainer-visibility]').forEach(input => {
        visibility[input.dataset.trainerVisibility] = input.checked;
    });
    return { duration, visibility };
}

function trainerShareVisibilityLabels(visibility) {
    const labels = {
        meals: 'приёмы пищи',
        nutrition: 'калории и БЖУ',
        history: 'история',
        ai_analysis: 'AI-анализ',
        profile_params: 'параметры профиля'
    };
    return Object.keys(labels).filter(key => visibility[key]).map(key => labels[key]);
}

trainerShareCreateButton?.addEventListener('click', openTrainerShareConfig);
document.getElementById('btn-configure-trainer-share')?.addEventListener('click', openTrainerShareConfig);

document.getElementById('btn-cancel-trainer-share')?.addEventListener('click', () => {
    renderTrainerShare(trainerShareData);
});

document.getElementById('btn-save-trainer-share')?.addEventListener('click', async () => {
    const settings = trainerShareFormData();
    if (!Object.values(settings.visibility).some(Boolean)) {
        document.getElementById('profile-trainer-config-error').classList.remove('hidden');
        return;
    }

    setTrainerShareBusy(true);
    try {
        const result = await apiRequestJson('/api/trainer-share', {
            method: trainerShareData.active ? 'PATCH' : 'POST',
            json: {
                ...settings,
                timezone_offset: getTimezoneOffsetMinutes()
            }
        });
        renderTrainerShare(result.data);
    } catch (error) {
        tg.showAlert(error?.message || 'Не удалось сохранить доступ');
    } finally {
        setTrainerShareBusy(false);
    }
});

document.getElementById('btn-copy-trainer-share')?.addEventListener('click', async () => {
    try {
        await navigator.clipboard.writeText(trainerShareUrl.value);
        showTrainerShareCopyStatus('Ссылка скопирована');
    } catch (error) {
        trainerShareUrl.select();
        const copied = document.execCommand('copy');
        showTrainerShareCopyStatus(copied ? 'Ссылка скопирована' : 'Не удалось скопировать ссылку');
    }
});

function showTrainerShareCopyStatus(message) {
    clearTimeout(trainerShareCopyStatusTimer);
    trainerShareCopyStatus.textContent = message;
    if (message) {
        trainerShareCopyStatusTimer = setTimeout(() => {
            trainerShareCopyStatus.textContent = '';
        }, 3000);
    }
}

document.getElementById('btn-revoke-trainer-share')?.addEventListener('click', async () => {
    setTrainerShareBusy(true);
    try {
        await apiRequestJson('/api/trainer-share', { method: 'DELETE' });
        renderTrainerShare({ active: false });
    } catch (error) {
        tg.showAlert(error?.message || 'Не удалось отключить доступ');
    } finally {
        setTrainerShareBusy(false);
    }
});

const mealRemindersToggle = document.getElementById('profile-meal-reminders-toggle');
const eveningSummaryToggle = document.getElementById('profile-evening-summary-toggle');
const eveningSummaryTime = document.getElementById('profile-evening-summary-time');
let eveningSummarySettings = { enabled: false, time: '21:00' };

async function loadReminderSettings() {
    if (!mealRemindersToggle) return;

    mealRemindersToggle.disabled = true;

    try {
        const result = await apiRequestJson('/api/reminder-settings');
        mealRemindersToggle.checked = Boolean(result.data?.enabled);
    } catch (error) {
        tg.showAlert(error?.message || 'Не удалось загрузить настройки уведомлений');
    } finally {
        mealRemindersToggle.disabled = false;
    }
}

mealRemindersToggle?.addEventListener('change', async () => {
    const previousValue = !mealRemindersToggle.checked;
    mealRemindersToggle.disabled = true;

    try {
        const result = await apiRequestJson('/api/reminder-settings', {
            method: 'PUT',
            json: { enabled: mealRemindersToggle.checked }
        });
        mealRemindersToggle.checked = Boolean(result.data?.enabled);
    } catch (error) {
        mealRemindersToggle.checked = previousValue;
        tg.showAlert(error?.message || 'Не удалось сохранить настройки уведомлений');
    } finally {
        mealRemindersToggle.disabled = false;
    }
});

async function loadEveningSummarySettings() {
    if (!eveningSummaryToggle || !eveningSummaryTime) return;

    setEveningSummaryControlsDisabled(true);

    try {
        const result = await apiRequestJson('/api/evening-summary-settings');
        renderEveningSummarySettings(result.data);
    } catch (error) {
        tg.showAlert(error?.message || 'Не удалось загрузить настройки вечерней сводки');
    } finally {
        setEveningSummaryControlsDisabled(false);
    }
}

async function saveEveningSummarySettings(previousSettings) {
    setEveningSummaryControlsDisabled(true);

    try {
        const result = await apiRequestJson('/api/evening-summary-settings', {
            method: 'PUT',
            json: {
                enabled: eveningSummaryToggle.checked,
                time: eveningSummaryTime.value
            }
        });
        renderEveningSummarySettings(result.data);
    } catch (error) {
        renderEveningSummarySettings(previousSettings);
        tg.showAlert(error?.message || 'Не удалось сохранить настройки вечерней сводки');
    } finally {
        setEveningSummaryControlsDisabled(false);
    }
}

function renderEveningSummarySettings(settings = {}) {
    eveningSummarySettings = {
        enabled: Boolean(settings.enabled),
        time: settings.time || '21:00'
    };
    eveningSummaryToggle.checked = eveningSummarySettings.enabled;
    eveningSummaryTime.value = eveningSummarySettings.time;
    eveningSummaryTime.disabled = !eveningSummaryToggle.checked;
}

function setEveningSummaryControlsDisabled(disabled) {
    eveningSummaryToggle.disabled = disabled;
    eveningSummaryTime.disabled = disabled || !eveningSummaryToggle.checked;
}

eveningSummaryToggle?.addEventListener('change', () => {
    const previousSettings = { ...eveningSummarySettings };
    eveningSummaryTime.disabled = !eveningSummaryToggle.checked;
    saveEveningSummarySettings(previousSettings);
});

eveningSummaryTime?.addEventListener('change', () => {
    saveEveningSummarySettings({ ...eveningSummarySettings });
});

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
        await apiRequestJson('/api/profile', {
            method: 'DELETE',
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
            method: 'PATCH',
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
