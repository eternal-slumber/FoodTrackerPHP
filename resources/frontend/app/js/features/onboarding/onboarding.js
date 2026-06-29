// Registration flow and validation

document.getElementById('btn-start').onclick = () => {
    startRegisterFlow();
};

const registerSteps = {
    1: document.getElementById('register-step-1'),
    2: document.getElementById('register-step-2'),
    3: document.getElementById('register-step-3')
};

const registerStepIndicators = document.querySelectorAll('[data-register-step-indicator]');
const registerGenderButtons = Array.from(document.querySelectorAll('[data-register-gender]'));
const registerGenderControl = registerGenderButtons[0]?.closest('.register-gender-control');
const btnNext1 = document.getElementById('btn-next-1');
const btnNext2 = document.getElementById('btn-next-2');
const registerSaveButton = document.getElementById('btn-save');
const registerSaveLabel = registerSaveButton.querySelector('.register-button-label');
const registerActivityInputs = Array.from(document.querySelectorAll('input[name="activity_level"]'));
const registerGoalInputs = Array.from(document.querySelectorAll('input[name="goal"]'));
let activeRegisterStep = 1;
let isRegisterStepTransitioning = false;

function setRegisterGender(value) {
    const genderInput = document.getElementById('gender');

    genderInput.value = value;

    if (registerGenderControl) {
        const activeGenderIndex = registerGenderButtons.findIndex(button => button.dataset.registerGender === value);

        if (activeGenderIndex >= 0) {
            registerGenderControl.style.setProperty('--active-gender-index', activeGenderIndex);
        }

        registerGenderControl.dataset.activeGender = value;
    }

    registerGenderButtons.forEach(button => {
        const isActive = button.dataset.registerGender === value;

        button.classList.toggle('active', isActive);
        button.setAttribute('aria-pressed', String(isActive));
    });
    genderInput.dispatchEvent(new Event('change', { bubbles: true }));
}

registerGenderButtons.forEach(button => {
    button.addEventListener('click', () => {
        setRegisterGender(button.dataset.registerGender);
    });
});

function startRegisterFlow() {
    const welcomeScreen = document.getElementById('screen-welcome');
    const registerScreen = document.getElementById('screen-register');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (document.body.classList.contains('register-transitioning')) {
        return;
    }

    showRegisterStep(1, { instant: true });
    updateRegisterFirstStepState();

    if (reduceMotion) {
        showScreen('register');
        return;
    }

    document.body.classList.add('register-transitioning');
    registerScreen.classList.remove('hidden');
    welcomeScreen.classList.add('register-swipe-out');
    registerScreen.classList.add('register-swipe-in');
    updateTabBar('register');

    window.setTimeout(() => {
        welcomeScreen.classList.add('hidden');
        welcomeScreen.classList.remove('register-swipe-out');
        registerScreen.classList.remove('register-swipe-in');
        document.body.classList.remove('register-transitioning');
    }, 680);
}

function showRegisterStep(step, options = {}) {
    const nextStep = parseInt(step, 10);
    const nextEl = registerSteps[nextStep];
    const currentEl = registerSteps[activeRegisterStep];
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!nextEl || isRegisterStepTransitioning) {
        return;
    }

    if (options.instant || reduceMotion || !currentEl || currentEl === nextEl) {
        Object.values(registerSteps).forEach(el => {
            clearRegisterStepClasses(el);
            el.classList.add('hidden');
        });

        nextEl.classList.remove('hidden');
        activeRegisterStep = nextStep;
        updateRegisterStepper(nextStep);
        return;
    }

    isRegisterStepTransitioning = true;
    const direction = nextStep > activeRegisterStep ? 'forward' : 'back';

    clearRegisterStepClasses(currentEl);
    clearRegisterStepClasses(nextEl);
    nextEl.classList.remove('hidden');

    currentEl.classList.add('is-step-leaving', `is-step-leaving-${direction}`);
    nextEl.classList.add('is-step-entering', `is-step-entering-${direction}`);
    updateRegisterStepper(nextStep);

    window.setTimeout(() => {
        currentEl.classList.add('hidden');
        clearRegisterStepClasses(currentEl);
        clearRegisterStepClasses(nextEl);
        activeRegisterStep = nextStep;
        isRegisterStepTransitioning = false;
    }, 360);
}

function clearRegisterStepClasses(el) {
    el.classList.remove(
        'is-step-entering',
        'is-step-leaving',
        'is-step-entering-forward',
        'is-step-leaving-forward',
        'is-step-entering-back',
        'is-step-leaving-back'
    );
}

function updateRegisterStepper(activeStep) {
    registerStepIndicators.forEach(indicator => {
        const step = parseInt(indicator.dataset.registerStepIndicator, 10);
        const isActive = step === activeStep;
        const isCompleted = step < activeStep;

        indicator.classList.toggle('active', isActive);
        indicator.classList.toggle('completed', isCompleted);
        if (isActive) {
            indicator.setAttribute('aria-current', 'step');
        } else {
            indicator.removeAttribute('aria-current');
        }
    });
}

const registerBodyRules = [
    { field: document.getElementById('age'), errorId: 'register-age-error', label: 'Возраст', integer: true },
    { field: document.getElementById('height'), errorId: 'register-height-error', label: 'Рост', integer: true },
    { field: document.getElementById('weight'), errorId: 'register-weight-error', label: 'Вес', integer: false }
];

function validateRegisterNumber(rule, showError = false) {
    const rawValue = String(rule.field.value || '').trim();
    const value = Number(rawValue);
    const min = Number(rule.field.min);
    const max = Number(rule.field.max);
    let error = '';

    if (rawValue === '') {
        error = `${rule.label}: заполните поле`;
    } else if (!Number.isFinite(value)) {
        error = `${rule.label}: введите число`;
    } else if (rule.integer && !Number.isInteger(value)) {
        error = `${rule.label}: укажите целое число`;
    } else if (value < min || value > max) {
        error = `${rule.label}: допустимо от ${min} до ${max}`;
    }

    if (showError || rule.field.dataset.touched === 'true' || error === '') {
        renderRegisterFieldError(rule.field, rule.errorId, error);
    }

    return error === '';
}

function validateRegisterGender(showError = false) {
    const field = document.getElementById('gender');
    const isValid = ['male', 'female'].includes(field.value);
    const error = isValid ? '' : 'Выберите пол';

    if (showError || field.dataset.touched === 'true' || isValid) {
        renderRegisterFieldError(field, 'register-gender-error', error);
    }

    registerGenderControl?.classList.toggle('has-error', !isValid && (showError || field.dataset.touched === 'true'));
    registerGenderControl?.setAttribute('aria-invalid', String(!isValid && (showError || field.dataset.touched === 'true')));

    return isValid;
}

function validateRegisterBodyStep({ showErrors = false } = {}) {
    const fieldResults = registerBodyRules.map(rule => validateRegisterNumber(rule, showErrors));
    const genderValid = validateRegisterGender(showErrors);
    const firstInvalidRule = registerBodyRules.find((rule, index) => !fieldResults[index]);

    return {
        valid: fieldResults.every(Boolean) && genderValid,
        firstInvalid: firstInvalidRule?.field || (!genderValid ? document.getElementById('gender') : null)
    };
}

function validateRegisterChoice(inputs, allowedValues, groupId, errorId, message, showError = false) {
    const selected = inputs.find(input => input.checked);
    const isValid = Boolean(selected && allowedValues.includes(selected.value));
    const group = document.getElementById(groupId);
    const errorElement = document.getElementById(errorId);
    const shouldShowError = !isValid && showError;

    group?.classList.toggle('has-error', shouldShowError);
    group?.setAttribute('aria-invalid', String(shouldShowError));
    if (errorElement) {
        errorElement.textContent = shouldShowError ? message : '';
        errorElement.classList.toggle('hidden', !shouldShowError);
    }

    return { valid: isValid, firstInvalid: inputs[0] || null };
}

function validateRegisterActivityStep(options = {}) {
    return validateRegisterChoice(
        registerActivityInputs,
        ['minimal', 'low', 'medium', 'high', 'extra'],
        'register-activity-options',
        'register-activity-error',
        'Выберите уровень активности',
        options.showErrors === true
    );
}

function validateRegisterGoalStep(options = {}) {
    return validateRegisterChoice(
        registerGoalInputs,
        ['deficit', 'maintenance', 'surplus'],
        'register-goal-options',
        'register-goal-error',
        'Выберите цель',
        options.showErrors === true
    );
}

function renderRegisterFieldError(field, errorId, message) {
    const errorElement = document.getElementById(errorId);
    const hasError = message !== '';

    field.setAttribute('aria-invalid', String(hasError));
    field.closest('.form-group')?.classList.toggle('has-error', hasError);
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.classList.toggle('hidden', !hasError);
    }
}

function focusRegisterInvalidField(field) {
    if (!field) return;

    if (field.type === 'hidden') {
        registerGenderButtons[0]?.focus();
        return;
    }

    field.focus();
}

function updateRegisterFirstStepState() {
    btnNext1.disabled = !validateRegisterBodyStep().valid;
}

function updateRegisterActivityStepState() {
    btnNext2.disabled = !validateRegisterActivityStep().valid;
}

function updateRegisterGoalStepState() {
    registerSaveButton.disabled = !validateRegisterGoalStep().valid;
}

registerBodyRules.forEach(rule => {
    rule.field.addEventListener('blur', () => {
        rule.field.dataset.touched = 'true';
        validateRegisterNumber(rule, true);
    });
    rule.field.addEventListener('input', updateRegisterFirstStepState);
});

document.getElementById('gender').addEventListener('change', event => {
    event.currentTarget.dataset.touched = 'true';
    updateRegisterFirstStepState();
});

registerActivityInputs.forEach(input => input.addEventListener('change', () => {
    validateRegisterActivityStep({ showErrors: true });
    updateRegisterActivityStepState();
}));

registerGoalInputs.forEach(input => input.addEventListener('change', () => {
    validateRegisterGoalStep({ showErrors: true });
    updateRegisterGoalStepState();
}));

btnNext1.onclick = () => {
    const validation = validateRegisterBodyStep({ showErrors: true });
    if (!validation.valid) {
        focusRegisterInvalidField(validation.firstInvalid);
        return;
    }

    showRegisterStep(2);
};

btnNext2.onclick = () => {
    const validation = validateRegisterActivityStep({ showErrors: true });
    if (!validation.valid) {
        focusRegisterInvalidField(validation.firstInvalid);
        return;
    }

    showRegisterStep(3);
};

document.getElementById('btn-back-2').onclick = () => {
    showRegisterStep(1);
};

document.getElementById('btn-back-3').onclick = () => {
    showRegisterStep(2);
};

updateRegisterFirstStepState();
updateRegisterActivityStepState();
updateRegisterGoalStepState();

registerSaveButton.onclick = async () => {
    if (registerSaveButton.disabled) {
        return;
    }

    const bodyValidation = validateRegisterBodyStep({ showErrors: true });
    if (!bodyValidation.valid) {
        showRegisterStep(1);
        window.setTimeout(() => focusRegisterInvalidField(bodyValidation.firstInvalid), 380);
        return;
    }

    const activityValidation = validateRegisterActivityStep({ showErrors: true });
    if (!activityValidation.valid) {
        showRegisterStep(2);
        window.setTimeout(() => focusRegisterInvalidField(activityValidation.firstInvalid), 380);
        return;
    }

    if (!validateRegisterGoalStep({ showErrors: true }).valid) {
        return;
    }

    const age = document.getElementById('age').value;
    const height = document.getElementById('height').value;
    const weight = document.getElementById('weight').value;
    const gender = document.getElementById('gender').value;
    const activityLevel = document.querySelector('input[name="activity_level"]:checked')?.value || '';
    const goal = document.querySelector('input[name="goal"]:checked')?.value || '';

    const data = {
        age: parseInt(age),
        height: parseInt(height),
        weight: parseFloat(weight),
        gender,
        activity_level: activityLevel,
        goal
    };

    registerSaveButton.disabled = true;
    registerSaveLabel.textContent = 'Рассчитываем...';
    tg.MainButton.setText('Рассчитываем...').show();

    try {
        const result = await apiRequestJson('/api/register', {
            method: 'POST',
            json: data
        });

        userData = {
            registered: true,
            daily_goal: result.daily_goal,
            age: data.age,
            height: data.height,
            weight: data.weight,
            gender: data.gender,
            activity_level: data.activity_level,
            goal: data.goal,
            macro_goals: result.macro_goals || {}
        };
        renderRegistrationSuccess(result, data);
        showScreen('registerSuccess');
        haptic('success');
    } catch (error) {
        const validationErrors = error instanceof ApiError ? error.data?.errors : null;
        tg.showAlert(
            validationErrors
                ? Object.values(validationErrors).join('\n')
                : (error?.message || 'Ошибка соединения с сервером')
        );
    } finally {
        registerSaveLabel.textContent = 'Рассчитать';
        updateRegisterGoalStepState();
        tg.MainButton.hide();
    }
};

function renderRegistrationSuccess(result, registrationData) {
    const macroGoals = result.macro_goals || {};

    setElementText('register-success-calories', Math.round(Number(result.daily_goal || 0)));
    setElementText('register-success-proteins', Math.round(Number(macroGoals.proteins_goal || 0)));
    setElementText('register-success-fats', Math.round(Number(macroGoals.fats_goal || 0)));
    setElementText('register-success-carbs', Math.round(Number(macroGoals.carbs_goal || 0)));
    setElementText('register-success-goal', formatGoalLabel(registrationData.goal));
    setElementText('register-success-activity', formatActivityLabel(registrationData.activity_level));
}

document.getElementById('btn-register-finish').onclick = () => {
    updateUserUI();
    showScreen('main');
    haptic('light');
};
