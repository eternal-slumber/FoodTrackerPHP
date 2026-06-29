(function () {
    const refreshButton = document.querySelector('[data-dashboard-refresh]');
    const loadingLine = document.querySelector('[data-dashboard-loading]');

    if (!refreshButton || !loadingLine) {
        return;
    }

    refreshButton.addEventListener('click', () => {
        refreshButton.disabled = true;
        loadingLine.classList.remove('admin-hidden');

        window.setTimeout(() => {
            loadingLine.textContent = 'Данные актуальны.';
            refreshButton.disabled = false;

            window.setTimeout(() => {
                loadingLine.classList.add('admin-hidden');
                loadingLine.textContent = 'Обновляем состояние...';
            }, 1200);
        }, 450);
    });
})();
