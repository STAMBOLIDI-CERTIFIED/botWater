/* WaterPrize WP Admin JS */
jQuery(document).ready(function($) {
    // Auto-dismiss notices
    setTimeout(function() {
        $('.notice').fadeOut(300);
    }, 5000);

    // ─── Chart.js initialization ───
    if (typeof wpzChartData !== 'undefined' && typeof Chart !== 'undefined') {
        var colors = {
            blue: 'rgba(34,113,177,0.8)',
            blueBg: 'rgba(34,113,177,0.1)',
            green: 'rgba(0,163,42,0.8)',
            greenBg: 'rgba(0,163,42,0.1)',
            orange: 'rgba(219,166,23,0.8)',
            orangeBg: 'rgba(219,166,23,0.1)',
            purple: 'rgba(139,92,246,0.8)',
            purpleBg: 'rgba(139,92,246,0.1)',
            red: 'rgba(214,54,56,0.8)',
            cyan: 'rgba(14,165,233,0.8)',
            teal: 'rgba(20,184,166,0.8)',
        };

        var chartDefaults = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: '#f0f0f0' } }
            }
        };

        // Registrations chart
        var regData = wpzChartData.registrations || [];
        new Chart(document.getElementById('chart-registrations'), {
            type: 'line',
            data: {
                labels: regData.map(function(r) { return r.day; }),
                datasets: [{
                    label: 'Регистрации',
                    data: regData.map(function(r) { return parseInt(r.cnt); }),
                    borderColor: colors.blue,
                    backgroundColor: colors.blueBg,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: colors.blue
                }]
            },
            options: chartDefaults
        });

        // Points chart
        var ptsData = wpzChartData.points || [];
        new Chart(document.getElementById('chart-points'), {
            type: 'bar',
            data: {
                labels: ptsData.map(function(r) { return r.day; }),
                datasets: [{
                    label: 'Баллы',
                    data: ptsData.map(function(r) { return parseInt(r.total); }),
                    backgroundColor: colors.green,
                    borderRadius: 4
                }]
            },
            options: chartDefaults
        });

        // Notifications donut
        var notData = wpzChartData.notifications || [];
        var donutColors = [colors.blue, colors.green, colors.orange, colors.purple, colors.red, colors.cyan, colors.teal];
        new Chart(document.getElementById('chart-notifications'), {
            type: 'doughnut',
            data: {
                labels: notData.map(function(r) { return r.type; }),
                datasets: [{
                    data: notData.map(function(r) { return parseInt(r.cnt); }),
                    backgroundColor: donutColors.slice(0, notData.length)
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Bottles bar
        var btlData = wpzChartData.bottles || [];
        new Chart(document.getElementById('chart-bottles'), {
            type: 'bar',
            data: {
                labels: btlData.map(function(r) { return 'Партия ' + r.batch; }),
                datasets: [{
                    label: 'Количество',
                    data: btlData.map(function(r) { return parseInt(r.cnt); }),
                    backgroundColor: colors.orange,
                    borderRadius: 4
                }]
            },
            options: chartDefaults
        });
    }

    // ─── Auto-refresh online count (real-time) ───
    var lastOnline = null;
    var onlineTimer = null;

    function refreshOnline() {
        $.get(ajaxurl, { action: 'wpz_online' }, function(r) {
            var count = 0;
            if (r && r.success && r.data && r.data.count !== undefined) {
                count = parseInt(r.data.count);
            } else if (r && r.count !== undefined) {
                count = parseInt(r.count);
            }
            var $el = $('#wpz-online');
            if ($el.length) {
                var oldVal = lastOnline;
                lastOnline = count;
                $el.text(count);
                if (oldVal !== null && oldVal !== count) {
                    $el.addClass('wpz-online-flash');
                    setTimeout(function() { $el.removeClass('wpz-online-flash'); }, 600);
                }
            }
        });
    }

    // Pause when tab is hidden, resume when visible
    function startOnlineTimer() {
        stopOnlineTimer();
        refreshOnline();
        onlineTimer = setInterval(refreshOnline, 10000);
    }
    function stopOnlineTimer() {
        if (onlineTimer) { clearInterval(onlineTimer); onlineTimer = null; }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) { stopOnlineTimer(); }
        else { startOnlineTimer(); }
    });

    startOnlineTimer();
});
