let summaryCurrentMonth = getCurrentMonthKey();
let summaryDailyGoal = 0;
let summaryDaysByDate = new Map();
let summaryDayBackHandler = null;
let summaryOverlayStartX = 0;
let summaryOverlayStartY = 0;
let summaryOverlayTapReady = false;

async function loadSummaryCalendar(month = getCurrentMonthKey()) {
    summaryCurrentMonth = month;
    const timezoneOffset = getTimezoneOffsetMinutes();

    try {
        const response = await apiFetch(`/api/summary?month=${month}&tz_offset=${timezoneOffset}`);
        const result = await response.json();

        if (!response.ok || result.status !== 'success') {
            tg.showAlert(result.message || result.error || 'Не удалось загрузить сводку');
            return;
        }

        summaryCurrentMonth = result.data.month;
        renderSummaryCalendar(result.data);
    } catch (error) {
        console.error('Ошибка загрузки сводки:', error);
        tg.showAlert('Ошибка загрузки сводки');
    }
}

function getCurrentMonthKey() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function shiftMonth(month, delta) {
    const [year, monthNumber] = month.split('-').map(Number);
    const date = new Date(year, monthNumber - 1 + delta, 1);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function renderSummaryCalendar(data) {
    document.getElementById('summary-month-label').textContent = formatSummaryMonth(data.month);

    summaryDailyGoal = Number(data.daily_goal || 0);
    summaryDaysByDate = new Map((data.days || []).map(day => [day.date, day]));
    const dates = buildMonthDates(data.month);

    document.getElementById('summary-calendar-grid').innerHTML = dates.map(dateInfo => {
        if (!dateInfo.date) {
            return '<div class="calendar-day calendar-day-empty-slot"></div>';
        }

        const day = getSummaryDay(dateInfo.date);

        return renderCalendarDay(dateInfo, day);
    }).join('');
}

function buildMonthDates(month) {
    const [year, monthNumber] = month.split('-').map(Number);
    const firstDay = new Date(year, monthNumber - 1, 1);
    const lastDay = new Date(year, monthNumber, 0);
    const dates = [];
    const firstWeekday = firstDay.getDay() || 7;

    for (let index = 1; index < firstWeekday; index += 1) {
        dates.push({ date: null });
    }

    for (let day = 1; day <= lastDay.getDate(); day += 1) {
        dates.push({
            date: `${year}-${String(monthNumber).padStart(2, '0')}-${String(day).padStart(2, '0')}`,
            day
        });
    }

    return dates;
}

function renderCalendarDay(dateInfo, day) {
    const rawPercentage = Number(day.percentage || 0);
    const visualPercentage = Math.min(Math.max(rawPercentage, 0), 100);
    const calories = Number(day.calories || 0);
    const colorClass = `day-${day.color || 'empty'}`;
    const roundedPercentage = Math.round(rawPercentage);

    return `
        <button class="calendar-day ${colorClass}" type="button" data-date="${escapeHtml(day.date)}">
            <span class="calendar-ring" style="--progress: ${visualPercentage}%">
                <span class="calendar-day-number">${dateInfo.day}</span>
            </span>
            <span class="calendar-day-calories">${calories > 0 ? calories : ''}</span>
            <span class="calendar-day-percent">${calories > 0 ? `${roundedPercentage}%` : ''}</span>
        </button>
    `;
}

function formatSummaryMonth(month) {
    const [year, monthNumber] = month.split('-').map(Number);
    return new Date(year, monthNumber - 1, 1).toLocaleDateString('ru-RU', {
        month: 'long',
        year: 'numeric'
    });
}

document.addEventListener('click', event => {
    const dayButton = event.target.closest('.calendar-day[data-date]');
    if (!dayButton) return;

    openSummaryDaySheet(dayButton.dataset.date);
});

document.getElementById('btn-summary-prev-month').onclick = () => {
    loadSummaryCalendar(shiftMonth(summaryCurrentMonth, -1));
};

document.getElementById('btn-summary-next-month').onclick = () => {
    loadSummaryCalendar(shiftMonth(summaryCurrentMonth, 1));
};

document.querySelector('.summary-day-overlay').addEventListener('pointerdown', event => {
    event.preventDefault();
    event.stopPropagation();
    summaryOverlayStartX = event.clientX;
    summaryOverlayStartY = event.clientY;
    summaryOverlayTapReady = false;
});

document.querySelector('.summary-day-overlay').addEventListener('pointerup', event => {
    event.preventDefault();
    event.stopPropagation();
    const deltaX = Math.abs(event.clientX - summaryOverlayStartX);
    const deltaY = Math.abs(event.clientY - summaryOverlayStartY);

    if (deltaX < 8 && deltaY < 8) {
        summaryOverlayTapReady = true;
    }
});

document.querySelector('.summary-day-overlay').addEventListener('click', event => {
    event.preventDefault();
    event.stopPropagation();

    if (summaryOverlayTapReady) {
        summaryOverlayTapReady = false;
        closeSummaryDaySheet();
    }
});

document.querySelector('.summary-day-panel').addEventListener('pointerdown', event => {
    event.stopPropagation();
});

document.querySelector('.summary-day-panel').addEventListener('click', event => {
    event.stopPropagation();
});

document.getElementById('btn-summary-day-close').onclick = closeSummaryDaySheet;

document.getElementById('summary-day-meals-list').addEventListener('click', event => {
    const mealCard = event.target.closest('.summary-day-meal[data-meal-id]');
    if (!mealCard) return;

    openMealDetail(Number(mealCard.dataset.mealId), {
        parentBackHandler: summaryDayBackHandler,
        keepBodyLockedOnClose: true
    });
});

function getSummaryDay(date) {
    return summaryDaysByDate.get(date) || {
        date,
        calories: 0,
        percentage: 0,
        color: 'empty',
        proteins: 0,
        fats: 0,
        carbs: 0,
        weight: 0,
        meals: []
    };
}

function openSummaryDaySheet(date) {
    const day = getSummaryDay(date);
    renderSummaryDaySheet(day);
    document.getElementById('summary-day-sheet').classList.remove('hidden');
    document.body.classList.add('sheet-open');

    summaryDayBackHandler = closeSummaryDaySheet;
    tg.BackButton.show();
    tg.BackButton.onClick(summaryDayBackHandler);
}

function closeSummaryDaySheet() {
    document.getElementById('summary-day-sheet').classList.add('hidden');
    document.body.classList.remove('sheet-open');

    if (summaryDayBackHandler) {
        tg.BackButton.offClick(summaryDayBackHandler);
        summaryDayBackHandler = null;
    }

    tg.BackButton.hide();
}

function renderSummaryDaySheet(day) {
    const rawPercentage = Number(day.percentage || 0);
    const visualPercentage = Math.min(Math.max(rawPercentage, 0), 100);
    const calories = Number(day.calories || 0);
    const colorClass = `day-${day.color || 'empty'}`;
    const ring = document.getElementById('summary-day-ring');

    ring.className = `summary-day-ring ${colorClass}`;
    ring.style.setProperty('--progress', `${visualPercentage}%`);

    document.getElementById('summary-day-date').textContent = formatSummaryDate(day.date);
    document.getElementById('summary-day-percent').textContent = `${Math.round(rawPercentage)}%`;
    document.getElementById('summary-day-calories').textContent = calories;
    document.getElementById('summary-day-goal').textContent = `из ${summaryDailyGoal} ккал`;
    document.getElementById('summary-day-proteins').textContent = formatMacro(day.proteins);
    document.getElementById('summary-day-fats').textContent = formatMacro(day.fats);
    document.getElementById('summary-day-carbs').textContent = formatMacro(day.carbs);
    document.getElementById('summary-day-meals-list').innerHTML = renderSummaryDayMeals(day.meals || []);
}

function renderSummaryDayMeals(meals) {
    if (meals.length === 0) {
        return '<p class="summary-day-empty">Приемов пищи нет</p>';
    }

    return meals.map(meal => `
        <div class="summary-day-meal" data-meal-id="${Number(meal.id || 0)}">
            <div>
                <span class="summary-day-meal-time">${escapeHtml(meal.time || '')}</span>
                <strong>${escapeHtml(meal.description || 'Прием пищи')}</strong>
                <small>${formatSummaryMealMeta(meal)}</small>
            </div>
            <span class="summary-day-meal-calories">${Number(meal.calories || 0)} ккал</span>
            <span class="summary-day-meal-arrow">›</span>
        </div>
    `).join('');
}

function formatSummaryMealMeta(meal) {
    const parts = [];

    if (meal.weight) {
        parts.push(`${Number(meal.weight)} г`);
    }

    parts.push(`Б ${formatMacro(meal.proteins)}`);
    parts.push(`Ж ${formatMacro(meal.fats)}`);
    parts.push(`У ${formatMacro(meal.carbs)}`);

    return escapeHtml(parts.join(' · '));
}

function formatSummaryDate(date) {
    const [year, monthNumber, day] = date.split('-').map(Number);
    return new Date(year, monthNumber - 1, day).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
}
