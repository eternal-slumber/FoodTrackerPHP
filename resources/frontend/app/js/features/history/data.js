// Meal history loading and cache

let mealDetailBackHandler = null;
let mealDetailParentBackHandler = null;
let mealDetailKeepBodyLockedOnClose = false;
let protectedImagesObserver = null;
let protectedImagesRenderId = 0;
let mealHistoryCache = null;
let mealHistoryDirty = true;
let mealHistoryLoadPromise = null;
let historySwipeGesture = null;
const historyMealDetailsCache = new Map();
const HISTORY_MAX_SWIPE_X = -120;
const HISTORY_OPEN_THRESHOLD = -18;
const HISTORY_INTENT_THRESHOLD = 6;

async function loadMealHistory(options = {}) {
    const force = Boolean(options.force);
    const historyList = document.getElementById('history-list');

    if (!force && !mealHistoryDirty && Array.isArray(mealHistoryCache)) {
        if (historyList?.dataset.historyRendered !== '1') {
            renderMealHistory(mealHistoryCache);
        }

        return mealHistoryCache;
    }

    if (mealHistoryLoadPromise) {
        return mealHistoryLoadPromise;
    }

    mealHistoryLoadPromise = fetchMealHistory();

    try {
        return await mealHistoryLoadPromise;
    } finally {
        mealHistoryLoadPromise = null;
    }
}

async function refreshMealHistory() {
    mealHistoryDirty = true;
    return loadMealHistory({ force: true });
}

function updateMealHistoryAfterDelete(mealId) {
    historyMealDetailsCache.delete(mealId);

    if (Array.isArray(mealHistoryCache)) {
        mealHistoryCache = mealHistoryCache.filter(meal => Number(meal.id) !== Number(mealId));
        mealHistoryDirty = false;
        renderMealHistory(mealHistoryCache);
        return;
    }

    mealHistoryDirty = true;
}

async function fetchMealHistory() {
    const historyList = document.getElementById('history-list');

    try {
        const result = await apiRequestJson('/api/history');
        mealHistoryCache = Array.isArray(result.data) ? result.data : [];
        mealHistoryDirty = false;
        renderMealHistory(mealHistoryCache);
        return mealHistoryCache;
    } catch (error) {
        console.error('Ошибка загрузки истории:', error);
        if (!Array.isArray(mealHistoryCache)) {
            historyList.innerHTML = '<p class="home-meals-error">Не удалось загрузить сегодняшние приёмы.</p>';
        }
    }

    return mealHistoryCache || [];
}
