// Product draft data, limits and generated names

function collectDraftProducts() {
    return getDraftProductCards().filter(card => card.dataset.loading !== 'true').slice(0, MAX_PRODUCTS_PER_MEAL).map(card => {
        const calories = card.querySelector('.product-calories').value;
        const proteins = card.querySelector('.product-proteins').value;
        const fats = card.querySelector('.product-fats').value;
        const carbs = card.querySelector('.product-carbs').value;

        const baseWeight = parseInt(card.querySelector('.product-weight').value, 10) || 0;
        const portions = Math.max(1, parseInt(card.querySelector('.product-portions')?.value || '1', 10) || 1);

        return {
            clientId: card.dataset.productId,
            name: card.querySelector('.product-name').value.trim(),
            weight: baseWeight * portions,
            baseWeight,
            portions,
            processing: card.dataset.index === '0'
                ? ''
                : card.querySelector('.processing-select')?.value || '',
            scanId: card.dataset.scanId || '',
            kbju: {
                calories,
                proteins,
                fats,
                carbs
            }
        };
    });
}

function getDraftScanCount() {
    const products = !editorScreen.classList.contains('hidden')
        ? getDraftProductCards().map(card => ({ scanId: card.dataset.scanId || '' }))
        : Array.isArray(mealDraft.products) ? mealDraft.products : [];

    const scanIds = products
        .map(product => String(product.scanId || '').trim())
        .filter(Boolean);

    return new Set(scanIds).size;
}

function createScanBatchId() {
    return `scan-${Date.now()}-${nextScanBatchNumber++}`;
}

function getCurrentProductCount() {
    if (!editorScreen.classList.contains('hidden')) {
        return getDraftProductCards().length;
    }

    return Array.isArray(mealDraft.products) ? mealDraft.products.length : 0;
}

function showProductLimitAlert() {
    tg.showAlert(`В одном приеме можно добавить до ${MAX_PRODUCTS_PER_MEAL} продуктов`);
    haptic('error');
}

function productsMissingKbju(products) {
    return products.filter(product => ['calories', 'proteins', 'fats', 'carbs'].some(
        key => !String(product.kbju?.[key] || '').trim()
    ));
}

function productCardMissingKbju(card) {
    return [
        '.product-calories',
        '.product-proteins',
        '.product-fats',
        '.product-carbs'
    ].some(selector => !card.querySelector(selector).value.trim());
}

function fillMissingCardKbju(card, nutrients) {
    const fields = {
        calories: card.querySelector('.product-calories'),
        proteins: card.querySelector('.product-proteins'),
        fats: card.querySelector('.product-fats'),
        carbs: card.querySelector('.product-carbs')
    };

    Object.entries(fields).forEach(([key, input]) => {
        if (input.value.trim() !== '') {
            return;
        }

        const value = nutrients?.[key];
        input.value = value === undefined || value === null ? 0 : value;
    });
}

function setKbjuLoadingState(card, isLoading, mode = 'nutrition') {
    const button = card.querySelector('.kbju-autofill-button');
    const overlay = card.querySelector('.draft-product-loading-overlay, .draft-main-product-loading-overlay');
    const label = card.querySelector('.draft-product-loading-label');
    card.classList.toggle('product-card-kbju-loading', isLoading);
    card.setAttribute('aria-busy', String(isLoading));
    card.inert = isLoading;
    if (isLoading) {
        card.dataset.loadingMode = mode;
    } else {
        delete card.dataset.loadingMode;
    }

    if (overlay) {
        overlay.setAttribute('aria-hidden', String(!isLoading));
    }

    if (label) {
        label.textContent = mode === 'photo' ? 'AI анализирует фото' : 'AI заполняет КБЖУ';
    }

    if (button) {
        button.disabled = isLoading;
    }

    updateProductKbjuActionState(card);
}

function confirmAsync(message) {
    return new Promise(resolve => {
        if (typeof tg.showConfirm === 'function') {
            tg.showConfirm(message, confirmed => resolve(Boolean(confirmed)));
            return;
        }

        resolve(window.confirm(message));
    });
}

function syncDraftFromEditor() {
    if (editorScreen.classList.contains('hidden')) return;

    const products = collectDraftProducts();
    mealDraft.mealName = mealNameEditedByUser
        ? mealNameInput.value.trim()
        : buildGeneratedMealName(products);
    mealDraft.products = products.map(product => ({
        clientId: product.clientId,
        name: product.name,
        weight: product.baseWeight,
        portions: product.portions,
        processing: product.processing,
        calories: product.kbju.calories,
        proteins: product.kbju.proteins,
        fats: product.kbju.fats,
        carbs: product.kbju.carbs,
        scanId: product.scanId
    }));

    if (!mealNameEditedByUser) {
        mealNameInput.value = mealDraft.mealName;
    }
}

function isEmptyDraftProduct(product) {
    return !String(product?.name || '').trim()
        && !String(product?.calories || '').trim()
        && !String(product?.proteins || '').trim()
        && !String(product?.fats || '').trim()
        && !String(product?.carbs || '').trim();
}

function productBaseForAppend(products) {
    if (products.length === 1 && isEmptyDraftProduct(products[0])) {
        return [];
    }

    return products;
}

function limitDraftProducts(draft) {
    const products = Array.isArray(draft.products) ? draft.products : [];
    const limitedProducts = products.slice(0, MAX_PRODUCTS_PER_MEAL);

    if (products.length > MAX_PRODUCTS_PER_MEAL) {
        tg.showAlert(`AI нашел больше ${MAX_PRODUCTS_PER_MEAL} продуктов, лишние не добавлены`);
        haptic('error');
    }

    return {
        ...draft,
        products: limitedProducts.length > 0 ? limitedProducts : [createEmptyProduct()]
    };
}

function appendAnalyzedDraft(analyzedDraft) {
    syncDraftFromEditor();

    const currentProducts = productBaseForAppend(Array.isArray(mealDraft.products) ? mealDraft.products : []);
    const incomingProducts = Array.isArray(analyzedDraft.products) ? analyzedDraft.products : [];
    const remainingSlots = MAX_PRODUCTS_PER_MEAL - currentProducts.length;

    if (remainingSlots <= 0) {
        showProductLimitAlert();
        return;
    }

    const productsToAdd = incomingProducts.slice(0, remainingSlots);
    const nextProducts = currentProducts.concat(productsToAdd);

    mealDraft = {
        ...mealDraft,
        source: 'photo',
        mealName: mealDraft.mealName || analyzedDraft.mealName || '',
        products: nextProducts.length > 0 ? nextProducts : [createEmptyProduct()],
        draftImagePath: mealDraft.draftImagePath || analyzedDraft.draftImagePath || null
    };

    if (incomingProducts.length > productsToAdd.length) {
        tg.showAlert(`Добавлено ${productsToAdd.length} продуктов. Лимит приема: ${MAX_PRODUCTS_PER_MEAL}`);
        haptic('error');
    }
}

function updateDraftLimitControls() {
    const productCount = getCurrentProductCount();
    const scanCount = getDraftScanCount();
    const productsLimitReached = productCount >= MAX_PRODUCTS_PER_MEAL;
    const mainProductCard = mainProductContainer.querySelector('.product-card');
    const mainProductComplete = mainProductCard
        && productCardCanAutofillKbju(mainProductCard)
        && !productCardMissingKbju(mainProductCard);

    const aiBusy = isDraftAiBusy();
    btnAddProduct.disabled = productsLimitReached || aiBusy;
    const addProductTitle = btnAddProduct.querySelector('.draft-add-product-copy strong');
    const addProductHint = btnAddProduct.querySelector('.draft-add-product-copy small');
    const addProductIcon = btnAddProduct.querySelector('.draft-add-product-icon');
    if (addProductTitle) {
        addProductTitle.textContent = productsLimitReached ? 'Лимит продуктов' : 'Добавить продукт';
    }
    if (addProductHint) {
        addProductHint.textContent = productsLimitReached
            ? `В одном приеме доступно до ${MAX_PRODUCTS_PER_MEAL} продуктов`
            : 'Хлеб, овощи, соус или другую часть приема';
    }
    if (addProductIcon) {
        addProductIcon.textContent = productsLimitReached ? '✓' : '+';
    }
    draftProductsCount.textContent = `${Math.max(0, productCount - 1)} / ${MAX_PRODUCTS_PER_MEAL - 1}`;

    updateAllProductKbjuActionStates();

    btnSaveMeal.classList.toggle('is-analyzing', aiBusy && !isSavingMealDraft);

    if (!isSavingMealDraft) {
        btnSaveMeal.disabled = aiBusy || !mainProductComplete;
        btnSaveMeal.textContent = aiBusy
            ? 'Идет анализ...'
            : `Добавить в ${draftMealType.value || getMealTypeByTime()}`;
    }

    updateAnalyzePhotoButton();
}

function updateDraftSourceLabel() {
    if (isAnalyzingPhoto && !editorScreen.classList.contains('hidden')) {
        draftSourceLabel.textContent = 'AI распознает блюдо, можно дождаться результата прямо здесь';
        return;
    }

    const scanInfo = `AI-сканы: ${getDraftScanCount()}/${MAX_DRAFT_SCANS_PER_MEAL}`;
    const hasScannedProducts = getDraftScanCount() > 0;

    draftSourceLabel.textContent = mealDraft.source === 'photo' && hasScannedProducts
        ? `AI заполнил черновик, проверьте перед сохранением. ${scanInfo}`
        : `Заполните продукты вручную. ${scanInfo}`;
}

function getMealTypeByTime(date = new Date()) {
    const hour = date.getHours();

    if (hour >= 5 && hour < 11) {
        return 'Завтрак';
    }

    if (hour >= 11 && hour < 16) {
        return 'Обед';
    }

    if (hour >= 16 && hour < 22) {
        return 'Ужин';
    }

    return 'Перекус';
}

function buildGeneratedMealName(products) {
    const productNames = (Array.isArray(products) ? products : [])
        .map(product => String(product.name || '').trim())
        .filter(Boolean);
    const mealType = draftMealType?.value || getMealTypeByTime();
    const name = productNames.length > 0
        ? `${mealType}: ${productNames.join(', ')}`
        : mealType;

    return name.length > 120 ? `${name.slice(0, 117)}...` : name;
}

function updateAutoMealNameFromProducts() {
    if (mealNameEditedByUser || editorScreen.classList.contains('hidden')) return;

    mealNameInput.value = buildGeneratedMealName(collectDraftProducts());
}

function startAiPhotoSelection(intent, input) {
    if (isDraftAiBusy() || isSavingMealDraft) {
        tg.showAlert('Дождитесь завершения текущего AI-запроса');
        haptic('light');
        return;
    }

    if (intent === PHOTO_INTENTS.APPEND_SCAN) {
        syncDraftFromEditor();

        if (getDraftScanCount() >= MAX_DRAFT_SCANS_PER_MEAL) {
            tg.showAlert(`В одном приеме можно сделать до ${MAX_DRAFT_SCANS_PER_MEAL} AI-сканов`);
            haptic('error');
            return;
        }

        if (getCurrentProductCount() >= MAX_PRODUCTS_PER_MEAL) {
            showProductLimitAlert();
            return;
        }
    }

    photoIntent = intent;
    input.value = '';
    input.click();
}
