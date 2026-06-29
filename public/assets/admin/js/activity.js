(function () {
    const canvas = document.getElementById('admin-user-activity-chart');
    const dataScript = document.getElementById('admin-user-activity-chart-data');
    const emptyState = document.querySelector('[data-chart-empty]');

    if (!canvas || !dataScript) {
        return;
    }

    let chartData = [];
    try {
        chartData = JSON.parse(dataScript.textContent || '[]');
    } catch (error) {
        chartData = [];
    }

    if (chartData.length === 0) {
        emptyState?.classList.remove('admin-hidden');
        return;
    }

    let selectedMetric = 'unique_entries';
    let showTrend = false;
    const labels = {
        unique_entries: 'Уникальные входы',
        visits: 'Все посещения'
    };
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const fallbackAnimationDuration = prefersReducedMotion ? 0 : 520;

    function sliceData() {
        return chartData;
    }

    function valuesFromData(data) {
        return data.map(item => Number(item[selectedMetric] || 0));
    }

    function trendValues(values) {
        if (values.length <= 1) {
            return values;
        }

        const count = values.length;
        const indexesSum = values.reduce((sum, _value, index) => sum + index, 0);
        const valuesSum = values.reduce((sum, value) => sum + value, 0);
        const indexesSquaresSum = values.reduce((sum, _value, index) => sum + index * index, 0);
        const indexValueProductsSum = values.reduce((sum, value, index) => sum + index * value, 0);
        const denominator = count * indexesSquaresSum - indexesSum * indexesSum;

        if (denominator === 0) {
            return values;
        }

        const slope = (count * indexValueProductsSum - indexesSum * valuesSum) / denominator;
        const intercept = (valuesSum - slope * indexesSum) / count;

        return values.map((_value, index) => Math.max(0, intercept + slope * index));
    }

    function chartConfig() {
        const visibleData = sliceData();
        const values = valuesFromData(visibleData);
        const datasets = [{
            label: labels[selectedMetric],
            data: values,
            backgroundColor: 'rgba(74, 163, 255, 0.42)',
            borderColor: 'rgba(74, 163, 255, 0.90)',
            borderWidth: 1,
            borderRadius: 6,
            maxBarThickness: 42
        }];

        if (showTrend) {
            datasets.push({
                type: 'line',
                label: 'Линия тренда',
                data: trendValues(values),
                backgroundColor: 'rgba(246, 196, 83, 0.16)',
                borderColor: 'rgba(246, 196, 83, 0.95)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(246, 196, 83, 1)',
                pointBorderColor: 'rgba(9, 13, 20, 1)',
                pointBorderWidth: 1,
                pointRadius: 3,
                tension: 0.25
            });
        }

        return {
            labels: visibleData.map(item => item.label),
            datasets
        };
    }

    function drawCanvasChart(progress = 1) {
        const context = canvas.getContext('2d');

        if (!context) {
            emptyState?.classList.remove('admin-hidden');
            return;
        }

        const bounds = canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        const width = Math.max(320, Math.floor(bounds.width));
        const height = Math.max(260, Math.floor(bounds.height));
        const visibleData = sliceData();
        const values = valuesFromData(visibleData);
        const trend = trendValues(values);
        const maxValue = Math.max(1, ...values, ...(showTrend ? trend : []));
        const padding = {
            top: 18,
            right: 24,
            bottom: 70,
            left: 44
        };
        const chartWidth = width - padding.left - padding.right;
        const chartHeight = height - padding.top - padding.bottom;

        function drawRoundedRect(x, y, rectWidth, rectHeight, radius) {
            const safeRadius = Math.min(radius, rectWidth / 2, rectHeight / 2);

            context.beginPath();
            context.moveTo(x + safeRadius, y);
            context.lineTo(x + rectWidth - safeRadius, y);
            context.quadraticCurveTo(x + rectWidth, y, x + rectWidth, y + safeRadius);
            context.lineTo(x + rectWidth, y + rectHeight - safeRadius);
            context.quadraticCurveTo(x + rectWidth, y + rectHeight, x + rectWidth - safeRadius, y + rectHeight);
            context.lineTo(x + safeRadius, y + rectHeight);
            context.quadraticCurveTo(x, y + rectHeight, x, y + rectHeight - safeRadius);
            context.lineTo(x, y + safeRadius);
            context.quadraticCurveTo(x, y, x + safeRadius, y);
            context.closePath();
        }

        canvas.width = Math.floor(width * ratio);
        canvas.height = Math.floor(height * ratio);
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        context.clearRect(0, 0, width, height);

        context.strokeStyle = 'rgba(148, 163, 184, 0.12)';
        context.lineWidth = 1;
        context.fillStyle = '#9aa8bb';
        context.font = '12px -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif';

        for (let index = 0; index <= 4; index += 1) {
            const y = padding.top + chartHeight - (chartHeight / 4) * index;
            const value = Math.round((maxValue / 4) * index);

            context.beginPath();
            context.moveTo(padding.left, y);
            context.lineTo(width - padding.right, y);
            context.stroke();
            context.fillText(String(value), 10, y + 4);
        }

        const gap = 14;
        const barWidth = Math.min(48, Math.max(18, (chartWidth - gap * (visibleData.length - 1)) / visibleData.length));
        const totalBarsWidth = barWidth * visibleData.length + gap * (visibleData.length - 1);
        const startX = padding.left + Math.max(0, (chartWidth - totalBarsWidth) / 2);

        visibleData.forEach((item, index) => {
            const value = Number(item[selectedMetric] || 0);
            const barHeight = value > 0 ? Math.max(3, (value / maxValue) * chartHeight) : 0;
            const animatedBarHeight = barHeight * progress;
            const x = startX + index * (barWidth + gap);
            const y = padding.top + chartHeight - animatedBarHeight;

            context.fillStyle = 'rgba(74, 163, 255, 0.42)';
            context.strokeStyle = 'rgba(74, 163, 255, 0.90)';
            context.lineWidth = 1;
            drawRoundedRect(x, y, barWidth, animatedBarHeight, 6);
            context.fill();
            context.stroke();

            context.fillStyle = '#9aa8bb';
            context.textAlign = 'center';
            context.fillText(item.label, x + barWidth / 2, height - 42);

            if (value > 0 && progress > 0.85) {
                context.fillStyle = '#f5f7fb';
                context.fillText(String(value), x + barWidth / 2, y - 8);
            }
        });

        if (showTrend && trend.length > 1) {
            context.save();
            context.globalAlpha = progress;
            context.strokeStyle = 'rgba(246, 196, 83, 0.95)';
            context.lineWidth = 2;
            context.beginPath();

            trend.forEach((value, index) => {
                const x = startX + index * (barWidth + gap) + barWidth / 2;
                const y = padding.top + chartHeight - (value / maxValue) * chartHeight;

                if (index === 0) {
                    context.moveTo(x, y);
                    return;
                }

                context.lineTo(x, y);
            });

            context.stroke();

            trend.forEach((value, index) => {
                const x = startX + index * (barWidth + gap) + barWidth / 2;
                const y = padding.top + chartHeight - (value / maxValue) * chartHeight;

                context.fillStyle = 'rgba(246, 196, 83, 1)';
                context.beginPath();
                context.arc(x, y, 3, 0, Math.PI * 2);
                context.fill();
            });
            context.restore();
        }

        context.textAlign = 'center';
        context.fillStyle = 'rgba(74, 163, 255, 0.42)';
        context.strokeStyle = 'rgba(74, 163, 255, 0.90)';
        context.lineWidth = 1;
        drawRoundedRect(width / 2 - (showTrend ? 138 : 72), height - 20, 12, 12, 3);
        context.fill();
        context.stroke();

        context.fillStyle = '#f5f7fb';
        context.font = '700 12px -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif';
        context.fillText(labels[selectedMetric], width / 2 + (showTrend ? -58 : 8), height - 10);

        if (showTrend) {
            context.strokeStyle = 'rgba(246, 196, 83, 0.95)';
            context.lineWidth = 2;
            context.beginPath();
            context.moveTo(width / 2 + 42, height - 14);
            context.lineTo(width / 2 + 58, height - 14);
            context.stroke();

            context.fillStyle = 'rgba(246, 196, 83, 1)';
            context.beginPath();
            context.arc(width / 2 + 50, height - 14, 3, 0, Math.PI * 2);
            context.fill();

            context.fillStyle = '#f5f7fb';
            context.fillText('Линия тренда', width / 2 + 112, height - 10);
        }

        context.textAlign = 'start';
    }

    let chart = null;
    let fallbackAnimationFrame = 0;

    function animateCanvasChart() {
        if (fallbackAnimationFrame) {
            window.cancelAnimationFrame(fallbackAnimationFrame);
        }

        if (fallbackAnimationDuration === 0) {
            drawCanvasChart();
            return;
        }

        const startedAt = window.performance.now();

        function tick(now) {
            const elapsed = now - startedAt;
            const linearProgress = Math.min(1, elapsed / fallbackAnimationDuration);
            const easedProgress = 1 - Math.pow(1 - linearProgress, 4);

            drawCanvasChart(easedProgress);

            if (linearProgress < 1) {
                fallbackAnimationFrame = window.requestAnimationFrame(tick);
            }
        }

        fallbackAnimationFrame = window.requestAnimationFrame(tick);
    }

    if (window.Chart) {
        chart = new window.Chart(canvas, {
            type: 'bar',
            data: chartConfig(),
            options: {
                animation: {
                    duration: prefersReducedMotion ? 0 : 650,
                    easing: 'easeOutQuart',
                    delay(context) {
                        if (context.type !== 'data') {
                            return 0;
                        }

                        return context.dataIndex * 35;
                    }
                },
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#f5f7fb',
                            boxWidth: 10,
                            boxHeight: 10,
                            padding: 18
                        }
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
    } else {
        animateCanvasChart();
    }

    function updateChart() {
        if (chart) {
            chart.data = chartConfig();
            chart.update();
            return;
        }

        animateCanvasChart();
    }

    window.addEventListener('resize', () => {
        if (!chart) {
            drawCanvasChart();
        }
    });

    document.querySelectorAll('[data-chart-metric]').forEach(button => {
        button.addEventListener('click', () => {
            selectedMetric = button.dataset.chartMetric || 'unique_entries';
            document.querySelectorAll('[data-chart-metric]').forEach(item => {
                item.classList.toggle('admin-is-active', item === button);
            });
            updateChart();
        });
    });

    document.querySelectorAll('[data-chart-trend]').forEach(button => {
        button.addEventListener('click', () => {
            showTrend = !showTrend;
            button.classList.toggle('admin-is-active', showTrend);
            button.setAttribute('aria-pressed', showTrend ? 'true' : 'false');
            updateChart();
        });
    });
})();
