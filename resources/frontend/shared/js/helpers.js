function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatMacro(value) {
    const number = Number(value || 0);
    return Number.isInteger(number) ? String(number) : number.toFixed(1);
}

function getTimezoneOffsetMinutes() {
    return new Date().getTimezoneOffset();
}

function getMealSlotFromDescription(description) {
    const prefix = String(description || '')
        .split(':', 1)[0]
        .trim()
        .toLocaleLowerCase('ru-RU')
        .replaceAll('ё', 'е');

    return {
        завтрак: 'breakfast',
        обед: 'lunch',
        ужин: 'dinner',
        перекус: 'snacks',
        перекусы: 'snacks'
    }[prefix] || null;
}

function setElementText(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.textContent = String(value ?? '');
    }
}

function formatActivityLabel(activityLevel) {
    return {
        minimal: 'Минимальная',
        low: 'Низкая',
        medium: 'Средняя',
        high: 'Высокая',
        extra: 'Очень высокая'
    }[activityLevel || 'medium'] || 'Средняя';
}

function formatGoalLabel(goal) {
    return {
        deficit: 'Похудение',
        maintenance: 'Поддержание',
        surplus: 'Набор массы'
    }[goal || 'maintenance'] || 'Поддержание';
}
