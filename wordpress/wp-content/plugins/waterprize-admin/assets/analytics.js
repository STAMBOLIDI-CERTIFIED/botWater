/* WaterPrize Analytics */
(function() {
    if (typeof WPZ_Analytics === 'undefined' || typeof Chart === 'undefined') return;

    var cfg = WPZ_Analytics;
    var chart = null;
    var initialLoaded = false;

    var titles = {
        registrations: 'Регистрации',
        points: 'Баллы',
        scans: 'Сканирования'
    };

    function renderChart(type, labels, data, extra) {
        var ctx = document.getElementById('wpz-analytics-chart');
        if (!ctx) return;
        if (chart) chart.destroy();

        var colors = {
            registrations: { border: 'rgba(34,113,177,1)', bg: 'rgba(34,113,177,0.15)' },
            points:        { border: 'rgba(0,163,42,1)',    bg: 'rgba(0,163,42,0.15)' },
            scans:         { border: 'rgba(14,165,233,1)',  bg: 'rgba(14,165,233,0.15)' }
        };
        var c = colors[type] || colors.registrations;

        var datasets;
        if (type === 'points') {
            datasets = [
                {
                    label: 'Сумма баллов',
                    data: data,
                    borderColor: c.border,
                    backgroundColor: c.bg,
                    fill: true,
                    tension: 0.3,
                    pointRadius: data.length > 60 ? 0 : 4,
                    yAxisID: 'y'
                },
                {
                    label: 'Количество начислений',
                    data: extra || [],
                    borderColor: 'rgba(139,92,246,1)',
                    backgroundColor: 'rgba(139,92,246,0.15)',
                    fill: false,
                    tension: 0.3,
                    pointRadius: (extra || []).length > 60 ? 0 : 3,
                    yAxisID: 'y1'
                }
            ];
        } else {
            datasets = [{
                label: titles[type],
                data: data,
                borderColor: c.border,
                backgroundColor: c.bg,
                fill: true,
                tension: 0.3,
                pointRadius: data.length > 60 ? 0 : 4,
                borderWidth: 2
            }];
        }

        var scales = {
            x: {
                grid: { display: false },
                ticks: { maxRotation: 45, font: { size: 11 } }
            },
            y: {
                beginAtZero: true,
                grid: { color: '#f0f0f0' },
                ticks: { font: { size: 11 } }
            }
        };

        if (type === 'points') {
            scales.y1 = {
                position: 'right',
                beginAtZero: true,
                grid: { display: false },
                ticks: { font: { size: 11 } }
            };
        }

        chart = new Chart(ctx, {
            type: type === 'scans' ? 'bar' : 'line',
            data: { labels: labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: type === 'points', position: 'top' },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleFont: { size: 13 },
                        bodyFont: { size: 12 },
                        padding: 10,
                        cornerRadius: 6
                    }
                },
                scales: scales
            }
        });
    }

    function updateStats(data, type) {
        var el = document.getElementById('wpz-analytics-stats');
        if (!el) return;
        var total = 0, max = 0, count = data.length;
        data.forEach(function(d) {
            var val = type === 'points' ? (d.total || 0) : (d.cnt || 0);
            total += val;
            if (val > max) max = val;
        });
        var avg = count ? Math.round(total / count) : 0;
        var title = titles[type] || type;
        el.innerHTML =
            '<div class="wpz-stat-card wpz-blue"><div class="wpz-stat-val">' + total.toLocaleString() + '</div><div class="wpz-stat-label">Всего: ' + title + '</div></div>' +
            '<div class="wpz-stat-card wpz-green"><div class="wpz-stat-val">' + avg.toLocaleString() + '</div><div class="wpz-stat-label">Среднее за период</div></div>' +
            '<div class="wpz-stat-card wpz-cyan"><div class="wpz-stat-val">' + max.toLocaleString() + '</div><div class="wpz-stat-label">Максимум</div></div>' +
            '<div class="wpz-stat-card wpz-purple"><div class="wpz-stat-val">' + count + '</div><div class="wpz-stat-label">Точек данных</div></div>';
    }

    function loadChart(type, period, from, to, forceAjax) {
        var labels = [], data = [], extra = [];

        function renderFromSrc(src) {
            src.forEach(function(d) {
                labels.push(d.label);
                data.push(type === 'points' ? (d.total || 0) : (d.cnt || 0));
                if (type === 'points') extra.push(d.cnt || 0);
            });
            renderChart(type, labels, data, extra);
            updateStats(src, type);
        }

        // First load: use PHP-injected data (no AJAX needed)
        if (!forceAjax && initialLoaded === false && cfg.data[type] && cfg.data[type].length > 0) {
            renderFromSrc(cfg.data[type]);
            initialLoaded = true;
            return;
        }
        initialLoaded = true;

        // All subsequent loads: always fetch fresh data via AJAX
        var url = cfg.restUrl + '?period=' + encodeURIComponent(period) + '&chart=' + encodeURIComponent(type);
        if (from) url += '&from=' + encodeURIComponent(from);
        if (to) url += '&to=' + encodeURIComponent(to);

        jQuery.ajax({
            url: url,
            headers: { 'X-WP-Nonce': cfg.nonce },
            beforeSend: function() {
                jQuery('#wpz-analytics-chart').css('opacity', '0.5');
            },
            success: function(resp) {
                var src = resp.data || [];
                renderFromSrc(src);
            },
            error: function() {
                jQuery('#wpz-analytics-chart').css('opacity', '1');
            },
            complete: function() {
                jQuery('#wpz-analytics-chart').css('opacity', '1');
            }
        });
    }

    function updateURL(period, from, to, chartType) {
        var params = new URLSearchParams();
        params.set('page', 'wpz-analytics');
        params.set('period', period);
        params.set('chart', chartType);
        if (from) params.set('from', from);
        if (to) params.set('to', to);
        window.history.replaceState({}, '', 'admin.php?' + params.toString());
    }

    jQuery(function() {
        var currentType = cfg.chart;
        var currentPeriod = cfg.period;

        loadChart(currentType, currentPeriod, jQuery('#wpz-date-from').val(), jQuery('#wpz-date-to').val(), false);

        jQuery('.wpz-period-btn').on('click', function() {
            currentPeriod = jQuery(this).data('period');
            jQuery('.wpz-period-btn').removeClass('button-primary');
            jQuery(this).addClass('button-primary');
            loadChart(currentType, currentPeriod, jQuery('#wpz-date-from').val(), jQuery('#wpz-date-to').val(), true);
            updateURL(currentPeriod, jQuery('#wpz-date-from').val(), jQuery('#wpz-date-to').val(), currentType);
        });

        jQuery('#wpz-chart-type').on('change', function() {
            currentType = jQuery(this).val();
            jQuery('#wpz-chart-title').text(titles[currentType]);
            loadChart(currentType, currentPeriod, jQuery('#wpz-date-from').val(), jQuery('#wpz-date-to').val(), true);
            updateURL(currentPeriod, jQuery('#wpz-date-from').val(), jQuery('#wpz-date-to').val(), currentType);
        });

        jQuery('#wpz-apply-filter').on('click', function() {
            loadChart(currentType, currentPeriod, jQuery('#wpz-date-from').val(), jQuery('#wpz-date-to').val(), true);
            updateURL(currentPeriod, jQuery('#wpz-date-from').val(), jQuery('#wpz-date-to').val(), currentType);
        });
    });
})();
