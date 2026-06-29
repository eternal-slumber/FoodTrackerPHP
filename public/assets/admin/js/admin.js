(function () {
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

    window.adminConfig = adminConfig;

    window.adminFetch = async function adminFetch(path, options = {}) {
        const headers = new Headers(options.headers || {});
        if (!headers.has('Content-Type') && options.body) {
            headers.set('Content-Type', 'application/json');
        }

        return fetch((adminConfig.path || '') + path, {
            ...options,
            headers
        });
    };

    function initAdminLogout() {
        document.querySelectorAll('[data-admin-action="logout"]').forEach(logoutButton => {
            logoutButton.addEventListener('click', async () => {
                logoutButton.disabled = true;

                try {
                    const response = await window.adminFetch('/logout', { method: 'POST' });
                    const data = await response.json();
                    window.location.assign(data.redirect || adminConfig.path || '/');
                } catch (error) {
                    window.location.assign(adminConfig.path || '/');
                }
            });
        });
    }

    function initAdminSidebar() {
        const toggleButton = document.querySelector('[data-admin-sidebar-toggle]');
        const toggleIcon = document.querySelector('[data-admin-sidebar-toggle-icon]');
        const storageKey = 'foodtracker.admin.sidebarCollapsed';

        if (!toggleButton) {
            return;
        }

        function setCollapsed(isCollapsed) {
            document.body.classList.toggle('admin-sidebar-collapsed', isCollapsed);
            toggleButton.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
            toggleButton.setAttribute('aria-label', isCollapsed ? 'Развернуть меню' : 'Свернуть меню');

            if (toggleIcon) {
                toggleIcon.textContent = isCollapsed ? '>' : '<';
            }
        }

        function readStoredState() {
            try {
                return window.localStorage.getItem(storageKey) === '1';
            } catch (error) {
                return false;
            }
        }

        function storeState(isCollapsed) {
            try {
                window.localStorage.setItem(storageKey, isCollapsed ? '1' : '0');
            } catch (error) {
                return;
            }
        }

        setCollapsed(readStoredState());

        toggleButton.addEventListener('click', () => {
            const isCollapsed = !document.body.classList.contains('admin-sidebar-collapsed');

            setCollapsed(isCollapsed);
            storeState(isCollapsed);
        });
    }

    initAdminLogout();
    initAdminSidebar();
})();
