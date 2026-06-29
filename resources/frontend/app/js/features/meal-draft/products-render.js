// Product card rendering and visual state

function resetPhotoInputs() {
    cameraInput.value = '';
    galleryInput.value = '';
}

function setProductKbjuExpanded(card, isExpanded) {
    const panel = card.querySelector('.kbju-panel');
    const button = card.querySelector('.kbju-toggle');

    if (!panel || !button) {
        return;
    }

    panel.classList.toggle('hidden', !isExpanded);
    card.classList.toggle('kbju-open', isExpanded);
    button.setAttribute('aria-expanded', String(isExpanded));
    const compactLabel = card.dataset.index !== '0';
    button.innerHTML = isExpanded
        ? `<span aria-hidden="true">▴</span><span>${compactLabel ? 'Скрыть' : 'Скрыть БЖУ'}</span>`
        : `<span aria-hidden="true">▾</span><span>${compactLabel ? 'Показать' : 'Показать БЖУ'}</span>`;
}

function toggleProductKbju(card) {
    setProductKbjuExpanded(card, !card.classList.contains('kbju-open'));
    haptic('light');
}

function productCardCanAutofillKbju(card) {
    const name = card.querySelector('.product-name')?.value.trim() || '';
    const weight = Number(card.querySelector('.product-weight')?.value || 0);

    return name !== '' && weight > 0;
}

function productCardHasAnyKbju(card) {
    return [
        '.product-calories',
        '.product-proteins',
        '.product-fats',
        '.product-carbs'
    ].some(selector => String(card.querySelector(selector)?.value || '').trim() !== '');
}

function updateProductKbjuActionState(card) {
    const button = card.querySelector('.kbju-autofill-button');
    const helper = card.querySelector('.kbju-autofill-helper');

    if (!button || !helper) {
        return;
    }

    const isLoading = card.classList.contains('product-card-kbju-loading');
    const isMainProduct = card.dataset.index === '0';
    const productPhoto = additionalProductPhotos.get(card.dataset.productId);
    const hasPhoto = isMainProduct ? Boolean(selectedPhotoFile) : Boolean(productPhoto?.file);
    const canAutofill = productCardCanAutofillKbju(card);
    const hasKbju = productCardHasAnyKbju(card);
    const isBusy = isLoading || isDraftAiBusy() || isSavingMealDraft;

    const shouldHideDuringProcessing = isLoading || (isMainProduct && isAnalyzingPhoto);
    button.hidden = hasKbju || shouldHideDuringProcessing;
    helper.hidden = hasKbju || shouldHideDuringProcessing;

    if (hasKbju || shouldHideDuringProcessing) {
        return;
    }

    if (isBusy) {
        button.disabled = true;
        button.dataset.aiAction = '';
        button.querySelector('.kbju-autofill-label').textContent = 'AI заполняет данные...';
        helper.textContent = 'Дождитесь завершения обработки';
        return;
    }

    if (canAutofill) {
        button.disabled = false;
        button.dataset.aiAction = 'nutrition';
        button.querySelector('.kbju-autofill-label').textContent = 'Автозаполнить КБЖУ';
        helper.textContent = hasPhoto
            ? 'AI использует указанное название и вес, фото останется для истории'
            : 'AI предложит значения по названию и весу';
        return;
    }

    if (hasPhoto) {
        button.disabled = false;
        button.dataset.aiAction = 'photo';
        button.querySelector('.kbju-autofill-label').textContent = 'Отсканировать фото';
        helper.textContent = 'AI заполнит название, вес и КБЖУ';
        return;
    }

    button.dataset.aiAction = isMainProduct ? '' : 'select_photo';
    button.disabled = isMainProduct;
    button.hidden = isMainProduct;
    helper.hidden = isMainProduct;
    button.querySelector('.kbju-autofill-label').textContent = 'Заполнить по фото';
    helper.textContent = 'Или укажите название и вес для автозаполнения';
}

function updateAllProductKbjuActionStates() {
    getDraftProductCards()
        .filter(card => card.dataset.loading !== 'true')
        .forEach(updateProductKbjuActionState);
}

function createProductCard(product, index) {
    const processingOptions = PROCESSING_OPTIONS.map(option => {
        const selected = option.value === (product.processing || '') ? ' selected' : '';
        return `<option value="${option.value}"${selected}>${option.label}</option>`;
    }).join('');
    const productPhoto = additionalProductPhotos.get(product.clientId);
    const productThumb = productPhoto?.url
        ? `<img src="${escapeHtml(productPhoto.url)}" alt="">`
        : String(index);
    const productName = escapeHtml(product.name || 'Новый продукт');
    const productCalories = Math.round(
        (Number(product.calories) || 0)
        * (Number(product.weight) || 100)
        * (Number(product.portions) || 1)
        / 100
    );

    return `
        <div class="product-card${index === 0 ? ' draft-main-product-form' : ' draft-product-card'}"
             data-index="${index}"
             data-product-id="${escapeHtml(product.clientId || `draft-product-${nextDraftProductId++}`)}"
             data-scan-id="${escapeHtml(product.scanId || '')}">
            <div class="product-card-header">
                ${index === 0 ? `
                    <div class="draft-main-product-heading">
                        <h2>${productName}</h2>
                        <p>Основной продукт</p>
                    </div>
                    <strong class="draft-main-total"><span id="draft-total-calories">${productCalories}</span> ккал</strong>
                ` : `
                    <span class="draft-product-thumb">${productThumb}</span>
                    <strong class="draft-product-name-summary">${productName}</strong>
                    <span class="draft-product-card-total">${productCalories} ккал</span>
                    <button class="product-delete" type="button" title="Удалить продукт" aria-label="Удалить продукт">
                        <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                            <path d="M6.5 6.5h7l-.6 9H7.1l-.6-9ZM5 6.5h10M8 4h4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/>
                        </svg>
                    </button>
                `}
            </div>
            ${index === 0 ? `
                <div class="kbju-autofill">
                    <button class="kbju-autofill-button draft-main-autofill" type="button">
                        <span class="kbju-autofill-label">Автозаполнить КБЖУ</span>
                    </button>
                    <p class="kbju-autofill-helper"></p>
                </div>
                <p class="draft-kbju-caption">На порцию</p>
                <div class="draft-nutrition-summary" aria-label="КБЖУ на одну порцию">
                    <div><strong id="draft-summary-calories">0</strong><span>ккал</span></div>
                    <div><strong id="draft-summary-protein">0</strong><span>белки</span></div>
                    <div><strong id="draft-summary-fat">0</strong><span>жиры</span></div>
                    <div><strong id="draft-summary-carbs">0</strong><span>углеводы</span></div>
                </div>
                <div class="draft-main-form-grid">
                    <label class="draft-main-field wide">
                        <span>Название</span>
                        <input type="text" class="product-name" value="${escapeHtml(product.name)}" placeholder="Например, куриный салат">
                    </label>
                    <div class="draft-main-portion-row">
                        <label class="draft-main-field draft-main-weight-field">
                            <span>Вес порции, г</span>
                            <input type="number" class="product-weight" value="${escapeHtml(product.weight ?? '')}" min="1" max="5000" placeholder="0">
                        </label>
                        <div class="draft-main-portions">
                            <span>Порции</span>
                            <div class="draft-portion-stepper">
                                <button class="draft-portions-minus" type="button" aria-label="Уменьшить количество порций">−</button>
                                <output class="draft-portions-value">${Math.max(1, Number(product.portions) || 1)}</output>
                                <button class="draft-portions-plus" type="button" aria-label="Увеличить количество порций">+</button>
                            </div>
                            <input class="product-portions" type="hidden" value="${Math.max(1, Number(product.portions) || 1)}">
                        </div>
                    </div>
                </div>
                <p class="draft-kbju-caption">На 100 г продукта</p>
                <div class="draft-main-kbju-grid">
                    <label class="draft-main-field">
                        <span>Ккал</span>
                        <input type="number" class="product-calories" value="${product.calories}" min="0" placeholder="0">
                    </label>
                    <label class="draft-main-field">
                        <span>Белки</span>
                        <input type="number" class="product-proteins kbju-field" value="${product.proteins}" min="0" placeholder="0">
                    </label>
                    <label class="draft-main-field">
                        <span>Жиры</span>
                        <input type="number" class="product-fats kbju-field" value="${product.fats}" min="0" placeholder="0">
                    </label>
                    <label class="draft-main-field">
                        <span>Углеводы</span>
                        <input type="number" class="product-carbs kbju-field" value="${product.carbs}" min="0" placeholder="0">
                    </label>
                </div>
            ` : `
                <div class="name-wrap">
                    <label class="field-label">Название</label>
                    <input type="text" class="product-name" value="${escapeHtml(product.name)}" placeholder="Например, хлеб">
                </div>
            `}
            ${index === 0 ? '' : `<label class="draft-processing-row">
                <span>Термообработка</span>
                <select class="processing-select">
                    ${processingOptions}
                </select>
            </label>`}
            ${index === 0 ? '' : `<div class="kbju-autofill">
                <button class="kbju-autofill-button" type="button">
                    <svg class="kbju-autofill-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M11 2.75a.75.75 0 0 1 1.43-.32l1.77 3.92 3.92 1.77a.75.75 0 0 1 0 1.36l-3.92 1.77-1.77 3.92a.75.75 0 0 1-1.36 0L9.3 11.25 5.38 9.48a.75.75 0 0 1 0-1.36L9.3 6.35l1.77-3.92A.75.75 0 0 1 11 2.75Zm.75 1.8-1.2 2.66a.75.75 0 0 1-.38.38l-2.66 1.2 2.66 1.2c.17.08.3.21.38.38l1.2 2.66 1.2-2.66c.08-.17.21-.3.38-.38l2.66-1.2-2.66-1.2a.75.75 0 0 1-.38-.38l-1.2-2.66ZM18.5 13.25a.75.75 0 0 1 .68.43l.66 1.48 1.48.66a.75.75 0 0 1 0 1.36l-1.48.66-.66 1.48a.75.75 0 0 1-1.36 0l-.66-1.48-1.48-.66a.75.75 0 0 1 0-1.36l1.48-.66.66-1.48a.75.75 0 0 1 .68-.43Zm0 2.58-.08.17a.75.75 0 0 1-.38.38l-.17.08.17.08c.17.08.3.21.38.38l.08.17.08-.17c.08-.17.21-.3.38-.38l.17-.08-.17-.08a.75.75 0 0 1-.38-.38l-.08-.17Z" fill="currentColor"/>
                    </svg>
                    <span class="kbju-autofill-label">Автозаполнить КБЖУ</span>
                </button>
                <p class="kbju-autofill-helper">Сначала укажите название и вес</p>
                <input class="draft-product-photo-input" type="file" accept="image/*" hidden>
            </div>
            <div class="draft-product-base-row">
                    <div class="weight-wrap">
                        <label class="field-label">Вес, г</label>
                        <input type="number" class="product-weight" value="${Number(product.weight) || 100}" min="1" max="5000">
                    </div>
                <div>
                    <label class="field-label">Ккал / 100г</label>
                    <input type="number" class="product-calories" value="${product.calories}" min="0" placeholder="0">
                </div>
                <div class="draft-kbju-toggle-wrap">
                    <span class="field-label">БЖУ</span>
                    <button class="kbju-toggle" type="button" aria-expanded="false">
                        <span aria-hidden="true">▾</span><span>Показать</span>
                    </button>
                </div>
            </div>
            <div class="kbju-panel hidden">
                <div class="kbju-field-wrap">
                    <input type="number" class="product-proteins kbju-field" value="${product.proteins}" min="0" placeholder="0">
                    <span class="kbju-label">бел</span>
                </div>
                <div class="kbju-field-wrap">
                    <input type="number" class="product-fats kbju-field" value="${product.fats}" min="0" placeholder="0">
                    <span class="kbju-label">жир</span>
                </div>
                <div class="kbju-field-wrap">
                    <input type="number" class="product-carbs kbju-field" value="${product.carbs}" min="0" placeholder="0">
                    <span class="kbju-label">угл</span>
                </div>
            </div>
            <div class="draft-product-loading-overlay" aria-hidden="true">
                <span class="draft-product-loading-spinner" aria-hidden="true"></span>
                <strong class="draft-product-loading-label">AI заполняет КБЖУ</strong>
                <small>Карточка обновится автоматически</small>
            </div>`}
            ${index === 0 ? `<div class="draft-main-product-loading-overlay" aria-hidden="true">
                <span class="draft-main-product-loading-spinner" aria-hidden="true"></span>
                <strong>AI автозаполняет блюдо</strong>
                <small>Название и КБЖУ появятся автоматически</small>
            </div>` : ''}
        </div>
    `;
}

function createScanLoadingCard(scanId, index) {
    return `
        <div class="product-card product-card-loading" data-index="${index}" data-scan-id="${escapeHtml(scanId)}" data-loading="true" aria-live="polite">
            <div class="product-card-header">
                <span class="input-label">Продукт ${index + 1}</span>
                <span class="scan-loading-badge">AI</span>
            </div>
            <div class="scan-loading-content">
                <span class="scan-loading-spinner" aria-hidden="true"></span>
                <div>
                    <strong class="shimmer-text">Распознаю блюдо</strong>
                    <p>Заполню название, вес и КБЖУ после ответа AI</p>
                </div>
            </div>
        </div>
    `;
}

function addScanLoadingCard(scanId) {
    productsList.insertAdjacentHTML(
        'beforeend',
        createScanLoadingCard(scanId, getCurrentProductCount())
    );
    renumberProductCards();
    updateDraftLimitControls();
    productsList.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function removeScanLoadingCard(scanId) {
    const card = Array.from(productsList.querySelectorAll('.product-card-loading'))
        .find(item => item.dataset.scanId === scanId);

    if (!card) {
        return;
    }

    card.remove();
    renumberProductCards();
    updateDraftLimitControls();
    recalculateDraftTotal();
}

function renumberProductCards() {
    productsList.querySelectorAll('.product-card').forEach((card, index) => {
        const productIndex = index + 1;
        card.dataset.index = String(productIndex);

        const thumb = card.querySelector('.draft-product-thumb');
        if (thumb && !thumb.querySelector('img')) {
            thumb.textContent = String(productIndex);
        }
    });
}

