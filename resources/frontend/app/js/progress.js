let progressLoadId = 0;

// Загрузка прогресса
async function loadProgress() {
    const loadId = ++progressLoadId;
    setHomeProgressState('loading');

    try {
        const result = await apiRequestJson(`/api/progress?tz_offset=${getTimezoneOffsetMinutes()}`);

        if (loadId !== progressLoadId) {
            return false;
        }

        updateProgressUI(result.data);
        setHomeProgressState('ready');
        return true;
    } catch (error) {
        console.error('Ошибка загрузки прогресса:', error);

        if (loadId === progressLoadId) {
            setHomeProgressState('error', error?.message || 'Не удалось загрузить дневной прогресс');
        }

        return false;
    }
}

function setHomeProgressState(state, message = '') {
    const card = document.getElementById('home-progress-card');
    const feedbackText = document.getElementById('home-progress-feedback-text');
    const retryButton = document.getElementById('btn-retry-progress');

    if (!card || !feedbackText || !retryButton) {
        return;
    }

    card.dataset.state = state;
    card.setAttribute('aria-busy', String(state === 'loading'));
    feedbackText.textContent = state === 'loading'
        ? 'Загружаем дневной прогресс...'
        : message;
    retryButton.classList.toggle('hidden', state !== 'error');
}

document.getElementById('btn-retry-progress')?.addEventListener('click', loadProgress);

// Обновление UI прогресса
function updateProgressUI(data) {
    const dailyGoal = data.daily_goal || 0;
    const todaySum = data.today_sum || 0;
    const percentage = data.percentage || 0;
    const macroGoals = data.macro_goals || {};
    const todayMacros = data.today_macros || {};
    const macroPercentages = data.macro_percentages || {};

    setElementText(
        'profile-macro-goals',
        `Б ${formatMacro(macroGoals.proteins_goal)} · Ж ${formatMacro(macroGoals.fats_goal)} · У ${formatMacro(macroGoals.carbs_goal)}`
    );

    document.getElementById('daily-goal').innerText = dailyGoal;
    document.getElementById('today-calories').innerText = todaySum;
    document.getElementById('progress-percentage').innerText = `${Math.round(percentage)}%`;
    document.getElementById('remaining-calories').innerText = Math.max(0, Math.round(Number(data.remaining_calories || 0)));
    document.getElementById('progress-fill').style.setProperty(
        '--day-progress',
        Math.min(Math.max(Number(percentage), 0), 100)
    );

    updateMacroProgress('proteins', todayMacros.proteins, macroGoals.proteins_goal, macroPercentages.proteins);
    updateMacroProgress('fats', todayMacros.fats, macroGoals.fats_goal, macroPercentages.fats);
    updateMacroProgress('carbs', todayMacros.carbs, macroGoals.carbs_goal, macroPercentages.carbs);
}

function updateMacroProgress(key, currentValue, goalValue, percentage) {
    const current = Number(currentValue || 0);
    const goal = Number(goalValue || 0);
    const calculatedProgress = goal > 0 ? (current / goal) * 100 : 0;
    const apiProgress = Number(percentage);
    const progress = goal > 0
        ? calculatedProgress
        : (Number.isFinite(apiProgress) ? apiProgress : 0);
    const card = document.getElementById(`${key}-progress-fill`);

    document.getElementById(`today-${key}`).innerText = formatMacro(current);
    document.getElementById(`goal-${key}`).innerText = formatMacro(goal);
    card.style.setProperty('--macro-progress', `${Math.min(Math.max(progress, 0), 100)}%`);
    card.setAttribute('aria-valuenow', String(Math.max(current, 0)));
    card.setAttribute('aria-valuemax', String(Math.max(goal, 0)));
    card.classList.toggle('is-over-goal', goal > 0 && current > goal);
    document.getElementById(`${key}-warning`)?.classList.toggle('hidden', goal <= 0 || current <= goal);
}
