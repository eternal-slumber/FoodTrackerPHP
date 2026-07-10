(() => {
    const state = document.body.dataset.shareState;
    const content = document.getElementById('trainer-share-content');
    const unavailable = document.getElementById('trainer-share-unavailable');

    if (state !== 'ready') {
        unavailable.hidden = false;
        return;
    }

    const data = JSON.parse(document.getElementById('trainer-share-data').textContent || '{}');
    const summary = data.summary || {};
    const goals = summary.macro_goals || {};
    const visibility = data.visibility || {};
    const daysByDate = new Map((data.recent_days || []).map(day => [day.date, day]));
    let selectedDate = data.today;

    daysByDate.set(data.today, {
        ...(daysByDate.get(data.today) || {}),
        date: data.today,
        calories: summary.calories,
        proteins: summary.proteins,
        fats: summary.fats,
        carbs: summary.carbs,
        meals: data.today_meals || [],
        meal_count: (data.today_meals || []).length
    });

    content.hidden = false;
    document.getElementById('trainer-share-summary-card').hidden = !visibility.nutrition;
    document.getElementById('trainer-share-meals-card').hidden = !visibility.meals;
    document.getElementById('trainer-share-history-card').hidden = !visibility.history;
    setText('trainer-share-name', data.display_name);
    setText('trainer-share-average', round(data.average_7_days));
    document.getElementById('trainer-share-average-wrap').hidden = data.average_7_days === null;

    renderCalendar();
    renderHistory();
    renderSelectedDay();
    renderProfile(data.profile_params);

    function renderSelectedDay() {
        const day = daysByDate.get(selectedDate) || { date: selectedDate, meals: [], meal_count: 0 };
        const isToday = selectedDate === data.today;

        setText('trainer-share-date', isToday ? data.today_label : formatDate(selectedDate));
        setText('trainer-share-summary-day-label', isToday ? 'Сегодня' : formatDate(selectedDate));
        setText('trainer-share-meals-title', isToday ? 'Приёмы сегодня' : `Приёмы · ${formatDate(selectedDate)}`);
        renderDayNutrition(day);
        renderMeals(day.meals || [], isToday);
        renderAi(isToday ? data.ai_analysis : null);
        updateSelectedControls();
    }

    function renderDayNutrition(day) {
        const calories = Number(day.calories || 0);
        const goal = Number(summary.daily_goal || 0);
        const remaining = goal - calories;

        setText('trainer-share-calories', round(calories));
        setText('trainer-share-goal', round(goal));
        setText('trainer-share-remaining', remaining >= 0
            ? `Осталось ${round(remaining)} ккал`
            : `Выше нормы на ${round(Math.abs(remaining))} ккал`);
        document.getElementById('trainer-share-progress-fill').style.width =
            `${Math.min(100, goal > 0 ? (calories / goal) * 100 : 0)}%`;
        setText('trainer-share-proteins', macro(day.proteins, goals.proteins_goal));
        setText('trainer-share-fats', macro(day.fats, goals.fats_goal));
        setText('trainer-share-carbs', macro(day.carbs, goals.carbs_goal));
    }

    function renderMeals(meals, isToday) {
        const container = document.getElementById('trainer-share-meals');
        container.replaceChildren();

        if (!meals.length) {
            container.append(empty(isToday
                ? 'Сегодня приёмы ещё не добавлены.'
                : 'В этот день приёмы не добавлялись.'));
            return;
        }

        groupMeals(meals).forEach(group => container.append(renderMealGroup(group)));
    }

    function groupMeals(meals) {
        const groups = new Map();

        meals.forEach(meal => {
            const rawName = String(meal.name || meal.description || 'Приём пищи').trim();
            const prefix = rawName.split(':', 1)[0].trim().toLocaleLowerCase('ru-RU');
            const type = resolveMealType(prefix);
            const group = groups.get(type.key) || { ...type, meals: [], calories: 0 };

            group.meals.push({ ...meal, display_name: mealDisplayName(rawName, type.label) });
            group.calories += Number(meal.calories || 0);
            groups.set(type.key, group);
        });

        const order = ['breakfast', 'lunch', 'dinner', 'snack', 'other'];
        return [...groups.values()].sort((left, right) => order.indexOf(left.key) - order.indexOf(right.key));
    }

    function resolveMealType(prefix) {
        if (prefix.startsWith('завтрак')) return { key: 'breakfast', label: 'Завтрак' };
        if (prefix.startsWith('обед')) return { key: 'lunch', label: 'Обед' };
        if (prefix.startsWith('ужин')) return { key: 'dinner', label: 'Ужин' };
        if (prefix.startsWith('перекус')) return { key: 'snack', label: 'Перекус' };
        return { key: 'other', label: 'Другой приём' };
    }

    function mealDisplayName(rawName, groupLabel) {
        const separatorIndex = rawName.indexOf(':');
        if (separatorIndex >= 0) {
            return rawName.slice(separatorIndex + 1).trim() || groupLabel;
        }
        return rawName.toLocaleLowerCase('ru-RU') === groupLabel.toLocaleLowerCase('ru-RU')
            ? 'Без описания'
            : rawName;
    }

    function renderMealGroup(group) {
        const card = document.createElement('article');
        card.className = `trainer-share-meal-group meal-group-${group.key}`;
        const header = document.createElement('header');
        const title = document.createElement('h3');
        const total = document.createElement('strong');
        const list = document.createElement('div');

        title.textContent = group.label;
        total.textContent = `${round(group.calories)} ккал`;
        list.className = 'trainer-share-meal-group-list';
        header.append(title, total);

        group.meals.forEach(meal => {
            const item = document.createElement('div');
            item.className = 'trainer-share-meal';
            const copy = document.createElement('div');
            const name = document.createElement('strong');
            const detail = document.createElement('small');
            const calories = document.createElement('span');

            name.textContent = meal.display_name;
            detail.textContent = [
                meal.time || '',
                `Б ${format(meal.proteins)}`,
                `Ж ${format(meal.fats)}`,
                `У ${format(meal.carbs)}`
            ].filter(Boolean).join(' · ');
            calories.textContent = `${round(meal.calories)} ккал`;
            copy.append(name, detail);
            item.append(copy, calories);
            list.append(item);
        });

        card.append(header, list);
        return card;
    }

    function renderCalendar() {
        const container = document.getElementById('trainer-share-calendar');
        const [year, month, day] = data.today.split('-').map(Number);
        const todayDate = new Date(year, month - 1, day);

        for (let offset = 6; offset >= 0; offset -= 1) {
            const date = new Date(todayDate);
            date.setDate(todayDate.getDate() - offset);
            const key = dateKey(date);
            const item = document.createElement('button');
            const weekday = document.createElement('span');
            const number = document.createElement('strong');

            item.type = 'button';
            item.dataset.date = key;
            item.className = `trainer-share-day${daysByDate.has(key) ? ' has-data' : ''}${key === data.today ? ' is-today' : ''}`;
            item.setAttribute('aria-label', `Показать дневник за ${formatDate(key)}`);
            weekday.textContent = date.toLocaleDateString('ru-RU', { weekday: 'short' }).replace('.', '');
            number.textContent = date.getDate();
            item.append(weekday, number);
            item.addEventListener('click', () => selectDate(key));
            container.append(item);
        }
    }

    function renderHistory() {
        const container = document.getElementById('trainer-share-history');
        const days = data.recent_days || [];
        if (!days.length) {
            container.append(empty('За последние семь дней данных пока нет.'));
            return;
        }

        [...days].reverse().forEach(day => {
            const row = document.createElement('button');
            const date = document.createElement('span');
            const calories = document.createElement('strong');

            row.type = 'button';
            row.dataset.date = day.date;
            row.className = 'trainer-share-history-row';
            date.textContent = formatDate(day.date);
            calories.textContent = day.calories === undefined
                ? `${day.meal_count || 0} приёмов`
                : `${round(day.calories)} ккал · ${day.meal_count || day.meals?.length || 0} приёмов`;
            row.append(date, calories);
            row.addEventListener('click', () => selectDate(day.date));
            container.append(row);
        });
    }

    function selectDate(date) {
        selectedDate = date;
        renderSelectedDay();
        const detailCard = visibility.nutrition
            ? document.getElementById('trainer-share-summary-card')
            : visibility.meals
                ? document.getElementById('trainer-share-meals-card')
                : document.getElementById('trainer-share-history-card');
        detailCard?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function updateSelectedControls() {
        document.querySelectorAll('[data-date]').forEach(control => {
            const selected = control.dataset.date === selectedDate;
            control.classList.toggle('is-selected', selected);
            control.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
    }

    function renderAi(ai) {
        const card = document.getElementById('trainer-share-ai-card');
        card.hidden = !ai;
        if (!ai) return;
        setText('trainer-share-ai-summary', ai.summary);
        setText('trainer-share-ai-analysis', ai.analysis);
    }

    function renderProfile(profile) {
        if (!profile) return;
        document.getElementById('trainer-share-profile-card').hidden = false;
        setText('trainer-share-profile-goal', profile.goal);
        setText('trainer-share-profile-weight', `${format(profile.weight)} кг`);
        setText('trainer-share-profile-height', `${round(profile.height)} см`);
        setText('trainer-share-profile-age', `${round(profile.age)} лет`);
        setText('trainer-share-profile-gender', profile.gender === 'female' ? 'женский' : 'мужской');
    }

    function setText(id, value) { document.getElementById(id).textContent = String(value ?? ''); }
    function round(value) { return Math.round(Number(value || 0)); }
    function format(value) { return Math.round(Number(value || 0) * 10) / 10; }
    function macro(value, goal) { return `${format(value)} / ${format(goal)} г`; }
    function dateKey(date) { return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`; }
    function formatDate(key) { const [y, m, d] = key.split('-').map(Number); return new Date(y, m - 1, d).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' }); }
    function empty(text) { const node = document.createElement('p'); node.className = 'trainer-share-empty'; node.textContent = text; return node; }
})();
