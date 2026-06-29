let dailyInsightLoadId = 0;
let dailyInsightRefreshPromise = null;
let dailyInsightHasContent = false;
let dailyInsightNeedsFreshContent = false;
let dailyInsightRefreshQueued = false;
let dailyInsightRevision = 0;

async function loadDailyNutritionInsight() {
    if (dailyInsightNeedsFreshContent) {
        return refreshDailyNutritionInsight();
    }

    const loadId = ++dailyInsightLoadId;

    if (!dailyInsightHasContent) {
        setDailyInsightLoading();
    }

    try {
        const result = await apiRequestJson(`/api/daily-insight?tz_offset=${getTimezoneOffsetMinutes()}`);

        if (loadId !== dailyInsightLoadId) {
            return;
        }

        renderDailyNutritionInsight(result.data);

        if (result.data?.state === 'missing' || result.data?.state === 'stale') {
            refreshDailyNutritionInsight();
        }
    } catch (error) {
        if (loadId === dailyInsightLoadId) {
            renderDailyInsightError(error?.message);
        }
    }
}

function refreshDailyNutritionInsight({ invalidate = false } = {}) {
    if (invalidate) {
        dailyInsightRevision += 1;
        dailyInsightLoadId += 1;
        dailyInsightNeedsFreshContent = true;
        dailyInsightHasContent = false;
        setDailyInsightLoading('Обновляем рекомендацию после нового приёма...');
    }

    if (dailyInsightRefreshPromise) {
        if (invalidate) {
            dailyInsightRefreshQueued = true;
        }

        return dailyInsightRefreshPromise;
    }

    const refreshRevision = dailyInsightRevision;

    if (!dailyInsightHasContent && !dailyInsightNeedsFreshContent) {
        setDailyInsightLoading('Анализируем сегодняшний рацион...');
    }

    dailyInsightRefreshPromise = (async () => {
        try {
            const result = await apiRequestJson(
                `/api/daily-insight/refresh?tz_offset=${getTimezoneOffsetMinutes()}`,
                {
                    method: 'POST',
                    timeoutMs: API_TIMEOUT.AI
                }
            );

            if (refreshRevision === dailyInsightRevision) {
                dailyInsightNeedsFreshContent = false;
                renderDailyNutritionInsight(result.data);
            }

            return result.data;
        } catch (error) {
            if (refreshRevision === dailyInsightRevision) {
                dailyInsightNeedsFreshContent = false;
                renderDailyInsightError(error?.message);
            }

            return null;
        } finally {
            dailyInsightRefreshPromise = null;

            if (dailyInsightRefreshQueued) {
                dailyInsightRefreshQueued = false;
                refreshDailyNutritionInsight();
            }
        }
    })();

    return dailyInsightRefreshPromise;
}

function renderDailyNutritionInsight(data) {
    const state = data?.state || 'missing';
    const insight = data?.insight;

    if (state === 'empty') {
        dailyInsightHasContent = false;
        setDailyInsightState('empty');
        setDailyInsightText({
            shortSummary: 'Добавьте первый приём — после него появится персональная рекомендация.',
            dayAnalysis: 'Пока недостаточно данных для анализа питания за сегодня.',
            nextMealAdvice: '',
            nextMealType: '',
            targetCalories: 0,
            foods: []
        });
        return;
    }

    if (!insight) {
        dailyInsightHasContent = false;
        setDailyInsightLoading('Формируем рекомендацию по сегодняшним приёмам...');
        return;
    }

    const nextMeal = insight.next_meal || {};
    dailyInsightHasContent = true;
    setDailyInsightState(state);
    setDailyInsightText({
        shortSummary: insight.short_summary || 'Рекомендация обновлена.',
        dayAnalysis: insight.day_analysis || '',
        nextMealAdvice: nextMeal.advice || '',
        nextMealType: nextMeal.type || 'следующий приём',
        targetCalories: Number(nextMeal.target_calories || 0),
        foods: Array.isArray(nextMeal.foods) ? nextMeal.foods : []
    });
}

function setDailyInsightText({
    shortSummary,
    dayAnalysis,
    nextMealAdvice,
    nextMealType,
    foods
}) {
    setDailyInsightElementText('home-ai-insight-short', shortSummary);
    setDailyInsightElementText('summary-ai-day-analysis', dayAnalysis);
    setDailyInsightElementText(
        'summary-ai-next-meal',
        nextMealAdvice
            ? `${nextMealType ? `${capitalizeDailyInsightText(nextMealType)}: ` : ''}${nextMealAdvice}`
            : ''
    );
    renderDailyInsightFoods(foods);
}

function setDailyInsightLoading(message = 'Загружаем рекомендацию...') {
    setDailyInsightState('loading');
    setDailyInsightElementText('home-ai-insight-short', message);
    setDailyInsightElementText('summary-ai-day-analysis', message);
    setDailyInsightElementText('summary-ai-next-meal', '');
    renderDailyInsightFoods([]);
}

function renderDailyInsightError(message = '') {
    setDailyInsightState('error');

    if (dailyInsightHasContent) {
        return;
    }

    const safeMessage = message || 'AI-рекомендация временно недоступна';
    setDailyInsightElementText('home-ai-insight-short', safeMessage);
    setDailyInsightElementText('summary-ai-day-analysis', safeMessage);
    setDailyInsightElementText('summary-ai-next-meal', 'Приёмы пищи сохранены — ошибка AI на них не влияет.');
    renderDailyInsightFoods([]);
}

function setDailyInsightState(state) {
    document.getElementById('home-ai-insight-card')?.setAttribute('data-insight-state', state);
    document.getElementById('summary-ai-insight-card')?.setAttribute('data-insight-state', state);
    document.getElementById('btn-retry-daily-insight')?.classList.toggle('hidden', state !== 'error');
}

function renderDailyInsightFoods(foods) {
    const container = document.getElementById('summary-ai-tags');
    if (!container) {
        return;
    }

    container.replaceChildren(...foods.slice(0, 5).map(food => {
        const item = document.createElement('span');
        item.textContent = String(food);
        return item;
    }));
    container.classList.toggle('hidden', container.childElementCount === 0);
}

function setDailyInsightElementText(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = String(value || '');
    }
}

function capitalizeDailyInsightText(value) {
    const text = String(value || '').trim();
    return text ? text.charAt(0).toUpperCase() + text.slice(1) : '';
}

document.getElementById('btn-retry-daily-insight')?.addEventListener('click', () => {
    refreshDailyNutritionInsight();
});

document.getElementById('home-ai-insight-card')?.addEventListener('click', () => {
    showScreen('summary');
});

document.getElementById('home-ai-insight-card')?.addEventListener('keydown', event => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        showScreen('summary');
    }
});
