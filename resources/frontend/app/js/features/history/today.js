// Today meal groups on the main screen

// Отрисовка истории приемов пищи
function renderMealHistory(meals) {
    const historyList = document.getElementById('history-list');
    disconnectProtectedImagesObserver();
    revokeProtectedImageUrls(historyList);
    protectedImagesRenderId++;
    historySwipeGesture = null;
    historyList.dataset.historyRendered = '1';

    const todayKey = formatDateKey(new Date());
    const todayMeals = (Array.isArray(meals) ? meals : [])
        .map(meal => ({ ...meal, parsedDate: parseMealDate(meal.created_at) }))
        .filter(meal => meal.parsedDate && formatDateKey(meal.parsedDate) === todayKey);
    const currentSlot = getCurrentHomeMealSlot();
    const slots = getHomeMealSlots().sort((left, right) => {
        if (left.key === currentSlot) return -1;
        if (right.key === currentSlot) return 1;
        return left.order - right.order;
    });

    historyList.innerHTML = slots.map(slot => {
        const slotMeals = todayMeals.filter(meal => getHomeMealSlotForMeal(meal) === slot.key);
        const totalCalories = slotMeals.reduce((sum, meal) => sum + Number(meal.calories || 0), 0);
        const isCurrent = slot.key === currentSlot;
        const isExpanded = isCurrent && slotMeals.length > 0;

        return `
            <article class="meal-item${isCurrent ? ' is-current' : ''}${slotMeals.length ? ' has-meals' : ''}${isExpanded ? ' is-expanded' : ''}"
                     data-meal-slot="${slot.key}">
                <button class="meal-header" type="button" aria-expanded="${String(isExpanded)}">
                    <span class="meal-icon" aria-hidden="true">${slot.icon}</span>
                    <span class="meal-title">
                        <strong>${slot.title}</strong>
                        ${slotMeals.length ? `<small>${formatHomeMealCount(slotMeals.length)}</small>` : ''}
                    </span>
                    <span class="meal-calories"><strong>${Math.round(totalCalories)}</strong> ккал</span>
                    <span class="meal-chevron" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M7 10l5 5 5-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"/>
                        </svg>
                    </span>
                </button>
                <div class="meal-panel${slotMeals.length ? '' : ' meal-panel-empty'}">
                    ${slotMeals.length
                        ? slotMeals.map(renderHomeMealCard).join('')
                        : '<p>Приемы пищи пока не добавлены. Нажмите плюс, чтобы добавить блюдо.</p>'}
                </div>
            </article>
        `;
    }).join('');

    requestAnimationFrame(() => {
        historyList.querySelectorAll('.meal-item.is-expanded').forEach(item => {
            setHomeMealItemExpanded(item, true);
        });
    });
    observeProtectedImages();
}

function getHomeMealSlots() {
    return [
        {
            key: 'breakfast',
            title: 'Завтрак',
            order: 0,
            icon: '<svg viewBox="0 0 24 24"><path d="M5 16.8c0-3.9 3.1-7 7-7s7 3.1 7 7H5Z" fill="currentColor"/><path d="M3.2 18.2h17.6c.4 0 .7.3.7.7s-.3.7-.7.7H3.2c-.4 0-.7-.3-.7-.7s.3-.7.7-.7Z" fill="currentColor"/><path d="M12 3c.4 0 .7.3.7.7v2a.7.7 0 0 1-1.4 0v-2c0-.4.3-.7.7-.7ZM19.2 6.1c.3.3.3.7 0 1l-1.4 1.4a.7.7 0 0 1-1-1l1.4-1.4c.3-.3.7-.3 1 0ZM4.8 6.1c.3-.3.7-.3 1 0l1.4 1.4a.7.7 0 0 1-1 1L4.8 7.1c-.3-.3-.3-.7 0-1ZM2.4 13.2c0-.4.3-.7.7-.7h2a.7.7 0 0 1 0 1.4h-2c-.4 0-.7-.3-.7-.7ZM18.9 12.5h2a.7.7 0 0 1 0 1.4h-2a.7.7 0 0 1 0-1.4Z" fill="currentColor"/></svg>'
        },
        {
            key: 'lunch',
            title: 'Обед',
            order: 1,
            icon: '<svg viewBox="0 0 24 24"><path d="M12 17.2a5.2 5.2 0 1 0 0-10.4 5.2 5.2 0 0 0 0 10.4Z" fill="currentColor"/><path d="M12 1.9c.4 0 .7.3.7.7v1.8a.7.7 0 0 1-1.4 0V2.6c0-.4.3-.7.7-.7ZM12 18.9c.4 0 .7.3.7.7v1.8a.7.7 0 0 1-1.4 0v-1.8c0-.4.3-.7.7-.7ZM22.1 12c0 .4-.3.7-.7.7h-1.8a.7.7 0 0 1 0-1.4h1.8c.4 0 .7.3.7.7ZM5.1 12c0 .4-.3.7-.7.7H2.6a.7.7 0 0 1 0-1.4h1.8c.4 0 .7.3.7.7ZM19.1 4.9c.3.3.3.7 0 1l-1.3 1.3a.7.7 0 0 1-1-1l1.3-1.3c.3-.3.7-.3 1 0ZM7.2 16.8c.3.3.3.7 0 1l-1.3 1.3a.7.7 0 0 1-1-1l1.3-1.3c.3-.3.7-.3 1 0ZM19.1 19.1c-.3.3-.7.3-1 0l-1.3-1.3a.7.7 0 0 1 1-1l1.3 1.3c.3.3.3.7 0 1ZM7.2 7.2c-.3.3-.7.3-1 0L4.9 5.9a.7.7 0 0 1 1-1l1.3 1.3c.3.3.3.7 0 1Z" fill="currentColor"/></svg>'
        },
        {
            key: 'dinner',
            title: 'Ужин',
            order: 2,
            icon: '<svg viewBox="0 0 24 24"><path d="M18.8 15.3c-5.3 0-9.6-4.3-9.6-9.6 0-.9.1-1.8.4-2.6.1-.5-.4-.9-.8-.6A9.9 9.9 0 1 0 21.5 15c.2-.5-.3-.9-.7-.8-.7.1-1.3.2-2 .2Z" fill="currentColor"/><path d="M17.6 4.4 18 6c.1.3.3.5.6.6l1.6.4c.4.1.4.7 0 .8l-1.6.4c-.3.1-.5.3-.6.6l-.4 1.6c-.1.4-.7.4-.8 0l-.4-1.6c-.1-.3-.3-.5-.6-.6l-1.6-.4c-.4-.1-.4-.7 0-.8l1.6-.4c.3-.1.5-.3.6-.6l.4-1.6c.1-.4.7-.4.8 0Z" fill="currentColor"/></svg>'
        },
        {
            key: 'snacks',
            title: 'Перекусы',
            order: 3,
            icon: '<svg viewBox="0 0 24 24"><path d="M16.2 7.5c1.8 0 3.1 1.6 3.1 4.3 0 4-2.6 8.2-5.2 8.2-.8 0-1.2-.3-2.1-.3-.9 0-1.3.3-2.1.3-2.6 0-5.2-4.2-5.2-8.2 0-2.7 1.3-4.3 3.1-4.3 1.2 0 2 .6 2.7.6.6 0 1.3-.6 2.5-.6.9 0 1.5.2 2 .5.3-.3.7-.5 1.2-.5Z" fill="currentColor"/><path d="M12.9 6.4c.2-2.1 1.3-3.5 3.3-4 .2 2.1-.9 3.6-3.3 4Z" fill="currentColor"/></svg>'
        }
    ];
}

function getCurrentHomeMealSlot() {
    return getHomeMealSlotForDate(new Date());
}

function getHomeMealSlotForDate(date) {
    const hour = date.getHours();

    if (hour >= 5 && hour < 12) return 'breakfast';
    if (hour >= 12 && hour < 16) return 'lunch';
    if (hour >= 16 && hour < 22) return 'dinner';
    return 'snacks';
}

function getHomeMealSlotForMeal(meal) {
    return getMealSlotFromDescription(meal?.description)
        || getHomeMealSlotForDate(meal?.parsedDate || new Date());
}

function formatHomeMealCount(count) {
    const lastDigit = count % 10;
    const lastTwoDigits = count % 100;
    const label = lastDigit === 1 && lastTwoDigits !== 11
        ? 'блюдо'
        : (lastDigit >= 2 && lastDigit <= 4 && (lastTwoDigits < 12 || lastTwoDigits > 14) ? 'блюда' : 'блюд');

    return `${count} ${label}`;
}

function renderHomeMealCard(meal) {
    const description = escapeHtml(meal.description || 'Приём пищи');
    const time = meal.parsedDate
        ? meal.parsedDate.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
        : '—';
    const imageUrl = escapeHtml(meal.thumbnail_url || meal.image_url || '');
    const thumbnail = imageUrl
        ? `<img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="" data-image-url="${imageUrl}" loading="lazy">`
        : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8.5h14v10H5zM8 8.5l1.4-2h5.2l1.4 2M9 13l2 2 4-4 3 3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>';

    return `
        <div class="swipe-row home-meal-swipe-row">
            <button class="delete-action history-delete-action" type="button" data-meal-id="${Number(meal.id || 0)}">Удалить</button>
            <div class="meal-card-wrapper home-meal-card-wrapper">
                <article class="meal-food-card home-meal-swipe-card">
                    <span class="meal-food-thumb">${thumbnail}</span>
                    <span class="meal-food-info">
                        <strong>${description}</strong>
                        <small>${escapeHtml(time)}</small>
                        <small>${formatMealMeta(meal)}</small>
                    </span>
                    <span class="meal-food-calories"><strong>${Number(meal.calories || 0)}</strong> ккал</span>
                </article>
            </div>
        </div>
    `;
}

function groupMealsByDate(meals) {
    const groups = new Map();

    meals.forEach(meal => {
        const date = parseMealDate(meal.created_at);
        const key = date ? formatDateKey(date) : 'no-date';
        const label = date ? formatHistoryDateLabel(date) : 'Без даты';

        if (!groups.has(key)) {
            groups.set(key, { key, label, meals: [] });
        }

        groups.get(key).meals.push({ ...meal, parsedDate: date });
    });

    return Array.from(groups.values());
}
