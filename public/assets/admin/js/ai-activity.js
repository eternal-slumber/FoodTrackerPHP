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

    function drawAiRequestsChart() {
        const canvas = document.getElementById('admin-ai-requests-chart');
        const chartData = readJsonScript('admin-ai-requests-chart-data');

        if (!canvas || chartData.length === 0 || !hasPositiveValues(chartData, 'requests')) {
            showEmpty('[data-ai-chart-empty]');
            return;
        }

        if (!window.Chart) {
            showEmpty('[data-ai-chart-empty]');
            return;
        }

        new window.Chart(canvas, {
            type: 'bar',
            data: {
                labels: chartData.map(item => item.label),
                datasets: [{
                    label: 'AI-запросов',
                    data: chartData.map(item => Number(item.requests || 0)),
                    backgroundColor: 'rgba(74, 163, 255, 0.42)',
                    borderColor: 'rgba(74, 163, 255, 0.9)',
                    borderWidth: 1,
                    borderRadius: 6,
                    maxBarThickness: 44
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
                            color: 'rgba(148, 163, 184, 0.10)'
                        },
                        ticks: {
                            color: '#9aa8bb'
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

    function drawAiTypesChart() {
        const canvas = document.getElementById('admin-ai-types-chart');
        const chartData = readJsonScript('admin-ai-types-chart-data')
            .filter(item => Number(item.value || 0) > 0);

        if (!canvas || chartData.length === 0) {
            showEmpty('[data-ai-types-empty]');
            return;
        }

        if (!window.Chart) {
            showEmpty('[data-ai-types-empty]');
            return;
        }

        new window.Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: chartData.map(item => item.label),
                datasets: [{
                    data: chartData.map(item => Number(item.value || 0)),
                    backgroundColor: [
                        'rgba(74, 163, 255, 0.72)',
                        'rgba(88, 214, 141, 0.72)',
                        'rgba(246, 196, 83, 0.72)'
                    ],
                    borderColor: 'rgba(9, 13, 20, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                cutout: '58%',
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#f5f7fb',
                            boxWidth: 10,
                            boxHeight: 10,
                            padding: 16
                        }
                    },
                    tooltip: {
                        displayColors: false
                    }
                }
            }
        });
    }

    drawAiRequestsChart();
    drawAiTypesChart();
})();
