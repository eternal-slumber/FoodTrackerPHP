// History gestures, images and deletion

document.addEventListener('pointerdown', event => {
    const row = event.target.closest('.swipe-row');
    if (row) {
        closeHistorySwipeRows(row);
        return;
    }

    closeHistorySwipeRows();
});

function setHistorySwipeRowOpen(row, isOpen) {
    const wrapper = row.querySelector('.meal-card-wrapper');
    if (!wrapper) {
        return;
    }

    row.classList.remove('is-closing');

    if (isOpen) {
        row.classList.add('is-open', 'open');
        wrapper.style.transform = `translate3d(${HISTORY_MAX_SWIPE_X}px, 0, 0)`;
        return;
    }

    const wasOpen = row.classList.contains('is-open') || row.classList.contains('open');
    row.classList.remove('is-open', 'open');
    row.classList.toggle('is-closing', wasOpen);
    wrapper.style.transform = '';

    if (wasOpen) {
        window.setTimeout(() => row.classList.remove('is-closing'), 340);
    }
}

function closeHistorySwipeRows(exceptRow = null) {
    document.querySelectorAll('.swipe-row.is-open, .history-swipe-row.open').forEach(row => {
        if (row === exceptRow) return;

        row.classList.remove('swiping', 'is-swiping');
        row.querySelector('.meal-card-wrapper')?.style.removeProperty('transition');
        setHistorySwipeRowOpen(row, false);
    });
}

function formatMealMeta(meal) {
    const parts = [];

    if (meal.weight) {
        parts.push(`${meal.weight} г`);
    }

    parts.push(`Б ${formatMacro(meal.proteins)}`);
    parts.push(`Ж ${formatMacro(meal.fats)}`);
    parts.push(`У ${formatMacro(meal.carbs)}`);

    return escapeHtml(parts.join(' · '));
}

function parseMealDate(value) {
    if (!value) {
        return null;
    }

    const rawValue = String(value).trim();
    let date = new Date(rawValue);

    if (Number.isNaN(date.getTime()) && rawValue.includes(' ')) {
        date = new Date(rawValue.replace(' ', 'T'));
    }

    return Number.isNaN(date.getTime()) ? null : date;
}

function formatDateKey(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function formatHistoryDateLabel(date) {
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);

    const shortDate = date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long'
    });

    if (formatDateKey(date) === formatDateKey(today)) {
        return 'Сегодня';
    }

    if (formatDateKey(date) === formatDateKey(yesterday)) {
        return `Вчера, ${shortDate}`;
    }

    return date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
}

function disconnectProtectedImagesObserver() {
    if (!protectedImagesObserver) {
        return;
    }

    protectedImagesObserver.disconnect();
    protectedImagesObserver = null;
}

function revokeProtectedImageUrls(root = document) {
    root.querySelectorAll('img[data-object-url]').forEach(image => {
        URL.revokeObjectURL(image.dataset.objectUrl);
        delete image.dataset.objectUrl;
    });
}

function observeProtectedImages() {
    const images = Array.from(document.querySelectorAll('img[data-image-url]'))
        .filter(image => image.dataset.imageUrl && image.dataset.imageLoaded !== '1' && image.dataset.imageLoading !== '1');
    const renderId = String(protectedImagesRenderId);

    if (images.length === 0) {
        return;
    }

    images.forEach(image => {
        image.dataset.imageRenderId = renderId;
    });

    if (!('IntersectionObserver' in window)) {
        loadProtectedImages(images, renderId);
        return;
    }

    disconnectProtectedImagesObserver();
    protectedImagesObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) {
                return;
            }

            observer.unobserve(entry.target);
            loadProtectedImage(entry.target, renderId);
        });
    }, {
        rootMargin: '300px 0px',
        threshold: 0.01
    });

    images.forEach(image => protectedImagesObserver.observe(image));
}

async function loadProtectedImages(images = document.querySelectorAll('img[data-image-url]'), renderId = String(protectedImagesRenderId)) {
    for (const image of images) {
        await loadProtectedImage(image, renderId);
    }
}

async function loadProtectedImage(image, renderId = String(protectedImagesRenderId)) {
    if (image.dataset.imageLoading === '1' || image.dataset.imageLoaded === '1') {
        return;
    }

    if (!image.isConnected || image.dataset.imageRenderId !== renderId) {
        return;
    }

    const url = image.dataset.imageUrl;
    if (!url) {
        return;
    }

    image.dataset.imageLoading = '1';

    try {
        const blob = await apiRequestBlob(url);

        if (!image.isConnected || image.dataset.imageRenderId !== renderId) {
            return;
        }

        const objectUrl = URL.createObjectURL(blob);

        if (image.dataset.objectUrl) {
            URL.revokeObjectURL(image.dataset.objectUrl);
        }

        image.src = objectUrl;
        image.dataset.objectUrl = objectUrl;
        image.dataset.imageLoaded = '1';
    } catch (error) {
        console.error('Ошибка загрузки изображения:', error);
    } finally {
        delete image.dataset.imageLoading;
    }
}

// Удаление записи о приеме пищи
async function deleteMeal(mealId) {
    const confirmed = confirm('Вы уверены, что хотите удалить эту запись?');

    if (!confirmed) {
        return;
    }

    const row = document.querySelector(`.swipe-row .history-delete-action[data-meal-id="${mealId}"]`)?.closest('.swipe-row');
    const deleteButton = row?.querySelector('.history-delete-action');

    row?.classList.add('is-deleting');
    if (deleteButton) {
        deleteButton.disabled = true;
    }

    try {
        await apiRequestJson('/api/delete-meal', {
            method: 'POST',
            json: { meal_id: mealId }
        });

        tg.showAlert('Запись успешно удалена');
        updateMealHistoryAfterDelete(mealId);
        loadProgress();
        refreshHistoryCalendar();
        refreshSummary();
        refreshDailyNutritionInsight();
    } catch (error) {
        row?.classList.remove('is-deleting');
        if (deleteButton) {
            deleteButton.disabled = false;
        }
        tg.showAlert(error?.message || 'Ошибка соединения с сервером');
    }
}

bindHistoryInteractions();
