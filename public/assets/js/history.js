// Загрузка истории приемов пищи
let mealDetailBackHandler = null;
let mealDetailParentBackHandler = null;
let mealDetailKeepBodyLockedOnClose = false;
let protectedImagesObserver = null;
let protectedImagesRenderId = 0;
const historyMealDetailsCache = new Map();

async function loadMealHistory() {
    try {
        const response = await apiFetch('/api/history');
        const result = await response.json();

        if (result.status === 'success') {
            renderMealHistory(result.data);
        }
    } catch (error) {
        console.error('Ошибка загрузки истории:', error);
    }
}

// Отрисовка истории приемов пищи
function renderMealHistory(meals) {
    const historyList = document.getElementById('history-list');
    disconnectProtectedImagesObserver();
    revokeProtectedImageUrls(historyList);
    protectedImagesRenderId++;

    if (!meals || meals.length === 0) {
        historyList.innerHTML = '<p class="history-empty">История пуста</p>';
        return;
    }

    const groupedMeals = groupMealsByDate(meals);

    historyList.innerHTML = groupedMeals.map(group => `
        <section class="history-day-group">
            <div class="history-day-header">${escapeHtml(group.label)}</div>
            <div class="history-day-list">
                ${group.meals.map(renderHistoryItem).join('')}
            </div>
        </section>
    `).join('');

    observeProtectedImages();
    bindHistorySwipeActions();
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

function renderHistoryItem(meal) {
    const description = escapeHtml(meal.description);
    const meta = formatMealMeta(meal);
    const formattedTime = meal.parsedDate
        ? meal.parsedDate.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
        : '—';

    return `
        <div class="history-swipe-row">
            <div class="history-swipe-layer">
                <button class="history-delete-action" type="button" data-meal-id="${meal.id}">Удалить</button>
                <div class="history-item" data-meal-id="${meal.id}" aria-expanded="false">
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
                </div>
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

function bindHistorySwipeActions() {
    const maxSwipeX = -120;
    const openThreshold = -18;
    const intentThreshold = 6;

    document.querySelectorAll('.history-swipe-row').forEach(row => {
        const item = row.querySelector('.history-item');
        const deleteButton = row.querySelector('.history-delete-action');
        let startX = 0;
        let startY = 0;
        let currentX = 0;
        let isPointerDown = false;
        let isHorizontalSwipe = false;
        let isVerticalScroll = false;
        let lastTranslateX = 0;

        deleteButton.onclick = () => deleteMeal(Number(deleteButton.dataset.mealId));

        item.addEventListener('click', event => {
            if (event.target.closest('.history-accordion')) {
                return;
            }

            if (row.dataset.swipeHandled === '1' || row.classList.contains('open')) {
                row.dataset.swipeHandled = '';
                return;
            }

            toggleHistoryAccordion(row, Number(item.dataset.mealId));
        });

        item.addEventListener('pointerdown', event => {
            if (event.target.closest('.history-accordion')) {
                return;
            }

            closeHistorySwipeRows(row);
            startX = event.clientX;
            startY = event.clientY;
            currentX = row.classList.contains('open') ? maxSwipeX : 0;
            isPointerDown = true;
            isHorizontalSwipe = false;
            isVerticalScroll = false;
            lastTranslateX = currentX;
            item.style.transition = 'none';
        });

        item.addEventListener('pointermove', event => {
            if (!isPointerDown || isVerticalScroll) return;

            const deltaX = event.clientX - startX;
            const deltaY = event.clientY - startY;
            const absX = Math.abs(deltaX);
            const absY = Math.abs(deltaY);

            if (!isHorizontalSwipe) {
                if (absX < intentThreshold && absY < intentThreshold) return;

                if (absY > absX * 1.4) {
                    isVerticalScroll = true;
                    item.style.transition = '';
                    return;
                }

                if (deltaX > 0 && !row.classList.contains('open')) {
                    isPointerDown = false;
                    item.style.transition = '';
                    return;
                }

                isHorizontalSwipe = true;
                row.classList.add('is-horizontal-dragging');
                item.setPointerCapture?.(event.pointerId);
            }

            event.preventDefault();

            const nextX = Math.max(maxSwipeX, Math.min(0, currentX + deltaX));
            lastTranslateX = nextX;
            row.classList.toggle('swiping', nextX < -4);
            item.style.transform = `translateX(${nextX}px)`;
        });

        item.addEventListener('pointerup', event => {
            if (!isPointerDown) return;

            isPointerDown = false;
            if (isHorizontalSwipe) {
                item.releasePointerCapture?.(event.pointerId);
            }

            const totalDeltaX = event.clientX - startX;
            const shouldOpen = isHorizontalSwipe && (lastTranslateX < openThreshold || totalDeltaX < openThreshold);
            closeHistorySwipeRows(row);
            row.classList.remove('swiping');
            row.classList.remove('is-horizontal-dragging');
            row.classList.toggle('open', shouldOpen);
            row.dataset.swipeHandled = shouldOpen || isHorizontalSwipe ? '1' : '';
            item.style.transition = '';
            item.style.transform = shouldOpen ? `translateX(${maxSwipeX}px)` : '';
        });

        item.addEventListener('pointercancel', event => {
            isPointerDown = false;
            if (isHorizontalSwipe) {
                item.releasePointerCapture?.(event.pointerId);
            }
            row.classList.remove('swiping');
            row.classList.remove('is-horizontal-dragging');
            item.style.transition = '';
            const shouldOpen = isHorizontalSwipe && lastTranslateX < openThreshold;
            row.classList.toggle('open', shouldOpen || row.classList.contains('open'));
            item.style.transform = row.classList.contains('open') ? `translateX(${maxSwipeX}px)` : '';
        });
    });
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

    const response = await apiFetch(`/api/meals/${mealId}`);
    const result = await response.json();

    if (!response.ok || result.status !== 'success') {
        throw new Error(result.message || result.error || 'Не удалось загрузить детализацию');
    }

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

async function openMealDetail(mealId, options = {}) {
    if (!mealId) return;

    try {
        const response = await apiFetch(`/api/meals/${mealId}`);
        const result = await response.json();

        if (!response.ok || result.status !== 'success') {
            tg.showAlert(result.message || result.error || 'Не удалось загрузить детализацию');
            return;
        }

        renderMealDetail(result.data);
        document.getElementById('meal-detail-sheet').classList.remove('hidden');
        document.body.classList.add('sheet-open');

        mealDetailParentBackHandler = options.parentBackHandler || null;
        mealDetailKeepBodyLockedOnClose = Boolean(options.keepBodyLockedOnClose);

        if (mealDetailParentBackHandler) {
            tg.BackButton.offClick(mealDetailParentBackHandler);
        }

        mealDetailBackHandler = closeMealDetail;
        tg.BackButton.show();
        tg.BackButton.onClick(mealDetailBackHandler);
    } catch (error) {
        console.error('Ошибка загрузки детализации приема:', error);
        tg.showAlert('Ошибка загрузки детализации');
    }
}

function closeMealDetail() {
    document.getElementById('meal-detail-sheet').classList.add('hidden');

    if (mealDetailBackHandler) {
        tg.BackButton.offClick(mealDetailBackHandler);
        mealDetailBackHandler = null;
    }

    if (mealDetailParentBackHandler) {
        tg.BackButton.show();
        tg.BackButton.onClick(mealDetailParentBackHandler);
        mealDetailParentBackHandler = null;
    } else {
        tg.BackButton.hide();
    }

    if (!mealDetailKeepBodyLockedOnClose) {
        document.body.classList.remove('sheet-open');
    }

    mealDetailKeepBodyLockedOnClose = false;
}

function renderMealDetail(meal) {
    const date = parseMealDate(meal.created_at);
    const formattedTime = date
        ? date.toLocaleString('ru-RU', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })
        : 'Прием пищи';

    document.getElementById('meal-detail-time').textContent = formattedTime;
    document.getElementById('meal-detail-title').textContent = meal.description || 'Прием пищи';
    document.getElementById('meal-detail-calories').textContent = Number(meal.calories || 0);
    document.getElementById('meal-detail-weight').textContent = meal.weight ? Number(meal.weight) : '—';
    document.getElementById('meal-detail-proteins').textContent = formatMacro(meal.proteins);
    document.getElementById('meal-detail-fats').textContent = formatMacro(meal.fats);
    document.getElementById('meal-detail-carbs').textContent = formatMacro(meal.carbs);
    document.getElementById('meal-detail-products-list').innerHTML = renderMealDetailProducts(meal.products || []);
}

function renderMealDetailProducts(products) {
    if (products.length === 0) {
        return '<p class="meal-detail-empty">Детализация недоступна для старых записей</p>';
    }

    return products.map(product => `
        <article class="meal-detail-product">
            <div>
                <strong>${escapeHtml(product.name || 'Продукт')}</strong>
                <small>${formatMealProductMeta(product)}</small>
            </div>
            <span>${Number(product.calories || 0)} ккал</span>
        </article>
    `).join('');
}

function formatMealProductMeta(product) {
    const parts = [];
    const processingLabel = getProcessingLabel(product.processing || '');

    if (product.weight) {
        parts.push(`${Number(product.weight)} г`);
    }

    if (processingLabel) {
        parts.push(processingLabel);
    }

    parts.push(`Б ${formatMacro(product.proteins)}`);
    parts.push(`Ж ${formatMacro(product.fats)}`);
    parts.push(`У ${formatMacro(product.carbs)}`);

    return escapeHtml(parts.join(' · '));
}

function getProcessingLabel(processing) {
    const labels = {
        fry: 'Жарка',
        bake: 'Запекание',
        boil: 'Варка',
        stew: 'Тушение',
        grill: 'Гриль',
        steam: 'На пару',
        deep_fry: 'Фритюр',
        no_oil_fry: 'Жарка без масла'
    };

    return labels[processing] || '';
}

document.querySelector('.meal-detail-overlay').onclick = closeMealDetail;
document.querySelector('.meal-detail-panel').onclick = event => event.stopPropagation();
document.getElementById('btn-meal-detail-close').onclick = closeMealDetail;

document.addEventListener('pointerdown', event => {
    const row = event.target.closest('.history-swipe-row');
    if (row) {
        closeHistorySwipeRows(row);
        return;
    }

    closeHistorySwipeRows();
});

function closeHistorySwipeRows(exceptRow = null) {
    document.querySelectorAll('.history-swipe-row.open').forEach(row => {
        if (row === exceptRow) return;

        row.classList.remove('open');
        row.classList.remove('swiping');
        row.querySelector('.history-item').style.transition = '';
        row.querySelector('.history-item').style.transform = '';
    });
}

function formatMealMeta(meal) {
    const parts = [];

    if (meal.weight) {
        parts.push(`${meal.weight} г`);
    }

    parts.push(`Б ${formatMacro(meal.proteins)}`);
    parts.push(`Ж ${formatMacro(meal.fats)}`);
    parts.push(`У ${formatMacro(meal.carbs)}`);

    return escapeHtml(parts.join(' · '));
}

function parseMealDate(value) {
    if (!value) {
        return null;
    }

    const rawValue = String(value).trim();
    let date = new Date(rawValue);

    if (Number.isNaN(date.getTime()) && rawValue.includes(' ')) {
        date = new Date(rawValue.replace(' ', 'T'));
    }

    return Number.isNaN(date.getTime()) ? null : date;
}

function formatDateKey(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function formatHistoryDateLabel(date) {
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);

    const shortDate = date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long'
    });

    if (formatDateKey(date) === formatDateKey(today)) {
        return 'Сегодня';
    }

    if (formatDateKey(date) === formatDateKey(yesterday)) {
        return `Вчера, ${shortDate}`;
    }

    return date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
}

function disconnectProtectedImagesObserver() {
    if (!protectedImagesObserver) {
        return;
    }

    protectedImagesObserver.disconnect();
    protectedImagesObserver = null;
}

function revokeProtectedImageUrls(root = document) {
    root.querySelectorAll('img[data-object-url]').forEach(image => {
        URL.revokeObjectURL(image.dataset.objectUrl);
        delete image.dataset.objectUrl;
    });
}

function observeProtectedImages() {
    const images = Array.from(document.querySelectorAll('img[data-image-url]'))
        .filter(image => image.dataset.imageUrl && image.dataset.imageLoaded !== '1' && image.dataset.imageLoading !== '1');
    const renderId = String(protectedImagesRenderId);

    if (images.length === 0) {
        return;
    }

    images.forEach(image => {
        image.dataset.imageRenderId = renderId;
    });

    if (!('IntersectionObserver' in window)) {
        loadProtectedImages(images, renderId);
        return;
    }

    disconnectProtectedImagesObserver();
    protectedImagesObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) {
                return;
            }

            observer.unobserve(entry.target);
            loadProtectedImage(entry.target, renderId);
        });
    }, {
        rootMargin: '300px 0px',
        threshold: 0.01
    });

    images.forEach(image => protectedImagesObserver.observe(image));
}

async function loadProtectedImages(images = document.querySelectorAll('img[data-image-url]'), renderId = String(protectedImagesRenderId)) {
    for (const image of images) {
        await loadProtectedImage(image, renderId);
    }
}

async function loadProtectedImage(image, renderId = String(protectedImagesRenderId)) {
    if (image.dataset.imageLoading === '1' || image.dataset.imageLoaded === '1') {
        return;
    }

    if (!image.isConnected || image.dataset.imageRenderId !== renderId) {
        return;
    }

    const url = image.dataset.imageUrl;
    if (!url) {
        return;
    }

    image.dataset.imageLoading = '1';

    try {
        const response = await apiFetch(url);
        if (!response.ok) {
            return;
        }

        if (!image.isConnected || image.dataset.imageRenderId !== renderId) {
            return;
        }

        const blob = await response.blob();

        if (!image.isConnected || image.dataset.imageRenderId !== renderId) {
            return;
        }

        const objectUrl = URL.createObjectURL(blob);

        if (image.dataset.objectUrl) {
            URL.revokeObjectURL(image.dataset.objectUrl);
        }

        image.src = objectUrl;
        image.dataset.objectUrl = objectUrl;
        image.dataset.imageLoaded = '1';
    } catch (error) {
        console.error('Ошибка загрузки изображения:', error);
    } finally {
        delete image.dataset.imageLoading;
    }
}

// Удаление записи о приеме пищи
async function deleteMeal(mealId) {
    const confirmed = confirm('Вы уверены, что хотите удалить эту запись?');

    if (!confirmed) {
        return;
    }

    try {
        const response = await apiFetch('/api/delete-meal', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ meal_id: mealId })
        });

        const result = await response.json();

        if (result.status === 'success') {
            tg.showAlert('Запись успешно удалена');
            loadMealHistory();
            loadProgress();
        } else {
            tg.showAlert(result.message || 'Ошибка при удалении записи');
        }
    } catch (error) {
        tg.showAlert('Ошибка соединения с сервером');
    }
}
