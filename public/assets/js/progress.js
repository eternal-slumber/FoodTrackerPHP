// Загрузка прогресса
async function loadProgress() {
    try {
        const response = await apiFetch(`/api/progress?tz_offset=${getTimezoneOffsetMinutes()}`);
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
    const macroGoals = data.macro_goals || {};
    const todayMacros = data.today_macros || {};
    const macroPercentages = data.macro_percentages || {};

    document.getElementById('daily-goal').innerText = dailyGoal;
    document.getElementById('today-calories').innerText = todaySum;
    document.getElementById('progress-percentage').innerText = `${Math.round(percentage)}%`;
    document.getElementById('progress-fill').style.width = `${Math.min(percentage, 100)}%`;

    updateMacroProgress('proteins', todayMacros.proteins, macroGoals.proteins_goal, macroPercentages.proteins);
    updateMacroProgress('fats', todayMacros.fats, macroGoals.fats_goal, macroPercentages.fats);
    updateMacroProgress('carbs', todayMacros.carbs, macroGoals.carbs_goal, macroPercentages.carbs);
}

function updateMacroProgress(key, currentValue, goalValue, percentage) {
    const current = Number(currentValue || 0);
    const goal = Number(goalValue || 0);
    const progress = Number(percentage || 0);

    document.getElementById(`today-${key}`).innerText = formatMacro(current);
    document.getElementById(`goal-${key}`).innerText = formatMacro(goal);
    document.getElementById(`${key}-progress-fill`).style.width = `${Math.min(Math.max(progress, 0), 100)}%`;
}
