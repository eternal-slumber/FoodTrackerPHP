// History calendar state, loading and date calculations

let historyCalendarCurrentMonth = historyCalendarGetCurrentMonthKey();
let historyCalendarDailyGoal = 0;
let historyCalendarDaysByDate = new Map();
let historyCalendarSelectedDate = '';
let historyCalendarDayExpanded = false;
let historyCalendarLoadId = 0;
let historyCalendarInitialized = false;
let historyCalendarHasRendered = false;
let historyCalendarImageRenderId = 0;

async function loadHistoryCalendar(month = historyCalendarCurrentMonth) {
    const loadId = ++historyCalendarLoadId;
    const timezoneOffset = getTimezoneOffsetMinutes();

    historyCalendarSetLoading(true);

    try {
        const result = await apiRequestJson(`/api/summary?month=${month}&tz_offset=${timezoneOffset}`);

        if (loadId !== historyCalendarLoadId) {
            return;
        }

        const shouldAnimate = !historyCalendarHasRendered || month !== historyCalendarCurrentMonth;

        historyCalendarRender(result.data, shouldAnimate);
        historyCalendarHasRendered = true;
    } catch (error) {
        if (loadId !== historyCalendarLoadId) {
            return;
        }

        console.error('Ошибка загрузки истории:', error);
        historyCalendarRenderError(error?.message || 'Не удалось загрузить историю');
    } finally {
        if (loadId === historyCalendarLoadId) {
            historyCalendarSetLoading(false);
        }
    }
}

async function refreshHistoryCalendar() {
    if (!historyCalendarInitialized) {
        return;
    }

    await loadHistoryCalendar(historyCalendarCurrentMonth);
}

function historyCalendarGetCurrentMonthKey() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function historyCalendarGetTodayDateKey() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function historyCalendarShiftMonth(month, delta) {
    const [year, monthNumber] = month.split('-').map(Number);
    const date = new Date(year, monthNumber - 1 + delta, 1);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function historyCalendarFormatMonth(month) {
    const [year, monthNumber] = month.split('-').map(Number);
    return new Date(year, monthNumber - 1, 1).toLocaleDateString('ru-RU', {
        month: 'long',
        year: 'numeric'
    });
}

function historyCalendarFormatDate(dateKey) {
    const [year, month, day] = dateKey.split('-').map(Number);
    return new Date(year, month - 1, day).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
}

function historyCalendarBuildMonthDates(month) {
    const [year, monthNumber] = month.split('-').map(Number);
    const firstDay = new Date(year, monthNumber - 1, 1);
    const lastDay = new Date(year, monthNumber, 0);
    const firstWeekday = firstDay.getDay() || 7;
    const dates = [];

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

function historyCalendarBuildWeekDates(dateKey) {
    const [year, month, day] = dateKey.split('-').map(Number);
    const selectedDate = new Date(year, month - 1, day);
    const weekday = selectedDate.getDay() || 7;
    const monday = new Date(selectedDate);
    const dates = [];

    monday.setDate(selectedDate.getDate() - weekday + 1);

    for (let index = 0; index < 7; index += 1) {
        const date = new Date(monday);
        date.setDate(monday.getDate() + index);
        dates.push({
            date: `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`,
            day: date.getDate()
        });
    }

    return dates;
}

function historyCalendarGetDay(date) {
    return historyCalendarDaysByDate.get(date) || {
        date,
        calories: 0,
        percentage: 0,
        color: 'empty',
        proteins: 0,
        fats: 0,
        carbs: 0,
        meals: []
    };
}

function historyCalendarDayHasRecords(day) {
    return Number(day.calories || 0) > 0
        || Number(day.proteins || 0) > 0
        || Number(day.fats || 0) > 0
        || Number(day.carbs || 0) > 0
        || (Array.isArray(day.meals) && day.meals.length > 0);
}
