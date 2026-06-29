// Meal draft DOM references and state

const bottomSheet = document.getElementById('bottom-sheet');
const appShell = document.getElementById('app');

if (bottomSheet && appShell && bottomSheet.parentElement !== appShell) {
    appShell.append(bottomSheet);
}

const methodScreen = document.getElementById('sheet-screen-method');
const photoScreen = document.getElementById('sheet-screen-photo');
const editorScreen = document.getElementById('sheet-screen-editor');
const cameraInput = document.getElementById('camera-input');
const galleryInput = document.getElementById('gallery-input');
const draftPhotoImg = document.getElementById('draft-photo-img');
const draftPhotoPreview = document.getElementById('draft-photo-preview');
const photoActions = document.querySelector('.photo-actions');
const btnCamera = document.getElementById('btn-camera');
const btnGallery = document.getElementById('btn-gallery');
const btnManualEntry = document.getElementById('btn-manual-entry');
const btnAnalyzePhoto = document.getElementById('btn-analyze-photo');
const btnAnalyzePhotoText = btnAnalyzePhoto.querySelector('.photo-analyze-text');
const btnChangePhoto = document.getElementById('btn-change-photo');
const btnCancelDraft = document.getElementById('btn-cancel-draft');
const btnAddProduct = document.getElementById('btn-add-product');
const btnSaveMeal = document.getElementById('btn-save-meal');
const btnAddManualPhoto = document.getElementById('btn-add-manual-photo');
const attachmentPhotoInput = document.getElementById('manual-photo-input');
const manualPhotoPreview = document.getElementById('manual-photo-preview');
const manualPhotoImg = document.getElementById('manual-photo-img');
const draftImageStatus = document.getElementById('draft-image-status');
const productsList = document.getElementById('products-list');
const mainProductContainer = document.getElementById('draft-main-product-container');
const mealNameInput = document.getElementById('meal-name');
const draftSourceLabel = document.getElementById('draft-source-label');
const draftPhotoHero = document.getElementById('draft-photo-hero');
const draftPhotoEmpty = document.getElementById('draft-photo-empty');
const btnDraftPhotoSelect = document.getElementById('btn-draft-photo-select');
const btnRemoveMainPhoto = document.getElementById('btn-remove-main-photo');
const draftMealType = document.getElementById('draft-meal-type');
const draftProductsCount = document.getElementById('draft-products-count');

const MAX_PRODUCTS_PER_MEAL = 6;
const MAX_DRAFT_SCANS_PER_MEAL = 3;
const MAX_UPLOAD_SIZE_BYTES = 10 * 1024 * 1024;
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const PHOTO_INTENTS = {
    AI_SCAN: 'ai_scan',
    ATTACH_ONLY: 'attach_only',
    APPEND_SCAN: 'append_scan'
};

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
        const result = await apiRequestJson('/api/processing-options');

        if (Array.isArray(result.data) && result.data.length > 0) {
            PROCESSING_OPTIONS = result.data;
        }
    } catch (error) {
        console.error('Ошибка загрузки вариантов термообработки:', error);
    }
}

let nextDraftProductId = 1;
const additionalProductPhotos = new Map();
let mealDraft = createEmptyDraft();
let selectedPhotoFile = null;
let selectedPhotoUrl = null;
let manualPhotoUrl = null;
let currentMainButtonHandler = null;
let photoIntent = PHOTO_INTENTS.AI_SCAN;
let nextScanBatchNumber = 1;
let mealNameEditedByUser = false;
let isAnalyzingPhoto = false;
let isAnalyzingAdditionalProduct = false;
let isSavingMealDraft = false;
let isFillingKbju = false;

function isDraftAiBusy() {
    return isAnalyzingPhoto || isAnalyzingAdditionalProduct || isFillingKbju;
}

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
        clientId: `draft-product-${nextDraftProductId++}`,
        name: '',
        weight: '',
        portions: 1,
        calories: '',
        proteins: '',
        fats: '',
        carbs: '',
        processing: '',
        scanId: ''
    };
}

function getDraftProductCards() {
    return [
        ...mainProductContainer.querySelectorAll('.product-card'),
        ...productsList.querySelectorAll('.product-card')
    ];
}

function clearAdditionalProductPhotos() {
    additionalProductPhotos.forEach(photo => {
        if (photo.url) URL.revokeObjectURL(photo.url);
    });
    additionalProductPhotos.clear();
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
