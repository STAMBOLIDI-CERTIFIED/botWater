/* WaterPrize Analytics — Chart.js rendering */
document.addEventListener('DOMContentLoaded', function () {
    var C = window.WPZ_Analytics;
    if (!C || !window.Chart) return;

    var chartTitles = { registrations: 'Регистрации', points: 'Баллы', scans: 'Сканирования' };
    var chartColors = {
        registrations: { bg: 'rgba(34,113,177,0.55)', border: '#2271b1' },
        points:        { bg: 'rgba(184,134,11,0.55)',  border: '#b8860b' },
        scans:         { bg: 'rgba(0,163,42,0.55)',    border: '#00a32a' }
    };

    var canvas = document.getElementById('wpz-analytics-chart');
    var titleEl = document.getElementById('wpz-chart-title');
    var statsEl = document.getElementById('wpz-analytics-stats');
    if (!canvas) return;

    var chart = null;

    function buildChart(type) {
        var d = C.data[type] || [];
        var labels = d.map(function (r) { return r.label; });
        var values;
        if (type === 'points') {
            values = d.map(function (r) { return r.total; });
        } else {
            values = d.map(function (r) { return r.cnt; });
        }
        var col = chartColors[type] || chartColors.registrations;

        if (chart) chart.destroy();

        chart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: chartTitles[type],
                    data: values,
                    backgroundColor: col.bg,
                    borderColor: col.border,
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 11 }, maxRotation: 45 } },
                    y: { beginAtZero: true, ticks: { font: { size: 11 }, stepSize: 1 } }
                }
            }
        });

        if (titleEl) titleEl.textContent = chartTitles[type] || type;
        renderStats(type, d);
    }

    function renderStats(type, d) {
        if (!statsEl) return;
        var total = 0, max = 0, maxLabel = '';
        d.forEach(function (r) {
            var v = type === 'points' ? r.total : r.cnt;
            total += v;
            if (v > max) { max = v; maxLabel = r.label; }
        });
        var avg = d.length ? Math.round(total / d.length) : 0;

        statsEl.innerHTML =
            '<div class="wpz-stat-card wpz-blue"><div class="wpz-stat-icon">📊</div>' +
            '<div class="wpz-stat-val">' + total.toLocaleString() + '</div>' +
            '<div class="wpz-stat-label">Всего</div></div>' +

            '<div class="wpz-stat-card wpz-green"><div class="wpz-stat-icon">📈</div>' +
            '<div class="wpz-stat-val">' + avg.toLocaleString() + '</div>' +
            '<div class="wpz-stat-label">Среднее</div></div>' +

            '<div class="wpz-stat-card wpz-gold"><div class="wpz-stat-icon">🏆</div>' +
            '<div class="wpz-stat-val">' + max.toLocaleString() + '</div>' +
            '<div class="wpz-stat-label">Максимум</div></div>' +

            '<div class="wpz-stat-card wpz-purple"><div class="wpz-stat-icon">📅</div>' +
            '<div class="wpz-stat-val">' + d.length + '</div>' +
            '<div class="wpz-stat-label">Периодов</div></div>';
    }

    // Chart type selector
    var typeSelect = document.getElementById('wpz-chart-type');
    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
            buildChart(this.value);
        });
    }

    // Period buttons
    document.querySelectorAll('.wpz-period-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var p = this.getAttribute('data-period');
            var url = new URL(window.location.href);
            url.searchParams.set('period', p);
            window.location.href = url.toString();
        });
    });

    // Apply filter
    var applyBtn = document.getElementById('wpz-apply-filter');
    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            var url = new URL(window.location.href);
            url.searchParams.set('chart', typeSelect ? typeSelect.value : 'registrations');
            var from = document.getElementById('wpz-date-from');
            var to = document.getElementById('wpz-date-to');
            if (from && from.value) url.searchParams.set('from', from.value); else url.searchParams.delete('from');
            if (to && to.value) url.searchParams.set('to', to.value); else url.searchParams.delete('to');
            url.searchParams.delete('period');
            window.location.href = url.toString();
        });
    }

    // Initial render
    buildChart(C.chart || 'registrations');
});
