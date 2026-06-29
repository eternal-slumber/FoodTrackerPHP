(function () {
    function readJsonScript(id) {
        const script = document.getElementById(id);

        if (!script) {
            return [];
        }

        try {
            const parsed = JSON.parse(script.textContent || '[]');

            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function hasPositiveValues(items, key) {
        return items.some(item => Number(item[key] || 0) > 0);
    }

    function showEmpty(selector) {
        document.querySelector(selector)?.classList.remove('admin-hidden');
    }

    function initMealUserFilter() {
        const form = document.querySelector('[data-admin-meal-filter]');
        const select = form?.querySelector('select[name="user_id"]');

        if (!form || !select) {
            return;
        }

        select.addEventListener('change', () => {
            form.submit();
        });
    }

    function drawMealHourlyChart() {
        const canvas = document.getElementById('admin-meal-hourly-chart');
        const chartData = readJsonScript('admin-meal-hourly-chart-data');

        if (!canvas || chartData.length === 0 || !hasPositiveValues(chartData, 'meals')) {
            showEmpty('[data-meal-chart-empty]');
            return;
        }

        if (!window.Chart) {
            showEmpty('[data-meal-chart-empty]');
            return;
        }

        new window.Chart(canvas, {
            type: 'bar',
            data: {
                labels: chartData.map(item => item.label),
                datasets: [{
                    label: 'Приёмов',
                    data: chartData.map(item => Number(item.meals || 0)),
                    backgroundColor: 'rgba(88, 214, 141, 0.42)',
                    borderColor: 'rgba(88, 214, 141, 0.9)',
                    borderWidth: 1,
                    borderRadius: 6,
                    maxBarThickness: 28
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        displayColors: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(148, 163, 184, 0.08)'
                        },
                        ticks: {
                            color: '#9aa8bb',
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 12
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(148, 163, 184, 0.12)'
                        },
                        ticks: {
                            color: '#9aa8bb',
                            precision: 0
                        }
                    }
                }
            }
        });
    }

    initMealUserFilter();
    drawMealHourlyChart();
})();
