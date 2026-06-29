// Meal totals, persistence and image uploads

function recalculateDraftTotal() {
    const totals = collectDraftProducts().reduce((result, product) => {
        const caloriesPer100g = parseFloat(product.kbju.calories) || 0;
        const proteinsPer100g = parseFloat(product.kbju.proteins) || 0;
        const fatsPer100g = parseFloat(product.kbju.fats) || 0;
        const carbsPer100g = parseFloat(product.kbju.carbs) || 0;
        const weight = parseFloat(product.weight) || 0;
        const coefficient = PROCESSING_OPTIONS.find(option => option.value === product.processing)?.coefficient ?? 1;
        const ratio = coefficient * weight / 100;

        result.calories += caloriesPer100g * ratio;
        result.proteins += proteinsPer100g * ratio;
        result.fats += fatsPer100g * ratio;
        result.carbs += carbsPer100g * ratio;
        return result;
    }, { calories: 0, proteins: 0, fats: 0, carbs: 0 });

    const mainProduct = collectDraftProducts()[0];
    const mainWeight = Number(mainProduct?.weight || 0);
    const mainRatio = mainWeight / 100;
    const mainCalories = Number(mainProduct?.kbju.calories || 0) * mainRatio;
    const mainProteins = Number(mainProduct?.kbju.proteins || 0) * mainRatio;
    const mainFats = Number(mainProduct?.kbju.fats || 0) * mainRatio;
    const mainCarbs = Number(mainProduct?.kbju.carbs || 0) * mainRatio;

    const summaryElements = {
        total: document.getElementById('draft-total-calories'),
        calories: document.getElementById('draft-summary-calories'),
        proteins: document.getElementById('draft-summary-protein'),
        fats: document.getElementById('draft-summary-fat'),
        carbs: document.getElementById('draft-summary-carbs')
    };

    if (summaryElements.total) summaryElements.total.textContent = Math.round(mainCalories);
    if (summaryElements.calories) summaryElements.calories.textContent = Math.round(mainCalories);
    if (summaryElements.proteins) summaryElements.proteins.textContent = formatMacro(mainProteins);
    if (summaryElements.fats) summaryElements.fats.textContent = formatMacro(mainFats);
    if (summaryElements.carbs) summaryElements.carbs.textContent = formatMacro(mainCarbs);
    updateAutoMealNameFromProducts();
}

async function saveMealDraft() {
    if (isSavingMealDraft) {
        return;
    }

    if (isDraftAiBusy()) {
        tg.showAlert('Дождитесь завершения текущего AI-запроса');
        haptic('error');
        return;
    }

    const products = collectDraftProducts();
    const mealName = mealNameInput.value.trim() || buildGeneratedMealName(products);

    if (products.some(product => !product.name || product.weight <= 0)) {
        tg.showAlert('Заполните название и вес каждого продукта');
        haptic('error');
        return;
    }

    if (products.length === 0) {
        tg.showAlert('Добавьте хотя бы один продукт');
        haptic('error');
        return;
    }

    const productsWithMissingKbju = productsMissingKbju(products);
    if (productsWithMissingKbju.length > 0) {
        const confirmed = await confirmAsync(
            `У ${productsWithMissingKbju.length} продукт(ов) не полностью заполнены КБЖУ. Недостающие значения сохранятся как 0. Продолжить?`
        );

        if (!confirmed) {
            haptic('light');
            return;
        }
    }

    isSavingMealDraft = true;
    btnSaveMeal.disabled = true;
    const previousSaveButtonText = btnSaveMeal.textContent;
    const previousDraftSourceText = draftSourceLabel.textContent;
    btnSaveMeal.textContent = 'Сохраняю...';
    draftSourceLabel.textContent = 'Сохраняю прием пищи';
    tg.MainButton.showProgress(false);

    try {
        await ensureMainDraftImageUploaded();
        await ensureAdditionalDraftImagesUploaded(products);

        const result = await apiRequestJson('/api/save-meal', {
            method: 'POST',
            json: {
                meal_name: mealName || 'Прием пищи',
                products: products.map(({ baseWeight, portions, clientId, ...product }) => product),
                draft_image_path: mealDraft.draftImagePath,
                split_products: true
            }
        });

        refreshDailyNutritionInsight({ invalidate: true });
        closeMealSheet();
        await refreshMealHistory();
        await loadProgress();
        await refreshHistoryCalendar();
        await refreshSummary();
        haptic('success');
        tg.showAlert(`Добавлено: ${mealName}, ${result.meal?.calories || 0} ккал`);
    } catch (error) {
        tg.showAlert(error?.message || 'Ошибка соединения');
        haptic('error');
    } finally {
        isSavingMealDraft = false;
        btnSaveMeal.disabled = false;
        btnSaveMeal.textContent = previousSaveButtonText;
        draftSourceLabel.textContent = previousDraftSourceText;
        tg.MainButton.hideProgress();
    }
}

async function ensureAdditionalDraftImagesUploaded(products) {
    for (const product of products.slice(1)) {
        const photo = additionalProductPhotos.get(product.clientId);
        if (!photo?.file) {
            continue;
        }

        if (!photo.draftImagePath) {
            const formData = new FormData();
            formData.append('photo', photo.file);

            const result = await apiRequestJson('/api/upload-draft-image', {
                method: 'POST',
                body: formData,
                timeoutMs: API_TIMEOUT.UPLOAD
            });
            const imagePath = result?.data?.draft_image_path;

            if (!imagePath) {
                throw new Error(`Не удалось сохранить фото продукта «${product.name}»`);
            }

            photo.draftImagePath = imagePath;
        }

        product.draft_image_path = photo.draftImagePath;
    }
}

async function ensureMainDraftImageUploaded() {
    if (mealDraft.draftImagePath || !selectedPhotoFile) {
        return;
    }

    const formData = new FormData();
    formData.append('photo', selectedPhotoFile);

    const result = await apiRequestJson('/api/upload-draft-image', {
        method: 'POST',
        body: formData,
        timeoutMs: API_TIMEOUT.UPLOAD
    });
    const imagePath = result?.data?.draft_image_path;

    if (!imagePath) {
        throw new Error('Не удалось сохранить главное фото');
    }

    mealDraft.draftImagePath = imagePath;
}
