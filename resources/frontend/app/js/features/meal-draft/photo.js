// Main photo analysis and attachment handling

function handleAiPhotoSelected(file) {
    if (!file) return;

    if (!validateImageFileBeforeUpload(file)) {
        resetPhotoInputs();
        return;
    }

    selectedPhotoFile = file;
    if (selectedPhotoUrl) URL.revokeObjectURL(selectedPhotoUrl);
    selectedPhotoUrl = URL.createObjectURL(file);
    draftPhotoImg.src = selectedPhotoUrl;
    renderMainDraftPhoto();

    if (photoIntent === PHOTO_INTENTS.ATTACH_ONLY) {
        syncDraftFromEditor();
        mealDraft.draftImagePath = null;
        showMealStep('editor');
        return;
    }

    if (photoIntent === PHOTO_INTENTS.APPEND_SCAN) {
        syncDraftFromEditor();
        mealDraft.source = 'photo';
        showMealStep('editor');
        return;
    }

    if (photoIntent !== PHOTO_INTENTS.APPEND_SCAN) {
        mealDraft = createEmptyDraft();
        mealDraft.source = 'photo';
        mealNameEditedByUser = false;
    }

    showMealStep('editor');
    updateAnalyzePhotoButton();
}

async function analyzeSelectedPhoto() {
    if (isDraftAiBusy()) {
        tg.showAlert('Дождитесь завершения текущего AI-запроса');
        haptic('light');
        return;
    }

    if (!selectedPhotoFile) {
        tg.showAlert('Выберите фото');
        haptic('error');
        return;
    }

    if (getDraftScanCount() >= MAX_DRAFT_SCANS_PER_MEAL) {
        tg.showAlert(`В одном приеме можно сделать до ${MAX_DRAFT_SCANS_PER_MEAL} AI-сканов`);
        haptic('error');
        return;
    }

    if (photoIntent === PHOTO_INTENTS.APPEND_SCAN && getCurrentProductCount() >= MAX_PRODUCTS_PER_MEAL) {
        showProductLimitAlert();
        return;
    }

    const isAppendScan = photoIntent === PHOTO_INTENTS.APPEND_SCAN;
    const isMainProductScan = photoIntent === PHOTO_INTENTS.ATTACH_ONLY;
    const appendScanId = isAppendScan ? createScanBatchId() : null;

    if (isMainProductScan) {
        syncDraftFromEditor();
    }

    isAnalyzingPhoto = true;
    updateAnalyzePhotoButton();
    updateDraftLimitControls();
    tg.MainButton.showProgress(false);

    if (isAppendScan && appendScanId) {
        addScanLoadingCard(appendScanId);
        updateDraftSourceLabel();
    }

    const formData = new FormData();
    formData.append('photo', selectedPhotoFile);

    try {
        const result = await apiRequestJson('/api/analyze-draft', {
            method: 'POST',
            body: formData,
            timeoutMs: API_TIMEOUT.AI
        });

        const scanId = appendScanId || createScanBatchId();
        const analyzedDraft = normalizeDraft(result.data, 'photo');
        analyzedDraft.products = analyzedDraft.products.map(product => ({
            ...product,
            scanId
        }));

        if (photoIntent === PHOTO_INTENTS.APPEND_SCAN) {
            appendAnalyzedDraft(analyzedDraft);
        } else if (isMainProductScan) {
            replaceMainProductFromAnalyzedDraft(analyzedDraft);
        } else {
            mealDraft = limitDraftProducts(analyzedDraft);
        }

        photoIntent = PHOTO_INTENTS.AI_SCAN;
        haptic('success');
        showMealStep('editor');
    } catch (error) {
        if (appendScanId) {
            removeScanLoadingCard(appendScanId);
        }
        tg.showAlert(error instanceof ApiError ? getAnalyzePhotoErrorMessage(error) : 'Ошибка при анализе фото');
        haptic('error');
    } finally {
        if (isAppendScan) {
            photoIntent = PHOTO_INTENTS.AI_SCAN;
        }
        isAnalyzingPhoto = false;
        updateAnalyzePhotoButton();
        if (!editorScreen.classList.contains('hidden')) {
            updateDraftSourceLabel();
            updateDraftLimitControls();
        }
        tg.MainButton.hideProgress();
    }
}

function replaceMainProductFromAnalyzedDraft(analyzedDraft) {
    const currentProducts = Array.isArray(mealDraft.products) ? mealDraft.products : [];
    const analyzedMainProduct = analyzedDraft.products?.[0] || createEmptyProduct();

    mealDraft = {
        ...mealDraft,
        source: 'photo',
        products: [analyzedMainProduct, ...currentProducts.slice(1)].slice(0, MAX_PRODUCTS_PER_MEAL),
        draftImagePath: analyzedDraft.draftImagePath || mealDraft.draftImagePath || null
    };
}

function getAnalyzePhotoErrorMessage(error) {
    if (error.status === 413) {
        return 'Фото слишком большое. Максимум 10 МБ';
    }

    if (error.status === 504 || error.code === 'timeout') {
        return 'AI не успел ответить. Попробуйте меньшее фото или другую модель';
    }

    if (error.status === 502) {
        return error.message || 'AI сейчас недоступен. Попробуйте позже';
    }

    return error.message || 'Не удалось распознать фото';
}

function updateAnalyzePhotoButton() {
    const aiBusy = isDraftAiBusy();
    btnAnalyzePhoto.disabled = aiBusy;
    btnAnalyzePhotoText.textContent = isAnalyzingPhoto ? 'AI анализирует фото' : 'Отсканировать фото';
    btnAnalyzePhoto.classList.toggle('is-loading', isAnalyzingPhoto);
    btnAnalyzePhoto.classList.add('hidden');
    btnChangePhoto.disabled = aiBusy;
    btnRemoveMainPhoto.disabled = aiBusy;
    btnDraftPhotoSelect.disabled = aiBusy;
    btnChangePhoto.setAttribute('aria-hidden', String(isAnalyzingPhoto));
    photoActions?.classList.toggle('is-analyzing', isAnalyzingPhoto);
    draftPhotoPreview?.classList.toggle('is-processing', isAnalyzingPhoto);
    setMainProductScanState(isAnalyzingPhoto);
}

function setMainProductScanState(isLoading) {
    const card = mainProductContainer.querySelector('.draft-main-product-form');
    if (!card) {
        return;
    }

    card.classList.toggle('main-product-scan-loading', isLoading);
    card.setAttribute('aria-busy', String(isLoading));
    card.inert = isLoading;
}

function renderMainDraftPhoto() {
    const hasPhoto = Boolean(selectedPhotoUrl);

    draftPhotoHero?.classList.toggle('has-photo', hasPhoto);
    draftPhotoEmpty?.classList.toggle('hidden', hasPhoto);
    draftPhotoPreview?.classList.toggle('hidden', !hasPhoto);

    if (hasPhoto) {
        draftPhotoImg.src = selectedPhotoUrl;
    } else {
        draftPhotoImg.removeAttribute('src');
    }

    updateAnalyzePhotoButton();
    updateAllProductKbjuActionStates();
}

function removeMainDraftPhoto() {
    if (selectedPhotoUrl) {
        URL.revokeObjectURL(selectedPhotoUrl);
    }

    selectedPhotoFile = null;
    selectedPhotoUrl = null;
    mealDraft.draftImagePath = null;
    resetPhotoInputs();
    renderMainDraftPhoto();
    haptic('light');
}

function normalizeDraft(data, source) {
    const products = Array.isArray(data?.products) && data.products.length > 0
        ? data.products.map(product => ({
            clientId: `draft-product-${nextDraftProductId++}`,
            name: product.name || '',
            weight: product.weight || 100,
            portions: product.portions || 1,
            calories: product.calories || '',
            proteins: product.proteins || '',
            fats: product.fats || '',
            carbs: product.carbs || '',
            processing: product.processing || '',
            scanId: product.scanId || ''
        }))
        : [createEmptyProduct()];

    return {
        source,
        mealName: data?.meal_name || '',
        products,
        draftImagePath: data?.draft_image_path || null
    };
}

function startManualDraft() {
    mealDraft = createEmptyDraft();
    mealDraft.source = 'manual';
    mealNameEditedByUser = false;
    showMealStep('editor');
}

function renderDraftEditor() {
    mealNameInput.value = mealNameEditedByUser
        ? mealDraft.mealName || ''
        : buildGeneratedMealName(mealDraft.products);
    const products = Array.isArray(mealDraft.products) && mealDraft.products.length > 0
        ? mealDraft.products
        : [createEmptyProduct()];
    const mainProduct = products[0];
    const additionalProducts = products.slice(1, MAX_PRODUCTS_PER_MEAL);

    mainProductContainer.innerHTML = createProductCard(mainProduct, 0);
    productsList.innerHTML = additionalProducts
        .map((product, index) => createProductCard(product, index + 1))
        .join('');
    updateDraftSourceLabel();
    renderDraftImageField();
    bindProductCardEvents();
    recalculateDraftTotal();
    updateDraftLimitControls();
    draftMealType.value = draftMealType.value || getMealTypeByTime();
}

function renderDraftImageField() {
    const hasImage = Boolean(mealDraft.draftImagePath || manualPhotoUrl);

    manualPhotoPreview.classList.toggle('hidden', !manualPhotoUrl);
    btnAddManualPhoto.setAttribute('aria-label', hasImage ? 'Заменить фото' : 'Добавить фото для истории');
    btnAddManualPhoto.title = hasImage ? 'Заменить фото' : 'Добавить фото для истории';
    draftImageStatus.textContent = hasImage
        ? 'Фото будет показано в истории после сохранения'
        : 'Можно добавить миниатюру для истории';

    if (manualPhotoUrl) {
        manualPhotoImg.src = manualPhotoUrl;
    }
}

async function uploadAttachmentPhoto(file) {
    if (!file) return;

    if (!validateImageFileBeforeUpload(file)) {
        attachmentPhotoInput.value = '';
        return;
    }

    photoIntent = PHOTO_INTENTS.ATTACH_ONLY;

    if (manualPhotoUrl) URL.revokeObjectURL(manualPhotoUrl);
    manualPhotoUrl = URL.createObjectURL(file);
    renderDraftImageField();

    const previousPath = mealDraft.draftImagePath;
    const formData = new FormData();
    formData.append('photo', file);

    btnAddManualPhoto.disabled = true;
    btnAddManualPhoto.textContent = 'Загружаю...';

    try {
        const result = await apiRequestJson('/api/upload-draft-image', {
            method: 'POST',
            body: formData,
            timeoutMs: API_TIMEOUT.UPLOAD
        });

        mealDraft.draftImagePath = result.data?.draft_image_path || null;
        haptic('success');
    } catch (error) {
        mealDraft.draftImagePath = previousPath;
        tg.showAlert(error?.message || 'Ошибка загрузки фото');
        haptic('error');
    } finally {
        btnAddManualPhoto.disabled = false;
        renderDraftImageField();
        attachmentPhotoInput.value = '';
    }
}

function validateImageFileBeforeUpload(file) {
    if (file.type && !ALLOWED_IMAGE_TYPES.includes(file.type)) {
        tg.showAlert('Поддерживаются только JPEG, PNG и WebP');
        haptic('error');
        return false;
    }

    if (file.size > MAX_UPLOAD_SIZE_BYTES) {
        tg.showAlert(`Фото слишком большое: ${formatFileSize(file.size)}. Максимум 10 МБ`);
        haptic('error');
        return false;
    }

    return true;
}

function formatFileSize(bytes) {
    return `${(bytes / 1024 / 1024).toFixed(1)} МБ`;
}
