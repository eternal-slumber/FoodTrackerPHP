// Meal draft AI actions

function startAttachmentPhotoSelection() {
    photoIntent = PHOTO_INTENTS.ATTACH_ONLY;
    attachmentPhotoInput.value = '';
    attachmentPhotoInput.click();
}

async function fillMissingKbjuWithAi() {
    if (isDraftAiBusy() || isSavingMealDraft) {
        tg.showAlert('Дождитесь завершения текущего AI-запроса');
        haptic('light');
        return;
    }

    const cards = getDraftProductCards();
    const cardsToFill = cards.filter(card => {
        const name = card.querySelector('.product-name').value.trim();
        return name !== '' && productCardMissingKbju(card);
    });

    if (cardsToFill.length === 0) {
        tg.showAlert('Нет продуктов с названием и пустыми полями КБЖУ');
        haptic('light');
        return;
    }

    isFillingKbju = true;
    const previousDraftSourceText = draftSourceLabel.textContent;
    draftSourceLabel.textContent = 'AI заполняет недостающие калории и БЖУ';
    updateDraftLimitControls();

    try {
        for (const card of cardsToFill) {
            setKbjuLoadingState(card, true);
            const productName = card.querySelector('.product-name').value.trim();
            const processing = card.querySelector('.processing-select')?.value || '';
            const result = await apiRequestJson('/api/product-nutrition', {
                method: 'POST',
                json: { product_name: productName, processing },
                timeoutMs: API_TIMEOUT.AI
            });

            fillMissingCardKbju(card, result.data || {});
            setKbjuLoadingState(card, false);
        }

        syncDraftFromEditor();
        recalculateDraftTotal();
        haptic('success');
    } catch (error) {
        tg.showAlert(error?.message || 'Ошибка при заполнении КБЖУ');
        haptic('error');
    } finally {
        cardsToFill.forEach(card => setKbjuLoadingState(card, false));
        isFillingKbju = false;
        draftSourceLabel.textContent = previousDraftSourceText;
        updateDraftLimitControls();
    }
}

async function fillProductKbjuWithAi(card) {
    if (isDraftAiBusy() || isSavingMealDraft) {
        tg.showAlert('Дождитесь завершения текущего AI-запроса');
        haptic('light');
        return;
    }

    if (!productCardCanAutofillKbju(card)) {
        tg.showAlert('Сначала укажите название и вес');
        haptic('error');
        updateProductKbjuActionState(card);
        return;
    }

    isFillingKbju = true;
    const previousDraftSourceText = draftSourceLabel.textContent;
    draftSourceLabel.textContent = 'AI заполняет КБЖУ продукта';
    updateDraftLimitControls();
    setKbjuLoadingState(card, true);

    try {
        const productName = card.querySelector('.product-name').value.trim();
        const processing = card.querySelector('.processing-select')?.value || '';
        const result = await apiRequestJson('/api/product-nutrition', {
            method: 'POST',
            json: { product_name: productName, processing },
            timeoutMs: API_TIMEOUT.AI
        });

        fillMissingCardKbju(card, result.data || {});
        setProductKbjuExpanded(card, true);
        syncDraftFromEditor();
        recalculateDraftTotal();
        haptic('success');
    } catch (error) {
        tg.showAlert(error?.message || 'Ошибка при заполнении КБЖУ');
        haptic('error');
    } finally {
        setKbjuLoadingState(card, false);
        isFillingKbju = false;
        draftSourceLabel.textContent = previousDraftSourceText;
        updateDraftLimitControls();
    }
}
