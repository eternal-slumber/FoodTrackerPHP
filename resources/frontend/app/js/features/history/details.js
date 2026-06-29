// Meal details view

async function openMealDetail(mealId, options = {}) {
    if (!mealId) return;

    try {
        const meal = await fetchMealDetail(mealId);
        renderMealDetail(meal);
        document.getElementById('meal-detail-sheet').classList.remove('hidden');
        document.body.classList.add('sheet-open');

        mealDetailParentBackHandler = options.parentBackHandler || null;
        mealDetailKeepBodyLockedOnClose = Boolean(options.keepBodyLockedOnClose);

        if (mealDetailParentBackHandler) {
            tg.BackButton.offClick(mealDetailParentBackHandler);
        }

        mealDetailBackHandler = closeMealDetail;
        tg.BackButton.show();
        tg.BackButton.onClick(mealDetailBackHandler);
    } catch (error) {
        console.error('Ошибка загрузки детализации приема:', error);
        tg.showAlert(error?.message || 'Ошибка загрузки детализации');
    }
}

function closeMealDetail() {
    document.getElementById('meal-detail-sheet').classList.add('hidden');

    if (mealDetailBackHandler) {
        tg.BackButton.offClick(mealDetailBackHandler);
        mealDetailBackHandler = null;
    }

    if (mealDetailParentBackHandler) {
        tg.BackButton.show();
        tg.BackButton.onClick(mealDetailParentBackHandler);
        mealDetailParentBackHandler = null;
    } else {
        tg.BackButton.hide();
    }

    if (!mealDetailKeepBodyLockedOnClose) {
        document.body.classList.remove('sheet-open');
    }

    mealDetailKeepBodyLockedOnClose = false;
}

function renderMealDetail(meal) {
    const date = parseMealDate(meal.created_at);
    const formattedTime = date
        ? date.toLocaleString('ru-RU', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })
        : 'Прием пищи';

    document.getElementById('meal-detail-time').textContent = formattedTime;
    document.getElementById('meal-detail-title').textContent = meal.description || 'Прием пищи';
    document.getElementById('meal-detail-calories').textContent = Number(meal.calories || 0);
    document.getElementById('meal-detail-weight').textContent = meal.weight ? Number(meal.weight) : '—';
    document.getElementById('meal-detail-proteins').textContent = formatMacro(meal.proteins);
    document.getElementById('meal-detail-fats').textContent = formatMacro(meal.fats);
    document.getElementById('meal-detail-carbs').textContent = formatMacro(meal.carbs);
    document.getElementById('meal-detail-products-list').innerHTML = renderMealDetailProducts(meal.products || []);
}

function renderMealDetailProducts(products) {
    if (products.length === 0) {
        return '<p class="meal-detail-empty">Детализация недоступна для старых записей</p>';
    }

    return products.map(product => `
        <article class="meal-detail-product">
            <div>
                <strong>${escapeHtml(product.name || 'Продукт')}</strong>
                <small>${formatMealProductMeta(product)}</small>
            </div>
            <span>${Number(product.calories || 0)} ккал</span>
        </article>
    `).join('');
}

function formatMealProductMeta(product) {
    const parts = [];
    const processingLabel = getProcessingLabel(product.processing || '');

    if (product.weight) {
        parts.push(`${Number(product.weight)} г`);
    }

    if (processingLabel) {
        parts.push(processingLabel);
    }

    parts.push(`Б ${formatMacro(product.proteins)}`);
    parts.push(`Ж ${formatMacro(product.fats)}`);
    parts.push(`У ${formatMacro(product.carbs)}`);

    return escapeHtml(parts.join(' · '));
}

function getProcessingLabel(processing) {
    const labels = {
        fry: 'Жарка',
        bake: 'Запекание',
        boil: 'Варка',
        stew: 'Тушение',
        grill: 'Гриль',
        steam: 'На пару',
        deep_fry: 'Фритюр',
        no_oil_fry: 'Жарка без масла'
    };

    return labels[processing] || '';
}

document.querySelector('.meal-detail-overlay').onclick = closeMealDetail;
document.querySelector('.meal-detail-panel').onclick = event => event.stopPropagation();
document.getElementById('btn-meal-detail-close').onclick = closeMealDetail;
