let summaryLoadId = 0;

async function loadSummary() {
    const loadId = ++summaryLoadId;
    const card = document.querySelector('#screen-summary .summary-progress-card');

    updateSummaryIntroText();
    card?.classList.add('is-loading');
    card?.classList.remove('is-error');

    try {
        const result = await apiRequestJson(`/api/progress?tz_offset=${getTimezoneOffsetMinutes()}`);

        if (loadId !== summaryLoadId) {
            return;
        }

        renderSummaryProgress(result.data);
    } catch (error) {
        if (loadId !== summaryLoadId) {
            return;
        }

        console.error('Ошибка загрузки прогресса сводки:', error);
        card?.classList.add('is-error');
    } finally {
        if (loadId === summaryLoadId) {
            card?.classList.remove('is-loading');
        }
    }
}

function updateSummaryIntroText() {
    const intro = document.getElementById('summary-intro-text');
    if (!intro) {
        return;
    }

    const verb = userData?.gender === 'female' ? 'начала' : 'начал';
    intro.textContent = `Ты хорошо ${verb}. Пока данных мало, но уже виден первый прогресс.`;
}

async function refreshSummary() {
    await loadSummary();
}

function renderSummaryProgress(data) {
    const dailyGoal = Math.max(0, Number(data.daily_goal || 0));
    const todayCalories = Math.max(0, Number(data.today_sum || 0));
    const remainingCalories = Math.max(0, Number(data.remaining_calories || 0));
    const calculatedPercentage = dailyGoal > 0 ? (todayCalories / dailyGoal) * 100 : 0;
    const apiPercentage = Number(data.percentage);
    const percentage = Number.isFinite(apiPercentage) ? apiPercentage : calculatedPercentage;
    const roundedPercentage = Math.max(0, Math.round(percentage));
    const visualPercentage = Math.min(Math.max(percentage, 0), 100);

    document.getElementById('summary-progress-percent').textContent = `${roundedPercentage}%`;
    document.getElementById('summary-today-calories').textContent = String(Math.round(todayCalories));
    document.getElementById('summary-daily-goal').textContent = String(Math.round(dailyGoal));
    document.getElementById('summary-remaining-calories').textContent = String(Math.round(remainingCalories));
    document.getElementById('summary-progress-fill').style.width = `${visualPercentage}%`;
    renderSummaryStreak(data.streak || {});
    renderSummaryMacroBalance(data);
}

function renderSummaryMacroBalance(data) {
    const goals = data.macro_goals || {};
    const consumed = data.today_macros || {};

    setSummaryMacroBalance('proteins', consumed.proteins, goals.proteins_goal);
    setSummaryMacroBalance('fats', consumed.fats, goals.fats_goal);
    setSummaryMacroBalance('carbs', consumed.carbs, goals.carbs_goal);
}

function setSummaryMacroBalance(key, consumedValue, goalValue) {
    const element = document.getElementById(`summary-balance-${key}`);
    const consumed = Math.max(0, Number(consumedValue || 0));
    const goal = Math.max(0, Number(goalValue || 0));

    if (!element) {
        return;
    }

    const status = getSummaryMacroBalanceStatus(consumed, goal);
    element.textContent = status.label;
    element.dataset.balance = status.key;
}

function getSummaryMacroBalanceStatus(consumed, goal) {
    if (goal <= 0) {
        return { key: 'empty', label: 'нет данных' };
    }

    const percentage = (consumed / goal) * 100;
    if (percentage < 80) {
        return { key: 'low', label: 'мало' };
    }

    if (percentage > 105) {
        return { key: 'over', label: 'выше' };
    }

    return { key: 'ok', label: 'ок' };
}

function renderSummaryStreak(streak) {
    const card = document.getElementById('summary-streak-card');
    const value = document.getElementById('summary-streak-value');
    const message = document.getElementById('summary-streak-message');
    const days = Math.max(0, Number(streak.current_days || 0));
    const todayCompleted = Boolean(streak.today_completed);
    const streakText = `${days} ${getSummaryStreakDayLabel(days)} подряд`;

    card.dataset.streakStage = getSummaryStreakStage(days);
    card.setAttribute('aria-label', `Серия питания: ${streakText}`);
    value.textContent = streakText;
    message.textContent = getSummaryStreakMessage(days, todayCompleted);
}

function getSummaryStreakDayLabel(days) {
    const lastDigit = days % 10;
    const lastTwoDigits = days % 100;

    if (lastDigit === 1 && lastTwoDigits !== 11) {
        return 'день';
    }

    if (lastDigit >= 2 && lastDigit <= 4 && (lastTwoDigits < 12 || lastTwoDigits > 14)) {
        return 'дня';
    }

    return 'дней';
}

function getSummaryStreakStage(days) {
    if (days <= 0) return 'seed';
    if (days <= 2) return 'sprout';
    if (days <= 6) return 'leafy';
    if (days <= 13) return 'plant';
    if (days <= 29) return 'large';
    return 'tree';
}

function getSummaryStreakMessage(days, todayCompleted) {
    if (!todayCompleted) {
        return days > 0
            ? `Добавь приём сегодня, чтобы продолжить серию в ${days} ${getSummaryStreakDayLabel(days)}.`
            : 'Добавь первый приём сегодня, чтобы начать серию.';
    }

    if (days <= 2) return 'Росток привычки уже появился. Продолжай завтра.';
    if (days <= 6) return 'Росток дал листья. Продолжай вести питание каждый день.';
    if (days <= 13) return 'Привычка крепнет — не прерывай серию.';
    if (days <= 29) return 'Серия стала заметной. Поддержи её завтра.';
    return 'Привычка выросла. Продолжай держать ритм.';
}
