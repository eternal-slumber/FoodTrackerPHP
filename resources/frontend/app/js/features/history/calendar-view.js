// History calendar rendering

function historyCalendarGetRingVisual(rawPercentage) {
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

function historyCalendarRenderRingSvg() {
    return `
        <svg class="history-ring-svg" viewBox="0 0 44 44" aria-hidden="true" focusable="false">
            <circle class="history-ring-track" cx="22" cy="22" r="18" pathLength="100"></circle>
            <circle class="history-ring-progress" cx="22" cy="22" r="18" pathLength="100"></circle>
        </svg>
    `;
}

function historyCalendarRender(data, shouldAnimate = false) {
    const monthDates = historyCalendarBuildMonthDates(data.month);

    historyCalendarCurrentMonth = data.month;
    historyCalendarDailyGoal = Number(data.daily_goal || 0);
    historyCalendarDaysByDate = new Map((data.days || []).map(day => [day.date, day]));
    historyCalendarSelectedDate = historyCalendarResolveSelectedDate(data.month, monthDates);

    document.getElementById('history-month-label').textContent = historyCalendarFormatMonth(data.month);
    historyCalendarRenderGrid(shouldAnimate);
    historyCalendarRenderDayDetail(historyCalendarGetDay(historyCalendarSelectedDate), shouldAnimate);
}

function historyCalendarResolveSelectedDate(month, dates) {
    const availableDates = dates.filter(dateInfo => Boolean(dateInfo.date));
    const selectedStillVisible = availableDates.some(dateInfo => dateInfo.date === historyCalendarSelectedDate);
    const today = historyCalendarGetTodayDateKey();

    if (selectedStillVisible) {
        return historyCalendarSelectedDate;
    }

    if (today.startsWith(`${month}-`)) {
        return today;
    }

    return availableDates[0]?.date || '';
}

function historyCalendarRenderGrid(shouldAnimate = false, shouldAnimateLayout = false) {
    const grid = document.getElementById('history-calendar-grid');
    const dates = historyCalendarDayExpanded
        ? historyCalendarBuildWeekDates(historyCalendarSelectedDate)
        : historyCalendarBuildMonthDates(historyCalendarCurrentMonth);

    if (!grid) {
        return;
    }

    const previousHeight = shouldAnimateLayout ? grid.getBoundingClientRect().height : 0;

    grid.classList.remove('is-calendar-entering');
    if (shouldAnimate) {
        void grid.offsetWidth;
    }
    grid.classList.toggle('is-week-mode', historyCalendarDayExpanded);
    grid.innerHTML = dates.map(dateInfo => {
        if (!dateInfo.date) {
            return '<div class="history-calendar-day history-calendar-day-empty-slot"></div>';
        }

        return historyCalendarRenderDay(dateInfo, historyCalendarGetDay(dateInfo.date));
    }).join('');

    if (shouldAnimateLayout) {
        historyCalendarAnimateGridHeight(grid, previousHeight);
    }

    if (shouldAnimate) {
        grid.classList.add('is-calendar-entering');
        historyCalendarAnimateRings(grid);
        return;
    }

    historyCalendarShowRings(grid);
}

function historyCalendarAnimateGridHeight(grid, previousHeight) {
    const resizeId = Number(grid.dataset.resizeId || 0) + 1;
    const nextHeight = grid.scrollHeight;

    grid.dataset.resizeId = String(resizeId);
    grid.classList.add('is-layout-transitioning');
    grid.style.height = `${previousHeight}px`;
    void grid.offsetHeight;

    requestAnimationFrame(() => {
        if (grid.dataset.resizeId !== String(resizeId)) {
            return;
        }

        grid.style.height = `${nextHeight}px`;
    });

    grid.addEventListener('transitionend', event => {
        if (event.propertyName !== 'height' || grid.dataset.resizeId !== String(resizeId)) {
            return;
        }

        grid.classList.remove('is-layout-transitioning');
        grid.style.removeProperty('height');
    }, { once: true });
}

function historyCalendarRenderDay(dateInfo, day) {
    const ringVisual = historyCalendarGetRingVisual(day.percentage);
    const selectedClass = day.date === historyCalendarSelectedDate ? ' is-selected' : '';

    return `
        <button class="history-calendar-day day-${day.color || 'empty'}${selectedClass}" type="button" data-date="${escapeHtml(day.date)}">
            <span
                class="history-calendar-ring history-progress-ring ${ringVisual.className}"
                data-ring-progress-value="${ringVisual.progressValue}"
                data-ring-rotation-value="${ringVisual.rotationValue}"
            >
                ${historyCalendarRenderRingSvg()}
                <span class="history-ring-dot" aria-hidden="true"></span>
                <span class="history-calendar-day-number">${dateInfo.day}</span>
            </span>
        </button>
    `;
}

function historyCalendarSetRingStroke(ring, progressValue, rotationValue) {
    const progressCircle = ring.querySelector('.history-ring-progress');
    const dot = ring.querySelector('.history-ring-dot');
    const progress = Math.min(Math.max(Number(progressValue || 0), 0), 100);
    const rotation = Math.max(Number(rotationValue || 0), 0);

    if (progressCircle) {
        progressCircle.style.strokeDashoffset = String(100 - progress);
    }

    if (dot) {
        dot.style.transform = `rotate(${rotation * 3.6}deg)`;
    }
}

function historyCalendarAnimateRings(container) {
    const rings = container.matches?.('.history-progress-ring')
        ? [container]
        : Array.from(container.querySelectorAll('.history-progress-ring'));

    rings.forEach(ring => {
        ring.classList.remove('is-ring-animated');
        ring.style.removeProperty('transition-delay');
        historyCalendarSetRingStroke(ring, 0, 0);
    });

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            rings.forEach((ring, index) => {
                ring.style.setProperty('transition-delay', `${Math.min(index * 16, 160)}ms`);
                historyCalendarSetRingStroke(
                    ring,
                    ring.dataset.ringProgressValue,
                    ring.dataset.ringRotationValue
                );
                ring.classList.add('is-ring-animated');
            });
        });
    });
}

function historyCalendarShowRings(container) {
    const rings = container.matches?.('.history-progress-ring')
        ? [container]
        : Array.from(container.querySelectorAll('.history-progress-ring'));

    rings.forEach(ring => {
        ring.style.removeProperty('transition-delay');
        historyCalendarSetRingStroke(
            ring,
            ring.dataset.ringProgressValue,
            ring.dataset.ringRotationValue
        );
        ring.classList.add('is-ring-animated');
    });
}

function historyCalendarApplyRingVisual(ring, rawPercentage) {
    const ringVisual = historyCalendarGetRingVisual(rawPercentage);

    ring.classList.toggle('has-progress', ringVisual.className.includes('has-progress'));
    ring.classList.toggle('is-full', ringVisual.className.includes('is-full'));
    ring.classList.toggle('has-over', ringVisual.className.includes('has-over'));
    ring.dataset.ringProgressValue = String(ringVisual.progressValue);
    ring.dataset.ringRotationValue = String(ringVisual.rotationValue);
}

function historyCalendarRenderDayDetail(day, shouldAnimate = false) {
    const detail = document.getElementById('history-day-detail');
    const ring = document.getElementById('history-day-ring');
    const expandButton = document.getElementById('history-day-expand');
    const expandedContent = detail?.querySelector('.history-day-expanded');
    const hasRecords = historyCalendarDayHasRecords(day);
    const percentage = Number(day.percentage || 0);

    if (!detail || !ring || !expandButton || !expandedContent) {
        return;
    }

    ring.className = `history-day-ring history-progress-ring day-${day.color || 'empty'}`;
    detail.classList.toggle('is-empty', !hasRecords);
    detail.classList.toggle('is-expanded', hasRecords && historyCalendarDayExpanded);
    historyCalendarApplyRingVisual(ring, percentage);
    if (shouldAnimate) {
        historyCalendarAnimateRings(ring);
    } else {
        historyCalendarShowRings(ring);
    }

    document.getElementById('history-day-date').textContent = historyCalendarFormatDate(day.date);
    document.getElementById('history-day-percent').textContent = `${Math.round(percentage)}%`;
    document.getElementById('history-day-calories').textContent = Math.round(Number(day.calories || 0));
    document.getElementById('history-day-goal-calories').textContent = Math.round(historyCalendarDailyGoal);
    document.getElementById('history-day-proteins').textContent = formatMacro(day.proteins);
    document.getElementById('history-day-fats').textContent = formatMacro(day.fats);
    document.getElementById('history-day-carbs').textContent = formatMacro(day.carbs);
    const mealsList = document.getElementById('history-day-meals-list');
    const keepExistingEmptyState = !shouldAnimate
        && !hasRecords
        && mealsList.querySelector('.history-empty-day');

    if (!keepExistingEmptyState) {
        historyCalendarRevokeImageUrls(mealsList);
        historyCalendarImageRenderId++;
        mealsList.innerHTML = historyCalendarRenderMeals(day.meals || [], shouldAnimate);
        historyCalendarLoadImages(mealsList, String(historyCalendarImageRenderId));
    }

    expandButton.hidden = !hasRecords;
    expandButton.setAttribute('aria-expanded', String(hasRecords && historyCalendarDayExpanded));
    document.getElementById('history-day-expand-label').textContent = historyCalendarDayExpanded
        ? 'Свернуть'
        : 'Развернуть';

    if (!hasRecords) {
        expandedContent.style.removeProperty('max-height');
        return;
    }

    if (!historyCalendarDayExpanded) {
        expandedContent.style.maxHeight = '0px';
        return;
    }

    requestAnimationFrame(() => {
        expandedContent.style.maxHeight = `${expandedContent.scrollHeight}px`;
    });
}

function historyCalendarGetMealSlot(meal) {
    const explicitSlot = getMealSlotFromDescription(meal?.description);
    if (explicitSlot) {
        return explicitSlot;
    }

    const hour = Number(String(meal?.time || '').split(':')[0]);

    if (hour >= 5 && hour < 12) {
        return 'breakfast';
    }

    if (hour >= 12 && hour < 16) {
        return 'lunch';
    }

    if (hour >= 16 && hour < 22) {
        return 'dinner';
    }

    return 'snacks';
}

function historyCalendarGetMealSlots() {
    return [
        { key: 'breakfast', title: 'Завтрак' },
        { key: 'lunch', title: 'Обед' },
        { key: 'dinner', title: 'Ужин' },
        { key: 'snacks', title: 'Перекусы' }
    ];
}

function historyCalendarRenderMeals(meals, shouldAnimateEmptyState = false) {
    if (meals.length === 0) {
        return historyCalendarRenderEmptyDay(shouldAnimateEmptyState);
    }

    return historyCalendarGetMealSlots().map(slot => {
        const slotMeals = meals.filter(meal => historyCalendarGetMealSlot(meal) === slot.key);

        if (slotMeals.length === 0) {
            return `
                <section class="history-meal-slot is-empty">
                    <h3>${slot.title}</h3>
                    <p>Не добавлено</p>
                </section>
            `;
        }

        return `
            <section class="history-meal-slot">
                <h3>${slot.title}</h3>
                ${slotMeals.map(historyCalendarRenderMeal).join('')}
            </section>
        `;
    }).join('');
}

function historyCalendarRenderMeal(meal) {
    const meta = [];
    const thumbnailUrl = String(meal.thumbnail_url || '');
    const thumbnail = thumbnailUrl
        ? `<img
            src="data:image/gif;base64,R0lGODlhAQABAAAAACw="
            alt=""
            data-history-image-url="${escapeHtml(thumbnailUrl)}"
            data-history-image-render-id="${historyCalendarImageRenderId}"
            loading="lazy"
        >`
        : '';

    if (Number(meal.weight || 0) > 0) {
        meta.push(`${formatMacro(meal.weight)} г`);
    }

    meta.push(`Б ${formatMacro(meal.proteins)}`);
    meta.push(`Ж ${formatMacro(meal.fats)}`);
    meta.push(`У ${formatMacro(meal.carbs)}`);

    return `
        <article class="history-day-meal">
            <span class="history-day-meal-thumb history-food-generic" aria-hidden="true">${thumbnail}</span>
            <div class="history-day-meal-info">
                <strong>${escapeHtml(meal.description || 'Приём пищи')}</strong>
                <small class="history-day-meal-time">${escapeHtml(meal.time || '')}</small>
                <small>${escapeHtml(meta.join(' · '))}</small>
            </div>
            <span class="history-day-meal-calories">
                <strong>${Math.round(Number(meal.calories || 0))}</strong> ккал
            </span>
        </article>
    `;
}

function historyCalendarRevokeImageUrls(root) {
    root?.querySelectorAll('img[data-history-object-url]').forEach(image => {
        URL.revokeObjectURL(image.dataset.historyObjectUrl);
        delete image.dataset.historyObjectUrl;
    });
}

function historyCalendarLoadImages(root, renderId) {
    root.querySelectorAll('img[data-history-image-url]').forEach(image => {
        historyCalendarLoadImage(image, renderId);
    });
}

async function historyCalendarLoadImage(image, renderId) {
    if (!image.isConnected || image.dataset.historyImageRenderId !== renderId) {
        return;
    }

    try {
        const blob = await apiRequestBlob(image.dataset.historyImageUrl);
        if (!image.isConnected || image.dataset.historyImageRenderId !== renderId) {
            return;
        }

        const objectUrl = URL.createObjectURL(blob);
        image.src = objectUrl;
        image.dataset.historyObjectUrl = objectUrl;
    } catch (error) {
        console.error('Ошибка загрузки миниатюры истории:', error);
    }
}

function historyCalendarRenderEmptyDay(shouldAnimate = false) {
    const animationClass = shouldAnimate ? '' : ' is-pointer-drawn';

    return `
        <div class="history-empty-day${animationClass}">
            <strong>В этот день ещё ничего не добавлено</strong>
            <p>Нажми на плюс, чтобы добавить первый приём.</p>
            <svg class="history-add-pointer" viewBox="0 0 64 74" aria-hidden="true" focusable="false">
                <path class="history-add-pointer-line" d="M18 12c13-10 33 1 23 16-6 9-22 3-15-6 5-6 18-2 19 11 .8 10-5 19-13 26" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="3"/>
                <path class="history-add-pointer-head" d="M32 59l9-2M32 59l2-9" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="3"/>
            </svg>
        </div>
    `;
}
