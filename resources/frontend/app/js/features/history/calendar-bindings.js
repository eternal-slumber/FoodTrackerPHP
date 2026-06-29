// History calendar interactions and initialization

function historyCalendarSelectDay(date) {
    historyCalendarSelectedDate = date;
    historyCalendarDayExpanded = false;
    historyCalendarRenderGrid();
    historyCalendarRenderDayDetail(historyCalendarGetDay(date));
}

function historyCalendarToggleDayExpanded() {
    historyCalendarDayExpanded = !historyCalendarDayExpanded;
    historyCalendarRenderGrid();
    historyCalendarRenderDayDetail(historyCalendarGetDay(historyCalendarSelectedDate));
}

function historyCalendarSetLoading(isLoading) {
    const previousButton = document.getElementById('btn-history-prev-month');
    const nextButton = document.getElementById('btn-history-next-month');

    if (previousButton) {
        previousButton.disabled = isLoading;
    }

    if (nextButton) {
        nextButton.disabled = isLoading;
    }
}

function historyCalendarRenderError(message) {
    const grid = document.getElementById('history-calendar-grid');

    if (!grid) {
        return;
    }

    grid.classList.remove('is-week-mode');
    grid.innerHTML = `
        <div class="history-calendar-error">
            ${escapeHtml(message)}
            <button type="button" data-history-retry>Повторить</button>
        </div>
    `;
}

function initializeHistoryCalendar() {
    if (historyCalendarInitialized) {
        return;
    }

    const grid = document.getElementById('history-calendar-grid');
    const previousButton = document.getElementById('btn-history-prev-month');
    const nextButton = document.getElementById('btn-history-next-month');
    const expandButton = document.getElementById('history-day-expand');

    if (!grid || !previousButton || !nextButton || !expandButton) {
        return;
    }

    historyCalendarInitialized = true;

    grid.addEventListener('click', event => {
        const retryButton = event.target.closest('[data-history-retry]');
        if (retryButton) {
            loadHistoryCalendar(historyCalendarCurrentMonth);
            return;
        }

        const dayButton = event.target.closest('.history-calendar-day[data-date]');
        if (dayButton) {
            historyCalendarSelectDay(dayButton.dataset.date);
        }
    });

    previousButton.addEventListener('click', () => {
        historyCalendarDayExpanded = false;
        loadHistoryCalendar(historyCalendarShiftMonth(historyCalendarCurrentMonth, -1));
    });

    nextButton.addEventListener('click', () => {
        historyCalendarDayExpanded = false;
        loadHistoryCalendar(historyCalendarShiftMonth(historyCalendarCurrentMonth, 1));
    });

    expandButton.addEventListener('click', historyCalendarToggleDayExpanded);
}

initializeHistoryCalendar();
