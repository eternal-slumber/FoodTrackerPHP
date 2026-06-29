// Meal draft sheet navigation

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
    photoIntent = PHOTO_INTENTS.AI_SCAN;
    nextScanBatchNumber = 1;
    mealNameEditedByUser = false;
    isAnalyzingPhoto = false;
    isAnalyzingAdditionalProduct = false;
    isSavingMealDraft = false;
    isFillingKbju = false;
    clearAdditionalProductPhotos();
    if (selectedPhotoUrl) URL.revokeObjectURL(selectedPhotoUrl);
    if (manualPhotoUrl) URL.revokeObjectURL(manualPhotoUrl);
    selectedPhotoUrl = null;
    manualPhotoUrl = null;
    bottomSheet.classList.remove('hidden');
    document.body.classList.add('sheet-open');
    draftMealType.value = getMealTypeByTime();
    showMealStep('editor');
    haptic('light');
}

function closeMealSheet() {
    bottomSheet.classList.add('hidden');
    document.body.classList.remove('sheet-open');
    clearMainAction();
    clearBackAction();
    cameraInput.value = '';
    galleryInput.value = '';
    attachmentPhotoInput.value = '';
    photoIntent = PHOTO_INTENTS.AI_SCAN;
    isAnalyzingPhoto = false;
    isAnalyzingAdditionalProduct = false;
    isSavingMealDraft = false;
    isFillingKbju = false;
    if (selectedPhotoUrl) URL.revokeObjectURL(selectedPhotoUrl);
    if (manualPhotoUrl) URL.revokeObjectURL(manualPhotoUrl);
    selectedPhotoFile = null;
    selectedPhotoUrl = null;
    manualPhotoUrl = null;
    clearAdditionalProductPhotos();
    renderMainDraftPhoto();
}

function showMealStep(step) {
    methodScreen.classList.toggle('hidden', step !== 'method');
    photoScreen.classList.toggle('hidden', step !== 'photo');
    editorScreen.classList.toggle('hidden', step !== 'editor');

    if (step === 'editor') {
        setBackAction(handleSheetBack);
        clearMainAction();
        renderDraftEditor();
        renderMainDraftPhoto();
    }
}

function handleSheetBack() {
    if (!editorScreen.classList.contains('hidden')) {
        closeMealSheet();
        return;
    }

    closeMealSheet();
}

