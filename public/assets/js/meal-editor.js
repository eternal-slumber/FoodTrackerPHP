// ========== Unified meal entry ==========

const bottomSheet = document.getElementById('bottom-sheet');
const methodScreen = document.getElementById('sheet-screen-method');
const photoScreen = document.getElementById('sheet-screen-photo');
const editorScreen = document.getElementById('sheet-screen-editor');
const cameraInput = document.getElementById('camera-input');
const galleryInput = document.getElementById('gallery-input');
const draftPhotoImg = document.getElementById('draft-photo-img');
const btnCamera = document.getElementById('btn-camera');
const btnGallery = document.getElementById('btn-gallery');
const btnManualEntry = document.getElementById('btn-manual-entry');
const btnChangePhoto = document.getElementById('btn-change-photo');
const btnCancelDraft = document.getElementById('btn-cancel-draft');
const btnScanMore = document.getElementById('btn-scan-more');
const btnAddProduct = document.getElementById('btn-add-product');
const btnSaveMeal = document.getElementById('btn-save-meal');
const btnAddManualPhoto = document.getElementById('btn-add-manual-photo');
const btnRemoveManualPhoto = document.getElementById('btn-remove-manual-photo');
const manualPhotoInput = document.getElementById('manual-photo-input');
const manualPhotoPreview = document.getElementById('manual-photo-preview');
const manualPhotoImg = document.getElementById('manual-photo-img');
const draftImageStatus = document.getElementById('draft-image-status');
const productsList = document.getElementById('products-list');
const mealNameInput = document.getElementById('meal-name');
const draftTotalCalories = document.getElementById('draft-total-calories');
const draftSourceLabel = document.getElementById('draft-source-label');

const MAX_PRODUCTS_PER_MEAL = 6;
const MAX_DRAFT_SCANS_PER_MEAL = 3;

let PROCESSING_OPTIONS = [
    { value: '', label: 'Не указано - КБЖУ готового продукта', coefficient: 1 },
    { value: 'fry', label: 'Жарка', coefficient: 1.4 },
    { value: 'bake', label: 'Запекание', coefficient: 1.3 },
    { value: 'boil', label: 'Варка', coefficient: 1.2 },
    { value: 'stew', label: 'Тушение', coefficient: 1.1 },
    { value: 'grill', label: 'Гриль', coefficient: 1.25 },
    { value: 'steam', label: 'На пару', coefficient: 1.05 },
    { value: 'deep_fry', label: 'Фритюр', coefficient: 1.6 },
    { value: 'no_oil_fry', label: 'Жарка без масла', coefficient: 1.15 }
];

async function loadProcessingOptions() {
    try {
        const response = await apiFetch('/api/processing-options');
        const result = await response.json();

        if (result.status === 'success' && Array.isArray(result.data) && result.data.length > 0) {
            PROCESSING_OPTIONS = result.data;
        }
    } catch (error) {
        console.error('Ошибка загрузки вариантов термообработки:', error);
    }
}

let mealDraft = createEmptyDraft();
let selectedPhotoFile = null;
let selectedPhotoUrl = null;
let manualPhotoUrl = null;
let currentMainButtonHandler = null;
let photoSelectionMode = 'initial';
let nextScanBatchNumber = 1;
let mealNameEditedByUser = false;

function createEmptyDraft() {
    return {
        source: 'manual',
        mealName: '',
        products: [createEmptyProduct()],
        draftImagePath: null
    };
}

function createEmptyProduct() {
    return {
        name: '',
        weight: 100,
        calories: '',
        proteins: '',
        fats: '',
        carbs: '',
        processing: '',
        scanId: ''
    };
}

function haptic(type = 'light') {
    try {
        if (type === 'error') {
            tg.HapticFeedback?.notificationOccurred('error');
        } else if (type === 'success') {
            tg.HapticFeedback?.notificationOccurred('success');
        } else {
            tg.HapticFeedback?.impactOccurred(type);
        }
    } catch (error) {
        console.debug('Haptic feedback unavailable', error);
    }
}

function setMainAction(text, handler) {
    clearMainAction();
    currentMainButtonHandler = handler;
    tg.MainButton.setText(text).show();
    tg.MainButton.onClick(currentMainButtonHandler);
}

function clearMainAction() {
    if (currentMainButtonHandler) {
        tg.MainButton.offClick(currentMainButtonHandler);
        currentMainButtonHandler = null;
    }
    tg.MainButton.hide();
}

function setBackAction(handler) {
    tg.BackButton.show();
    tg.BackButton.offClick(handleSheetBack);
    tg.BackButton.onClick(handler);
}

function clearBackAction() {
    tg.BackButton.offClick(handleSheetBack);
    tg.BackButton.hide();
}

function openMealSheet() {
    mealDraft = createEmptyDraft();
    selectedPhotoFile = null;
    photoSelectionMode = 'initial';
    nextScanBatchNumber = 1;
    mealNameEditedByUser = false;
    if (selectedPhotoUrl) URL.revokeObjectURL(selectedPhotoUrl);
    if (manualPhotoUrl) URL.revokeObjectURL(manualPhotoUrl);
    selectedPhotoUrl = null;
    manualPhotoUrl = null;
    bottomSheet.classList.remove('hidden');
    document.body.classList.add('sheet-open');
    showMealStep('method');
    haptic('light');
}

function closeMealSheet() {
    bottomSheet.classList.add('hidden');
    document.body.classList.remove('sheet-open');
    clearMainAction();
    clearBackAction();
    cameraInput.value = '';
    galleryInput.value = '';
    manualPhotoInput.value = '';
    photoSelectionMode = 'initial';
}

function showMealStep(step) {
    methodScreen.classList.toggle('hidden', step !== 'method');
    photoScreen.classList.toggle('hidden', step !== 'photo');
    editorScreen.classList.toggle('hidden', step !== 'editor');

    if (step === 'method') {
        clearMainAction();
        clearBackAction();
    }

    if (step === 'photo') {
        setBackAction(handleSheetBack);
        setMainAction('Проанализировать', analyzeSelectedPhoto);
    }

    if (step === 'editor') {
        setBackAction(handleSheetBack);
        clearMainAction();
        renderDraftEditor();
    }
}

function handleSheetBack() {
    if (!photoScreen.classList.contains('hidden')) {
        if (photoSelectionMode === 'append') {
            photoSelectionMode = 'initial';
            showMealStep('editor');
            return;
        }

        showMealStep('method');
        return;
    }

    if (!editorScreen.classList.contains('hidden')) {
        syncDraftFromEditor();
        showMealStep(mealDraft.source === 'photo' ? 'photo' : 'method');
        return;
    }

    closeMealSheet();
}

function handlePhotoSelected(file) {
    if (!file) return;

    selectedPhotoFile = file;
    if (selectedPhotoUrl) URL.revokeObjectURL(selectedPhotoUrl);
    selectedPhotoUrl = URL.createObjectURL(file);
    draftPhotoImg.src = selectedPhotoUrl;

    if (photoSelectionMode !== 'append') {
        mealDraft = createEmptyDraft();
        mealDraft.source = 'photo';
        mealNameEditedByUser = false;
    }

    showMealStep('photo');
}

async function analyzeSelectedPhoto() {
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

    if (photoSelectionMode === 'append' && getCurrentProductCount() >= MAX_PRODUCTS_PER_MEAL) {
        showProductLimitAlert();
        return;
    }

    tg.MainButton.showProgress(false);

    const formData = new FormData();
    formData.append('photo', selectedPhotoFile);

    try {
        const response = await apiFetch('/api/analyze-draft', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (!response.ok || result.status !== 'success') {
            tg.showAlert(result.message || result.error || 'Не удалось распознать фото');
            haptic('error');
            return;
        }

        const scanId = createScanBatchId();
        const analyzedDraft = normalizeDraft(result.data, 'photo');
        analyzedDraft.products = analyzedDraft.products.map(product => ({
            ...product,
            scanId
        }));

        if (photoSelectionMode === 'append') {
            appendAnalyzedDraft(analyzedDraft);
        } else {
            mealDraft = limitDraftProducts(analyzedDraft);
        }

        photoSelectionMode = 'initial';
        haptic('success');
        showMealStep('editor');
    } catch (error) {
        tg.showAlert('Ошибка при анализе фото');
        haptic('error');
    } finally {
        tg.MainButton.hideProgress();
    }
}

function normalizeDraft(data, source) {
    const products = Array.isArray(data?.products) && data.products.length > 0
        ? data.products.map(product => ({
            name: product.name || '',
            weight: product.weight || 100,
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
    productsList.innerHTML = mealDraft.products
        .map((product, index) => createProductCard(product, index))
        .join('');
    updateDraftSourceLabel();
    renderDraftImageField();
    bindProductCardEvents();
    recalculateDraftTotal();
    updateDraftLimitControls();
}

function renderDraftImageField() {
    const hasImage = Boolean(mealDraft.draftImagePath || manualPhotoUrl);

    manualPhotoPreview.classList.toggle('hidden', !manualPhotoUrl);
    btnRemoveManualPhoto.classList.toggle('hidden', !hasImage);
    btnAddManualPhoto.parentElement.classList.toggle('single-action', !hasImage);
    btnAddManualPhoto.textContent = hasImage ? 'Заменить фото' : 'Добавить фото';
    draftImageStatus.textContent = hasImage
        ? 'Фото будет показано в истории после сохранения'
        : 'Можно добавить миниатюру для истории';

    if (manualPhotoUrl) {
        manualPhotoImg.src = manualPhotoUrl;
    }
}

async function uploadManualDraftPhoto(file) {
    if (!file) return;

    if (manualPhotoUrl) URL.revokeObjectURL(manualPhotoUrl);
    manualPhotoUrl = URL.createObjectURL(file);
    renderDraftImageField();

    const previousPath = mealDraft.draftImagePath;
    const formData = new FormData();
    formData.append('photo', file);

    btnAddManualPhoto.disabled = true;
    btnAddManualPhoto.textContent = 'Загружаю...';

    try {
        const response = await apiFetch('/api/upload-draft-image', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (!response.ok || result.status !== 'success') {
            mealDraft.draftImagePath = previousPath;
            tg.showAlert(result.message || result.error || 'Не удалось загрузить фото');
            haptic('error');
            return;
        }

        mealDraft.draftImagePath = result.data?.draft_image_path || null;
        haptic('success');
    } catch (error) {
        mealDraft.draftImagePath = previousPath;
        tg.showAlert('Ошибка загрузки фото');
        haptic('error');
    } finally {
        btnAddManualPhoto.disabled = false;
        renderDraftImageField();
        manualPhotoInput.value = '';
    }
}

function removeManualDraftPhoto() {
    if (manualPhotoUrl) {
        URL.revokeObjectURL(manualPhotoUrl);
    }

    manualPhotoUrl = null;
    mealDraft.draftImagePath = null;
    manualPhotoInput.value = '';
    renderDraftImageField();
    haptic('light');
}

function toggleProductKbju(card) {
    const panel = card.querySelector('.kbju-panel');
    const button = card.querySelector('.kbju-toggle');
    const isHidden = panel.classList.toggle('hidden');

    card.classList.toggle('kbju-open', !isHidden);
    button.setAttribute('aria-expanded', String(!isHidden));
    button.textContent = isHidden ? 'БЖУ' : 'Скрыть БЖУ';
    haptic('light');
}

function createProductCard(product, index) {
    const processingOptions = PROCESSING_OPTIONS.map(option => {
        const selected = option.value === (product.processing || '') ? ' selected' : '';
        return `<option value="${option.value}"${selected}>${option.label}</option>`;
    }).join('');

    return `
        <div class="product-card" data-index="${index}" data-scan-id="${escapeHtml(product.scanId || '')}">
            <div class="product-card-header">
                <span class="input-label">Продукт ${index + 1}</span>
                <button class="product-delete" type="button" title="Удалить">✕</button>
            </div>
            <div class="product-row inline">
                <div class="name-wrap">
                    <label class="field-label">Название</label>
                    <input type="text" class="product-name" value="${escapeHtml(product.name)}" placeholder="Например, курица">
                </div>
                <div class="weight-wrap">
                    <label class="field-label">Вес</label>
                    <input type="number" class="product-weight" value="${Number(product.weight) || 100}" min="1" max="5000">
                </div>
            </div>
            <label class="field-label">Термообработка</label>
            <select class="processing-select">
                ${processingOptions}
            </select>
            <div class="nutrition-row">
                <div>
                    <label class="field-label">Ккал / 100г</label>
                    <input type="number" class="product-calories" value="${product.calories}" min="0" placeholder="0">
                </div>
                <button class="kbju-toggle" type="button" aria-expanded="false">БЖУ</button>
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
        </div>
    `;
}

function bindProductCardEvents() {
    productsList.querySelectorAll('.product-card').forEach(card => {
        card.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', recalculateDraftTotal);
        });

        card.querySelector('.processing-select').addEventListener('change', () => {
            recalculateDraftTotal();
            haptic('light');
        });

        card.querySelector('.product-delete').onclick = () => {
            if (productsList.children.length === 1) {
                if (card.dataset.scanId) {
                    mealDraft.products = [createEmptyProduct()];
                    if (mealNameEditedByUser) {
                        mealDraft.mealName = mealNameInput.value.trim();
                    }
                    renderDraftEditor();
                    haptic('light');
                    return;
                }

                tg.showAlert('Оставьте хотя бы один продукт');
                haptic('error');
                return;
            }
            card.remove();
            syncDraftFromEditor();
            recalculateDraftTotal();
            updateDraftLimitControls();
            updateDraftSourceLabel();
            haptic('light');
        };
    });
}

productsList.addEventListener('click', event => {
    const toggle = event.target.closest('.kbju-toggle');
    if (!toggle) return;

    const card = toggle.closest('.product-card');
    if (!card) return;

    event.preventDefault();
    toggleProductKbju(card);
});

function collectDraftProducts() {
    return Array.from(productsList.querySelectorAll('.product-card')).slice(0, MAX_PRODUCTS_PER_MEAL).map(card => {
        const calories = card.querySelector('.product-calories').value;
        const proteins = card.querySelector('.product-proteins').value;
        const fats = card.querySelector('.product-fats').value;
        const carbs = card.querySelector('.product-carbs').value;

        return {
            name: card.querySelector('.product-name').value.trim(),
            weight: parseInt(card.querySelector('.product-weight').value, 10) || 100,
            processing: card.querySelector('.processing-select').value,
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
        ? Array.from(productsList.querySelectorAll('.product-card')).map(card => ({ scanId: card.dataset.scanId || '' }))
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
        return productsList.querySelectorAll('.product-card').length;
    }

    return Array.isArray(mealDraft.products) ? mealDraft.products.length : 0;
}

function showProductLimitAlert() {
    tg.showAlert(`В одном приеме можно добавить до ${MAX_PRODUCTS_PER_MEAL} продуктов`);
    haptic('error');
}

function syncDraftFromEditor() {
    if (editorScreen.classList.contains('hidden')) return;

    const products = collectDraftProducts();
    mealDraft.mealName = mealNameEditedByUser
        ? mealNameInput.value.trim()
        : buildGeneratedMealName(products);
    mealDraft.products = products.map(product => ({
        name: product.name,
        weight: product.weight,
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
    const scanLimitReached = scanCount >= MAX_DRAFT_SCANS_PER_MEAL;

    btnAddProduct.disabled = productsLimitReached;
    btnAddProduct.textContent = productsLimitReached ? 'Лимит продуктов' : '＋ Добавить';

    btnScanMore.disabled = productsLimitReached || scanLimitReached;
    btnScanMore.textContent = scanLimitReached
        ? `Лимит сканов ${scanCount}/${MAX_DRAFT_SCANS_PER_MEAL}`
        : `Сканировать еще ${scanCount}/${MAX_DRAFT_SCANS_PER_MEAL}`;
}

function updateDraftSourceLabel() {
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
    const mealType = getMealTypeByTime();
    const name = productNames.length > 0
        ? `${mealType}: ${productNames.join(', ')}`
        : mealType;

    return name.length > 120 ? `${name.slice(0, 117)}...` : name;
}

function updateAutoMealNameFromProducts() {
    if (mealNameEditedByUser || editorScreen.classList.contains('hidden')) return;

    mealNameInput.value = buildGeneratedMealName(collectDraftProducts());
}

function startPhotoSelection(mode, input) {
    if (mode === 'append') {
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

    photoSelectionMode = mode;
    input.value = '';
    input.click();
}

function recalculateDraftTotal() {
    const total = collectDraftProducts().reduce((sum, product) => {
        const caloriesPer100g = parseFloat(product.kbju.calories) || 0;
        const weight = parseFloat(product.weight) || 100;
        const coefficient = PROCESSING_OPTIONS.find(option => option.value === product.processing)?.coefficient ?? 1;
        return sum + (caloriesPer100g * coefficient * weight / 100);
    }, 0);
    draftTotalCalories.textContent = Math.round(total);
    updateAutoMealNameFromProducts();
}

async function saveMealDraft() {
    const products = collectDraftProducts();
    const mealName = mealNameInput.value.trim() || buildGeneratedMealName(products);

    if (products.some(product => !product.name)) {
        tg.showAlert('Заполните названия всех продуктов');
        haptic('error');
        return;
    }

    if (products.length === 0) {
        tg.showAlert('Добавьте хотя бы один продукт');
        haptic('error');
        return;
    }

    tg.MainButton.showProgress(false);

    try {
        const response = await apiFetch('/api/save-meal', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                meal_name: mealName || 'Прием пищи',
                products,
                draft_image_path: mealDraft.draftImagePath
            })
        });
        const result = await response.json();

        if (result.status === 'success') {
            closeMealSheet();
            await loadMealHistory();
            await loadProgress();
            haptic('success');
            tg.showAlert(`Добавлено: ${mealName}, ${result.meal?.calories || 0} ккал`);
            return;
        }

        tg.showAlert(result.message || result.error || 'Ошибка сохранения');
        haptic('error');
    } catch (error) {
        tg.showAlert('Ошибка соединения');
        haptic('error');
    } finally {
        tg.MainButton.hideProgress();
    }
}

document.getElementById('btn-add-food').onclick = openMealSheet;
document.querySelector('.sheet-overlay').onclick = closeMealSheet;
btnManualEntry.onclick = startManualDraft;
btnCancelDraft.onclick = closeMealSheet;
btnSaveMeal.onclick = saveMealDraft;
btnAddManualPhoto.onclick = () => manualPhotoInput.click();
btnRemoveManualPhoto.onclick = removeManualDraftPhoto;
mealNameInput.addEventListener('input', () => {
    mealNameEditedByUser = true;
    mealDraft.mealName = mealNameInput.value.trim();
});
btnScanMore.onclick = () => startPhotoSelection('append', cameraInput);
btnAddProduct.onclick = () => {
    if (getCurrentProductCount() >= MAX_PRODUCTS_PER_MEAL) {
        showProductLimitAlert();
        updateDraftLimitControls();
        return;
    }

    const product = createEmptyProduct();
    productsList.insertAdjacentHTML('beforeend', createProductCard(product, productsList.children.length));
    bindProductCardEvents();
    recalculateDraftTotal();
    updateDraftLimitControls();
    haptic('light');
};

btnCamera.onclick = () => startPhotoSelection('initial', cameraInput);
btnGallery.onclick = () => startPhotoSelection('initial', galleryInput);
btnChangePhoto.onclick = () => startPhotoSelection(photoSelectionMode, galleryInput);
cameraInput.onchange = event => handlePhotoSelected(event.target.files[0]);
galleryInput.onchange = event => handlePhotoSelected(event.target.files[0]);
manualPhotoInput.onchange = event => uploadManualDraftPhoto(event.target.files[0]);
