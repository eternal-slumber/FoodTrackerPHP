let summaryCurrentMonth = getCurrentMonthKey();
let summaryDailyGoal = 0;
let summaryDaysByDate = new Map();
let summaryDayBackHandler = null;
let summaryOverlayStartX = 0;
let summaryOverlayStartY = 0;
let summaryOverlayTapReady = false;
let summaryMonthLoadId = 0;
let summaryMonthAnimationId = 0;
let summaryMonthIsLoading = false;

async function loadSummaryCalendar(month = getCurrentMonthKey(), options = {}) {
    const loadId = ++summaryMonthLoadId;
    const direction = Number(options.direction || 0);
    const timezoneOffset = getTimezoneOffsetMinutes();
    let keepsButtonsDisabledForAnimation = false;
    setSummaryMonthButtonsDisabled(true);

    try {
        const response = await apiFetch(`/api/summary?month=${month}&tz_offset=${timezoneOffset}`);
        const result = await response.json();

        if (loadId !== summaryMonthLoadId) {
            return;
        }

        if (!response.ok || result.status !== 'success') {
            tg.showAlert(result.message || result.error || 'Не удалось загрузить сводку');
            return;
        }

        summaryCurrentMonth = result.data.month;
        keepsButtonsDisabledForAnimation = direction !== 0;
        renderSummaryCalendar(result.data, { direction });
    } catch (error) {
        if (loadId !== summaryMonthLoadId) {
            return;
        }

        console.error('Ошибка загрузки сводки:', error);
        tg.showAlert('Ошибка загрузки сводки');
    } finally {
        if (loadId === summaryMonthLoadId && !keepsButtonsDisabledForAnimation) {
            setSummaryMonthButtonsDisabled(false);
        }
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

function renderSummaryCalendar(data, options = {}) {
    const direction = Number(options.direction || 0);

    if (direction !== 0) {
        renderSummaryCalendarAnimated(data, direction);
        return;
    }

    renderSummaryCalendarContent(data);
}

function renderSummaryCalendarContent(data) {
    document.getElementById('summary-month-label').textContent = formatSummaryMonth(data.month);

    summaryDailyGoal = Number(data.daily_goal || 0);
    summaryDaysByDate = new Map((data.days || []).map(day => [day.date, day]));
    document.getElementById('summary-month-stats').innerHTML = renderSummaryMonthStats(data);
    const dates = buildMonthDates(data.month);

    document.getElementById('summary-calendar-grid').innerHTML = dates.map(dateInfo => {
        if (!dateInfo.date) {
            return '<div class="calendar-day calendar-day-empty-slot"></div>';
        }

        const day = getSummaryDay(dateInfo.date);

        return renderCalendarDay(dateInfo, day);
    }).join('');
    animateSummaryRings(document.getElementById('summary-calendar-grid'));
}

function renderSummaryCalendarAnimated(data, direction) {
    const animationId = ++summaryMonthAnimationId;
    const monthLabel = document.getElementById('summary-month-label');
    const monthStats = document.getElementById('summary-month-stats');
    const calendarGrid = document.getElementById('summary-calendar-grid');
    const exitingClass = direction > 0 ? 'summary-slide-out-left' : 'summary-slide-out-right';
    const enteringClass = direction > 0 ? 'summary-slide-in-right' : 'summary-slide-in-left';

    resetSummaryMonthAnimation(monthLabel);
    resetSummaryMonthAnimation(monthStats);
    resetSummaryMonthAnimation(calendarGrid);

    monthLabel.classList.add('summary-month-animating', exitingClass);
    monthStats.classList.add('summary-month-animating', exitingClass);
    calendarGrid.classList.add('summary-month-animating', exitingClass);

    window.setTimeout(() => {
        if (animationId !== summaryMonthAnimationId) {
            return;
        }

        renderSummaryCalendarContent(data);
        resetSummaryMonthAnimation(monthLabel);
        resetSummaryMonthAnimation(monthStats);
        resetSummaryMonthAnimation(calendarGrid);
        monthLabel.classList.add('summary-month-animating', enteringClass);
        monthStats.classList.add('summary-month-animating', enteringClass);
        calendarGrid.classList.add('summary-month-animating', enteringClass);

        requestAnimationFrame(() => {
            if (animationId !== summaryMonthAnimationId) {
                return;
            }

            monthLabel.classList.add('summary-slide-active');
            monthStats.classList.add('summary-slide-active');
            calendarGrid.classList.add('summary-slide-active');
        });

        window.setTimeout(() => {
            if (animationId !== summaryMonthAnimationId) {
                return;
            }

            resetSummaryMonthAnimation(monthLabel);
            resetSummaryMonthAnimation(monthStats);
            resetSummaryMonthAnimation(calendarGrid);
            setSummaryMonthButtonsDisabled(false);
        }, 320);
    }, 170);
}

function resetSummaryMonthAnimation(element) {
    element.classList.remove(
        'summary-month-animating',
        'summary-slide-out-left',
        'summary-slide-out-right',
        'summary-slide-in-left',
        'summary-slide-in-right',
        'summary-slide-active'
    );
}

function setSummaryMonthButtonsDisabled(disabled) {
    summaryMonthIsLoading = disabled;
    document.getElementById('btn-summary-prev-month').disabled = disabled;
    document.getElementById('btn-summary-next-month').disabled = disabled;
}

function renderSummaryMonthStats(data) {
    const days = Array.isArray(data.days) ? data.days : [];
    const recordedDays = days.filter(dayHasRecords);
    const recordedCount = recordedDays.length;
    const totalCalories = recordedDays.reduce((sum, day) => sum + Number(day.calories || 0), 0);
    const averageCalories = recordedCount > 0 ? Math.round(totalCalories / recordedCount) : 0;
    const normalDays = recordedDays.filter(day => Number(day.percentage || 0) >= 90 && Number(day.percentage || 0) <= 105).length;
    const overDays = recordedDays.filter(day => Number(day.percentage || 0) > 105).length;

    return `
        <div class="summary-month-stat-compact">
            <strong>${averageCalories}</strong>
            <span>ккал/день в среднем</span>
        </div>
        <p>${normalDays} в норме · ${overDays} переборов</p>
    `;
}

function dayHasRecords(day) {
    return Number(day.calories || 0) > 0
        || Number(day.proteins || 0) > 0
        || Number(day.fats || 0) > 0
        || Number(day.carbs || 0) > 0
        || (Array.isArray(day.meals) && day.meals.length > 0);
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
    const ringVisual = getRingVisual(rawPercentage);
    const calories = Number(day.calories || 0);
    const colorClass = `day-${day.color || 'empty'}`;
    const roundedPercentage = Math.round(rawPercentage);

    return `
        <button class="calendar-day ${colorClass}" type="button" data-date="${escapeHtml(day.date)}">
            <span
                class="calendar-ring summary-progress-ring ${ringVisual.className}"
                data-ring-progress-value="${ringVisual.progressValue}"
                data-ring-rotation-value="${ringVisual.rotationValue}"
            >
                ${renderProgressRingSvg()}
                <span class="summary-ring-dot" aria-hidden="true"></span>
                <span class="calendar-day-number">${dateInfo.day}</span>
            </span>
            <span class="calendar-day-calories">${calories > 0 ? calories : ''}</span>
            <span class="calendar-day-percent">${calories > 0 ? `${roundedPercentage}%` : ''}</span>
        </button>
    `;
}

function getRingVisual(rawPercentage) {
    const percentage = Math.max(Number(rawPercentage || 0), 0);
    const progressValue = Math.min(percentage, 100);

    return {
        className: [
            percentage > 0 ? 'has-progress' : '',
            progressValue >= 99.5 ? 'is-full' : '',
            percentage > 100 ? 'has-over' : ''
        ].filter(Boolean).join(' '),
        progressValue: Math.round(progressValue * 100) / 100,
        rotationValue: Math.round(percentage * 100) / 100
    };
}

function renderProgressRingSvg() {
    return `
        <svg class="summary-ring-svg" viewBox="0 0 44 44" aria-hidden="true" focusable="false">
            <circle class="summary-ring-track" cx="22" cy="22" r="18" pathLength="100"></circle>
            <circle class="summary-ring-progress" cx="22" cy="22" r="18" pathLength="100"></circle>
        </svg>
    `;
}

function applySummaryRingVisual(ring, rawPercentage) {
    const ringVisual = getRingVisual(rawPercentage);

    ring.classList.toggle('has-progress', ringVisual.className.includes('has-progress'));
    ring.classList.toggle('is-full', ringVisual.className.includes('is-full'));
    ring.classList.toggle('has-over', ringVisual.className.includes('has-over'));
    ring.dataset.ringProgressValue = String(ringVisual.progressValue);
    ring.dataset.ringRotationValue = String(ringVisual.rotationValue);
    setRingStroke(ring, 0, 0);
}

function animateSummaryRings(root) {
    const rings = root.matches?.('.summary-progress-ring')
        ? [root]
        : Array.from(root.querySelectorAll('.summary-progress-ring'));

    rings.forEach(ring => {
        ring.classList.remove('is-ring-animated');
        ring.style.removeProperty('transition-delay');
        setRingStroke(ring, 0, 0);
    });

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            rings.forEach((ring, index) => {
                ring.style.setProperty('transition-delay', `${Math.min(index * 16, 160)}ms`);
                setRingStroke(
                    ring,
                    Number(ring.dataset.ringProgressValue || 0),
                    Number(ring.dataset.ringRotationValue || 0)
                );
                ring.classList.add('is-ring-animated');
            });
        });
    });
}

function setRingStroke(ring, progressValue, rotationValue) {
    const progressCircle = ring.querySelector('.summary-ring-progress');
    const dot = ring.querySelector('.summary-ring-dot');
    const progress = Math.min(Math.max(Number(progressValue || 0), 0), 100);
    const rotation = Math.max(Number(rotationValue || 0), 0);

    if (progressCircle) {
        progressCircle.style.strokeDashoffset = String(100 - progress);
    }

    if (dot) {
        dot.style.transform = `rotate(${rotation * 3.6}deg)`;
    }
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
    if (summaryMonthIsLoading) return;
    loadSummaryCalendar(shiftMonth(summaryCurrentMonth, -1), { direction: -1 });
};

document.getElementById('btn-summary-next-month').onclick = () => {
    if (summaryMonthIsLoading) return;
    loadSummaryCalendar(shiftMonth(summaryCurrentMonth, 1), { direction: 1 });
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
    const calories = Number(day.calories || 0);
    const colorClass = `day-${day.color || 'empty'}`;
    const ring = document.getElementById('summary-day-ring');

    ring.className = `summary-day-ring summary-progress-ring ${colorClass}`;
    applySummaryRingVisual(ring, rawPercentage);
    animateSummaryRings(ring);

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
