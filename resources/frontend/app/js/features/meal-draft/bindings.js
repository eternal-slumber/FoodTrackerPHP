// Meal draft event bindings

document.getElementById('btn-add-food').onclick = openMealSheet;
document.querySelector('.sheet-overlay').onclick = closeMealSheet;
btnManualEntry.onclick = startManualDraft;
btnCancelDraft.onclick = closeMealSheet;
btnAnalyzePhoto.onclick = analyzeSelectedPhoto;
btnSaveMeal.onclick = saveMealDraft;
btnAddManualPhoto.onclick = startAttachmentPhotoSelection;
btnDraftPhotoSelect.onclick = () => startAiPhotoSelection(PHOTO_INTENTS.ATTACH_ONLY, galleryInput);
btnRemoveMainPhoto.onclick = removeMainDraftPhoto;
draftMealType.addEventListener('change', () => {
    mealNameEditedByUser = false;
    updateAutoMealNameFromProducts();
    updateDraftLimitControls();
    haptic('light');
});
mealNameInput.addEventListener('input', () => {
    mealNameEditedByUser = true;
    mealDraft.mealName = mealNameInput.value.trim();
});
btnAddProduct.onclick = () => {
    if (getCurrentProductCount() >= MAX_PRODUCTS_PER_MEAL) {
        showProductLimitAlert();
        updateDraftLimitControls();
        return;
    }

    const product = createEmptyProduct();
    productsList.insertAdjacentHTML('beforeend', createProductCard(product, getCurrentProductCount()));
    renumberProductCards();
    bindProductCardEvents();
    recalculateDraftTotal();
    updateDraftLimitControls();
    haptic('light');
};

btnCamera.onclick = () => startAiPhotoSelection(PHOTO_INTENTS.AI_SCAN, cameraInput);
btnGallery.onclick = () => startAiPhotoSelection(PHOTO_INTENTS.AI_SCAN, galleryInput);
btnChangePhoto.onclick = () => {
    const nextIntent = [PHOTO_INTENTS.APPEND_SCAN, PHOTO_INTENTS.ATTACH_ONLY].includes(photoIntent)
        ? photoIntent
        : PHOTO_INTENTS.AI_SCAN;

    startAiPhotoSelection(nextIntent, galleryInput);
};
cameraInput.onchange = event => handleAiPhotoSelected(event.target.files[0]);
galleryInput.onchange = event => handleAiPhotoSelected(event.target.files[0]);
attachmentPhotoInput.onchange = event => uploadAttachmentPhoto(event.target.files[0]);
