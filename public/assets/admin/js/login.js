(function () {
    const form = document.getElementById('admin-login-form');

    if (!form) {
        return;
    }

    function readAdminConfig() {
        const configScript = document.getElementById('admin-config');

        if (!configScript) {
            return {};
        }

        try {
            return JSON.parse(configScript.textContent || '{}');
        } catch (error) {
            return {};
        }
    }

    const adminConfig = readAdminConfig();
    const button = document.getElementById('admin-login-button');
    const status = document.getElementById('admin-login-status');
    const loginInput = document.getElementById('admin-login');
    const passwordInput = document.getElementById('admin-password');

    function setStatus(message, type = '') {
        status.className = ['admin-status', type].filter(Boolean).join(' ');
        status.textContent = message;
    }

    function adminUrl(path) {
        return (adminConfig.path || '') + path;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const login = loginInput.value.trim();
        const password = passwordInput.value;

        if (login.length < 8 || password.length < 8) {
            setStatus('Введите логин и пароль.', 'admin-error');
            return;
        }

        button.disabled = true;
        setStatus('Проверяем доступ...');

        try {
            const response = await fetch(adminUrl('/auth'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ login, password })
            });
            const data = await response.json();

            if (!response.ok) {
                setStatus(data.message || 'Доступ запрещён.', 'admin-error');
                return;
            }

            setStatus('Доступ подтверждён.', 'admin-success');
            window.location.assign(data.redirect || adminUrl('/dashboard'));
        } catch (error) {
            setStatus('Ошибка авторизации.', 'admin-error');
        } finally {
            button.disabled = false;
        }
    });
})();
