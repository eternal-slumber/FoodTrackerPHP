// Product card events and additional photo analysis

function bindProductCardEvents() {
    getDraftProductCards().forEach(card => {
        if (card.dataset.loading === 'true') {
            return;
        }

        card.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', () => {
                validateDraftNumericInput(input, false);
                recalculateDraftTotal();
                updateProductCardSummary(card);
                updateProductKbjuActionState(card);
                updateDraftLimitControls();
            });
        });

        card.querySelector('.processing-select')?.addEventListener('change', () => {
            recalculateDraftTotal();
            updateProductCardSummary(card);
            updateProductKbjuActionState(card);
            updateDraftLimitControls();
            haptic('light');
        });

        const deleteButton = card.querySelector('.product-delete');
        if (deleteButton) {
            deleteButton.onclick = () => {
            const productPhoto = additionalProductPhotos.get(card.dataset.productId);
            if (productPhoto?.url) URL.revokeObjectURL(productPhoto.url);
            additionalProductPhotos.delete(card.dataset.productId);
            card.remove();
            renumberProductCards();
            syncDraftFromEditor();
            recalculateDraftTotal();
            updateDraftLimitControls();
            updateDraftSourceLabel();
            haptic('light');
            };
        }

        const portionsInput = card.querySelector('.product-portions');
        const portionsOutput = card.querySelector('.draft-portions-value');
        const changePortions = delta => {
            if (!portionsInput || !portionsOutput) return;

            const nextValue = Math.min(20, Math.max(1, Number(portionsInput.value || 1) + delta));
            portionsInput.value = String(nextValue);
            portionsOutput.textContent = String(nextValue);
            recalculateDraftTotal();
            updateProductCardSummary(card);
            updateDraftLimitControls();
            haptic('light');
        };

        card.querySelector('.draft-portions-minus')?.addEventListener('click', () => changePortions(-1));
        card.querySelector('.draft-portions-plus')?.addEventListener('click', () => changePortions(1));
        card.querySelector('.draft-product-photo-input')?.addEventListener('change', event => {
            selectAdditionalProductPhoto(card, event.target.files?.[0]);
        });

        updateProductKbjuActionState(card);
        updateProductCardSummary(card);
    });
}

function updateProductCardSummary(card) {
    const name = card.querySelector('.product-name')?.value.trim() || 'Новый продукт';
    const weight = Number(card.querySelector('.product-weight')?.value || 0);
    const portions = Number(card.querySelector('.product-portions')?.value || 1);
    const calories = Number(card.querySelector('.product-calories')?.value || 0);
    const total = Math.round(calories * weight * portions / 100);

    const summaryName = card.querySelector('.draft-product-name-summary');
    const mainName = card.querySelector('.draft-main-product-heading h2');
    const summaryCalories = card.querySelector('.draft-product-card-total');
    const mainCalories = card.querySelector('#draft-total-calories');

    if (summaryName) summaryName.textContent = name;
    if (mainName) mainName.textContent = name;
    if (mainCalories) {
        mainCalories.textContent = String(total);
    } else if (summaryCalories) {
        summaryCalories.textContent = `${total} ккал`;
    }
}

function handleProductEditorClick(event) {
    const autofillButton = event.target.closest('.kbju-autofill-button');
    if (autofillButton) {
        const card = autofillButton.closest('.product-card');
        if (!card) return;

        event.preventDefault();
        const action = autofillButton.dataset.aiAction;

        if (action === 'photo') {
            if (card.dataset.index === '0') {
                analyzeSelectedPhoto();
            } else {
                analyzeAdditionalProductPhoto(card);
            }
            return;
        }

        if (action === 'select_photo') {
            card.querySelector('.draft-product-photo-input')?.click();
            return;
        }

        if (action === 'nutrition') {
            fillProductKbjuWithAi(card);
        }
        return;
    }

    const toggle = event.target.closest('.kbju-toggle');
    if (!toggle) return;

    const card = toggle.closest('.product-card');
    if (!card) return;

    event.preventDefault();
    toggleProductKbju(card);
}

mainProductContainer.addEventListener('click', handleProductEditorClick);
productsList.addEventListener('click', handleProductEditorClick);

function selectAdditionalProductPhoto(card, file) {
    if (!file || !validateImageFileBeforeUpload(file)) {
        return;
    }

    const previousPhoto = additionalProductPhotos.get(card.dataset.productId);
    if (previousPhoto?.url) URL.revokeObjectURL(previousPhoto.url);

    additionalProductPhotos.set(card.dataset.productId, {
        file,
        url: URL.createObjectURL(file),
        draftImagePath: null
    });
    const thumb = card.querySelector('.draft-product-thumb');
    if (thumb) {
        thumb.innerHTML = `<img src="${escapeHtml(additionalProductPhotos.get(card.dataset.productId).url)}" alt="">`;
    }
    updateProductKbjuActionState(card);
    haptic('light');
}

async function analyzeAdditionalProductPhoto(card) {
    const photo = additionalProductPhotos.get(card.dataset.productId);
    if (!photo?.file || card.classList.contains('product-card-kbju-loading')) {
        return;
    }

    if (isDraftAiBusy() || isSavingMealDraft) {
        tg.showAlert('Дождитесь завершения текущего AI-запроса');
        haptic('light');
        return;
    }

    isAnalyzingAdditionalProduct = true;
    setKbjuLoadingState(card, true, 'photo');
    updateDraftLimitControls();
    const formData = new FormData();
    formData.append('photo', photo.file);

    try {
        const result = await apiRequestJson('/api/analyze-draft', {
            method: 'POST',
            body: formData,
            timeoutMs: API_TIMEOUT.AI
        });
        const product = result?.data?.products?.[0];

        if (!product) {
            throw new ApiError('AI вернул некорректные данные блюда', {
                code: 'invalid_ai_response',
                data: result
            });
        }

        photo.draftImagePath = result.data?.draft_image_path || null;

        card.querySelector('.product-name').value = product.name || '';
        card.querySelector('.product-weight').value = Number(product.weight) || 100;
        card.querySelector('.product-calories').value = product.calories ?? '';
        card.querySelector('.product-proteins').value = product.proteins ?? '';
        card.querySelector('.product-fats').value = product.fats ?? '';
        card.querySelector('.product-carbs').value = product.carbs ?? '';
        const processingSelect = card.querySelector('.processing-select');
        if (processingSelect) {
            processingSelect.value = product.processing || '';
        }
        card.dataset.scanId = createScanBatchId();
        setProductKbjuExpanded(card, true);
        updateProductCardSummary(card);
        syncDraftFromEditor();
        recalculateDraftTotal();
        haptic('success');
    } catch (error) {
        tg.showAlert(error instanceof ApiError ? getAnalyzePhotoErrorMessage(error) : 'Ошибка при анализе фото');
        haptic('error');
    } finally {
        setKbjuLoadingState(card, false);
        isAnalyzingAdditionalProduct = false;
        updateDraftLimitControls();
    }
}
