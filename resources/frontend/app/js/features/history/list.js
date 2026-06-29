// History list and accordion

function renderHistoryItem(meal) {
    const description = escapeHtml(meal.description);
    const meta = formatMealMeta(meal);
    const formattedTime = meal.parsedDate
        ? meal.parsedDate.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
        : '—';

    return `
        <div class="swipe-row history-swipe-row">
            <button class="delete-action history-delete-action" type="button" data-meal-id="${meal.id}">Удалить</button>
            <div class="meal-card-wrapper history-card-wrapper">
                <article class="meal-card history-item" data-meal-id="${meal.id}" aria-expanded="false">
                    <div class="history-card-main">
                        <div class="history-thumbnail">
                            ${renderHistoryThumbnail(meal, description)}
                        </div>
                        <div class="history-info">
                            <div class="history-date">${formattedTime}</div>
                            <div class="history-description">${description}</div>
                            <div class="history-macros">${meta}</div>
                        </div>
                        <div class="history-calories">
                            <span class="calories-value">${meal.calories}</span>
                            <span class="calories-label">ккал</span>
                            <span class="history-expand-indicator" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path opacity="0.18" d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" fill="currentColor"/>
                                    <path d="M10.74 16.2802C10.55 16.2802 10.36 16.2102 10.21 16.0602C9.91999 15.7702 9.91999 15.2902 10.21 15.0002L13.21 12.0002L10.21 9.00016C9.91999 8.71016 9.91999 8.23016 10.21 7.94016C10.5 7.65016 10.98 7.65016 11.27 7.94016L14.8 11.4702C15.09 11.7602 15.09 12.2402 14.8 12.5302L11.27 16.0602C11.12 16.2102 10.93 16.2802 10.74 16.2802Z" fill="currentColor"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div class="history-accordion" aria-hidden="true">
                        <div class="history-accordion-inner"></div>
                    </div>
                </article>
            </div>
        </div>
    `;
}

function renderHistoryThumbnail(meal, description) {
    const imageUrl = escapeHtml(meal.thumbnail_url || meal.image_url || '');

    if (imageUrl) {
        return `<img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="${description}" data-image-url="${imageUrl}" loading="lazy" decoding="async">`;
    }

    return `
        <span class="history-thumbnail-placeholder" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" focusable="false">
                <path d="M18.2295 10.2H5.76953L5.88953 9.36999C6.00953 8.49999 6.74953 7.85999 7.62953 7.85999H16.3695C17.2495 7.85999 17.9895 8.49999 18.1095 9.36999L18.2295 10.2Z"/>
                <g opacity="0.4">
                    <path d="M19.0697 16.14H4.92969L5.76969 10.2H11.9997C12.6497 10.2 13.1697 10.73 13.1697 11.37C13.1697 10.73 13.6997 10.2 14.3397 10.2C14.9797 10.2 15.5197 10.73 15.5197 11.37C15.5197 10.73 16.0397 10.2 16.6897 10.2H18.2297L19.0697 16.14Z"/>
                </g>
                <path d="M13.1701 6.68999C13.1701 7.32999 12.6501 7.85999 12.0001 7.85999C11.3501 7.85999 10.8301 7.32999 10.8301 6.68999C10.8301 6.04999 11.3501 5.51999 12.0001 5.51999C12.6501 5.51999 13.1701 6.03999 13.1701 6.68999Z"/>
                <path d="M20.2398 16.92V17.31C20.2398 17.96 19.7198 18.48 19.0698 18.48H4.92977C4.27977 18.48 3.75977 17.96 3.75977 17.31V16.92C3.75977 16.49 4.10977 16.14 4.53977 16.14H19.4598C19.8898 16.14 20.2398 16.49 20.2398 16.92Z"/>
            </svg>
        </span>
    `;
}

function bindHistoryInteractions() {
    const historyList = document.getElementById('history-list');

    if (!historyList || historyList.dataset.historyInteractionsBound === '1') {
        return;
    }

    historyList.dataset.historyInteractionsBound = '1';
    historyList.addEventListener('click', handleHistoryClick);
    historyList.addEventListener('pointerdown', handleHistoryPointerDown);
    document.addEventListener('pointermove', handleHistoryPointerMove);
    document.addEventListener('pointerup', handleHistoryPointerUp);
    document.addEventListener('pointercancel', handleHistoryPointerCancel);
}

function handleHistoryClick(event) {
    const homeMealHeader = event.target.closest('#screen-main .meal-header');
    if (homeMealHeader) {
        const selectedItem = homeMealHeader.closest('.meal-item');
        const shouldExpand = !selectedItem.classList.contains('is-expanded');

        document.querySelectorAll('#screen-main .meal-item').forEach(item => {
            setHomeMealItemExpanded(item, false);
        });

        if (shouldExpand) {
            setHomeMealItemExpanded(selectedItem, true);
        }

        return;
    }

    const deleteButton = event.target.closest('.history-delete-action');
    if (deleteButton) {
        const row = deleteButton.closest('.swipe-row');
        if (!row?.classList.contains('is-open')) {
            return;
        }

        deleteMeal(Number(deleteButton.dataset.mealId));
        return;
    }

    if (event.target.closest('.home-meal-swipe-card')) {
        return;
    }

    const item = event.target.closest('.meal-card');
    if (!item || event.target.closest('.history-accordion')) {
        return;
    }

    const row = item.closest('.swipe-row');
    if (!row) {
        return;
    }

    if (row.dataset.swipeHandled === '1' || row.classList.contains('is-open')) {
        row.dataset.swipeHandled = '';
        return;
    }

    toggleHistoryAccordion(row, Number(item.dataset.mealId));
}

function setHomeMealItemExpanded(item, expanded) {
    const panel = item?.querySelector('.meal-panel');
    const header = item?.querySelector('.meal-header');

    if (!item || !panel || !header) {
        return;
    }

    item.classList.toggle('is-expanded', expanded);
    header.setAttribute('aria-expanded', String(expanded));
    panel.style.maxHeight = expanded ? `${panel.scrollHeight}px` : '0px';
}

function handleHistoryPointerDown(event) {
    const item = event.target.closest('.meal-card, .home-meal-swipe-card');
    if (!item || event.target.closest('.history-accordion')) {
        return;
    }

    const row = item.closest('.swipe-row');
    const wrapper = item.closest('.meal-card-wrapper');
    if (!row || !wrapper || row.classList.contains('is-deleting')) {
        return;
    }

    closeHistorySwipeRows(row);
    historySwipeGesture = {
        row,
        item,
        wrapper,
        pointerId: event.pointerId,
        startX: event.clientX,
        startY: event.clientY,
        currentX: row.classList.contains('is-open') ? HISTORY_MAX_SWIPE_X : 0,
        wasOpen: row.classList.contains('is-open'),
        isHorizontalSwipe: false,
        isVerticalScroll: false,
        lastTranslateX: row.classList.contains('is-open') ? HISTORY_MAX_SWIPE_X : 0,
    };
    wrapper.style.transition = 'none';
}

function handleHistoryPointerMove(event) {
    const gesture = historySwipeGesture;
    if (!gesture || gesture.pointerId !== event.pointerId || gesture.isVerticalScroll) {
        return;
    }

    const deltaX = event.clientX - gesture.startX;
    const deltaY = event.clientY - gesture.startY;
    const absX = Math.abs(deltaX);
    const absY = Math.abs(deltaY);

    if (!gesture.isHorizontalSwipe) {
        if (absX < HISTORY_INTENT_THRESHOLD && absY < HISTORY_INTENT_THRESHOLD) {
            return;
        }

        if (absY > absX * 1.4) {
            gesture.isVerticalScroll = true;
            gesture.wrapper.style.transition = '';
            return;
        }

        if (deltaX > 0 && !gesture.row.classList.contains('is-open')) {
            historySwipeGesture = null;
            gesture.wrapper.style.transition = '';
            return;
        }

        gesture.isHorizontalSwipe = true;
        gesture.row.classList.add('is-horizontal-dragging', 'is-swiping', 'swiping');
        gesture.item.setPointerCapture?.(event.pointerId);
    }

    event.preventDefault();

    const nextX = Math.max(HISTORY_MAX_SWIPE_X, Math.min(0, gesture.currentX + deltaX));
    gesture.lastTranslateX = nextX;
    gesture.row.classList.toggle('is-swiping', nextX < -4);
    gesture.row.classList.toggle('swiping', nextX < -4);
    gesture.wrapper.style.transform = `translate3d(${nextX}px, 0, 0)`;
}

function handleHistoryPointerUp(event) {
    finishHistoryPointerGesture(event, false);
}

function handleHistoryPointerCancel(event) {
    finishHistoryPointerGesture(event, true);
}

function finishHistoryPointerGesture(event, isCanceled) {
    const gesture = historySwipeGesture;
    if (!gesture || gesture.pointerId !== event.pointerId) {
        return;
    }

    historySwipeGesture = null;

    if (gesture.isHorizontalSwipe) {
        gesture.item.releasePointerCapture?.(event.pointerId);
    }

    gesture.row.classList.remove('swiping', 'is-swiping');
    gesture.row.classList.remove('is-horizontal-dragging');
    gesture.wrapper.style.transition = '';

    if (isCanceled) {
        const shouldRemainOpen = gesture.isHorizontalSwipe && gesture.lastTranslateX < HISTORY_OPEN_THRESHOLD;
        setHistorySwipeRowOpen(gesture.row, shouldRemainOpen || gesture.row.classList.contains('is-open'));
        return;
    }

    const totalDeltaX = event.clientX - gesture.startX;
    const shouldOpen = gesture.isHorizontalSwipe
        && (gesture.lastTranslateX < HISTORY_OPEN_THRESHOLD || totalDeltaX < HISTORY_OPEN_THRESHOLD);
    closeHistorySwipeRows(gesture.row);
    setHistorySwipeRowOpen(gesture.row, shouldOpen);
    gesture.row.dataset.swipeHandled = shouldOpen || gesture.isHorizontalSwipe || gesture.wasOpen ? '1' : '';
}

async function toggleHistoryAccordion(row, mealId) {
    if (!mealId) return;

    if (row.classList.contains('detail-open')) {
        closeHistoryAccordions();
        return;
    }

    closeHistoryAccordions(row);
    closeHistorySwipeRows(row);

    setHistoryAccordionContent(row, '<div class="history-detail-content"><p class="history-detail-loading">Загрузка деталей...</p></div>');
    openHistoryAccordion(row);

    try {
        const meal = await fetchMealDetail(mealId);
        if (!row.isConnected) {
            return;
        }

        setHistoryAccordionContent(row, renderHistoryAccordionDetail(meal));
    } catch (error) {
        if (!row.isConnected) {
            return;
        }

        console.error('Ошибка загрузки детализации приема:', error);
        setHistoryAccordionContent(row, '<div class="history-detail-content"><p class="history-detail-loading">Не удалось загрузить детализацию</p></div>');
    }
}

function closeHistoryAccordions(exceptRow = null) {
    document.querySelectorAll('.history-swipe-row.detail-open').forEach(row => {
        if (row === exceptRow) return;

        closeHistoryAccordion(row);
    });
}

function setHistoryAccordionContent(row, html) {
    const accordion = row.querySelector('.history-accordion');
    const accordionInner = row.querySelector('.history-accordion-inner');

    if (!accordion || !accordionInner) {
        return;
    }

    const isOpen = row.classList.contains('detail-open');
    if (isOpen) {
        accordion.style.height = `${accordion.offsetHeight}px`;
    }

    accordionInner.innerHTML = html;

    if (isOpen) {
        requestAnimationFrame(() => animateHistoryAccordionHeight(row));
    }
}

function openHistoryAccordion(row) {
    const accordion = row.querySelector('.history-accordion');
    if (!accordion) {
        return;
    }

    accordion.setAttribute('aria-hidden', 'false');
    accordion.style.height = '0px';
    row.classList.add('detail-open');
    row.querySelector('.history-item')?.setAttribute('aria-expanded', 'true');

    requestAnimationFrame(() => animateHistoryAccordionHeight(row));
}

function closeHistoryAccordion(row) {
    const accordion = row.querySelector('.history-accordion');
    if (!accordion) {
        return;
    }

    accordion.style.height = `${accordion.offsetHeight}px`;
    row.classList.remove('detail-open');
    accordion.setAttribute('aria-hidden', 'true');
    row.querySelector('.history-item')?.setAttribute('aria-expanded', 'false');

    requestAnimationFrame(() => {
        accordion.style.height = '0px';
    });
}

function animateHistoryAccordionHeight(row) {
    const accordion = row.querySelector('.history-accordion');
    const accordionInner = row.querySelector('.history-accordion-inner');

    if (!accordion || !accordionInner || !row.classList.contains('detail-open')) {
        return;
    }

    accordion.style.height = `${accordionInner.scrollHeight}px`;
}

async function fetchMealDetail(mealId) {
    if (historyMealDetailsCache.has(mealId)) {
        return historyMealDetailsCache.get(mealId);
    }

    const result = await apiRequestJson(`/api/meals/${mealId}`);

    historyMealDetailsCache.set(mealId, result.data);
    return result.data;
}

function renderHistoryAccordionDetail(meal) {
    return `
        <div class="history-detail-content">
            <div class="history-detail-totals">
                <div><strong>${meal.weight ? Number(meal.weight) : '—'}</strong><span>граммы</span></div>
                <div><strong>${formatMacro(meal.proteins)}</strong><span>белки</span></div>
                <div><strong>${formatMacro(meal.fats)}</strong><span>жиры</span></div>
                <div><strong>${formatMacro(meal.carbs)}</strong><span>углеводы</span></div>
            </div>
            <div class="history-detail-products">
                ${renderHistoryAccordionProducts(meal.products || [])}
            </div>
        </div>
    `;
}

function renderHistoryAccordionProducts(products) {
    if (products.length === 0) {
        return '<p class="history-detail-empty">Детализация недоступна для старых записей</p>';
    }

    return products.map(product => `
        <article class="history-detail-product">
            <div>
                <strong>${escapeHtml(product.name || 'Продукт')}</strong>
                <small>${formatMealProductMeta(product)}</small>
            </div>
            <span>${Number(product.calories || 0)} ккал</span>
        </article>
    `).join('');
}
