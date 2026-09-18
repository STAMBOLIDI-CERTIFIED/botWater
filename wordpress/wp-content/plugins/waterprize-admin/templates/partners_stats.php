<?php if (!defined('ABSPATH')) exit;
$rest_url = get_rest_url('waterprize/v1/partner-stats');
$nonce = wp_create_nonce('wp_rest');
$total_scans = 0;
$total_unique = 0;
$total_points = 0;
foreach ($summary as $row) {
    $total_scans += $row['total_scans'];
    $total_unique += $row['unique_users'];
    $total_points += $row['total_points'];
}
?>
<div class="wrap">
    <h1>📊 Статистика партнёров</h1>

    <!-- Summary Cards -->
    <div class="wpz-stats-grid" style="margin-bottom:20px;">
        <div class="wpz-stat-card wpz-blue">
            <div class="wpz-stat-icon">📡</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($total_scans); ?></div>
            <div class="wpz-stat-label">Всего сканирований</div>
        </div>
        <div class="wpz-stat-card wpz-green">
            <div class="wpz-stat-icon">👥</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($total_unique); ?></div>
            <div class="wpz-stat-label">Уникальных пользователей</div>
        </div>
        <div class="wpz-stat-card wpz-purple">
            <div class="wpz-stat-icon">⭐</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($total_points); ?></div>
            <div class="wpz-stat-label">Всего баллов начислено</div>
        </div>
        <div class="wpz-stat-card wpz-orange">
            <div class="wpz-stat-icon">🏪</div>
            <div class="wpz-stat-val"><?php echo count($summary); ?></div>
            <div class="wpz-stat-label">Партнёров</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="wpz-card" style="margin-bottom:20px;">
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <label><strong>Партнёр:</strong></label>
            <select id="wpz-ps-partner">
                <option value="0">Все партнёры</option>
                <?php foreach ($summary as $row): ?>
                    <option value="<?php echo esc_attr($row['category_id']); ?>" <?php selected($category_id, $row['category_id']); ?>>
                        <?php echo esc_html($row['partner_name']); ?> (<?php echo $row['total_scans']; ?> сканов)
                    </option>
                <?php endforeach; ?>
            </select>

            <label><strong>Период:</strong></label>
            <div style="display:flex;gap:4px;">
                <button type="button" class="button wpz-ps-period <?php echo $period === 'hour' ? 'button-primary' : ''; ?>" data-period="hour">Час</button>
                <button type="button" class="button wpz-ps-period <?php echo $period === 'day' ? 'button-primary' : ''; ?>" data-period="day">День</button>
                <button type="button" class="button wpz-ps-period <?php echo $period === 'week' ? 'button-primary' : ''; ?>" data-period="week">Неделя</button>
                <button type="button" class="button wpz-ps-period <?php echo $period === 'month' ? 'button-primary' : ''; ?>" data-period="month">Месяц</button>
            </div>

            <label>С:</label>
            <input type="date" id="wpz-ps-from" value="<?php echo esc_attr($from); ?>">
            <label>По:</label>
            <input type="date" id="wpz-ps-to" value="<?php echo esc_attr($to); ?>">
            <button type="button" class="button button-primary" id="wpz-ps-apply">Применить</button>
        </div>
    </div>

    <!-- Chart -->
    <div class="wpz-card" style="margin-bottom:20px;">
        <h2>Динамика сканирований</h2>
        <div style="height:320px;position:relative;">
            <canvas id="wpz-ps-chart"></canvas>
        </div>
    </div>

    <!-- Per-partner table -->
    <div class="wpz-card" style="margin-bottom:20px;">
        <h2>Сводка по партнёрам</h2>
        <table class="widefat striped" style="margin-top:8px;">
            <thead>
                <tr>
                    <th>Партнёр</th>
                    <th>QR-код</th>
                    <th>Баллы за скан</th>
                    <th>Всего сканов</th>
                    <th>Уник. пользователей</th>
                    <th>Баллов начислено</th>
                    <th>Последний скан</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($summary)): ?>
                    <tr><td colspan="8">Нет данных</td></tr>
                <?php else: ?>
                    <?php foreach ($summary as $row): ?>
                        <tr style="<?php echo $row['category_id'] == $category_id ? 'background:#e8f5e9;' : ''; ?>">
                            <td><strong><?php echo esc_html($row['partner_name']); ?></strong></td>
                            <td><code style="font-size:11px;"><?php echo esc_html($row['qr_code']); ?></code></td>
                            <td><?php echo $row['scan_points']; ?></td>
                            <td><strong><?php echo number_format_i18n($row['total_scans']); ?></strong></td>
                            <td><?php echo number_format_i18n($row['unique_users']); ?></td>
                            <td><?php echo number_format_i18n($row['total_points']); ?></td>
                            <td><?php echo $row['last_scan_at'] ? date_i18n('d.m.Y H:i', strtotime($row['last_scan_at'])) : '—'; ?></td>
                            <td>
                                <a href="?page=wpz-partner-stats&partner_id=<?php echo $row['category_id']; ?>" class="button button-small">Детали</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Top Users who scan partners -->
    <div class="wpz-card" style="margin-bottom:20px;">
        <h2>Топ пользователей по сканированиям партнёров</h2>
        <table class="widefat striped" style="margin-top:8px;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Имя</th>
                    <th>Username</th>
                    <th>Telegram ID</th>
                    <th>Баланс</th>
                    <th>Всего сканов</th>
                    <th>Баллов получено</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($top_users)): ?>
                    <tr><td colspan="7">Нет данных</td></tr>
                <?php else: ?>
                    <?php foreach ($top_users as $i => $u): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo esc_html($u['user_name']); ?></strong></td>
                            <td><?php echo esc_html($u['username']); ?></td>
                            <td><code><?php echo $u['telegram_id']; ?></code></td>
                            <td><?php echo number_format_i18n($u['balance']); ?></td>
                            <td><strong><?php echo number_format_i18n($u['scans_count']); ?></strong></td>
                            <td><?php echo number_format_i18n($u['total_points']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Detail: per-user scans for selected partner -->
    <?php if ($category_id > 0 && !empty($detail)): ?>
    <div class="wpz-card" style="margin-bottom:20px;">
        <h2>Детали сканирований: <?php echo esc_html($summary[array_search($category_id, array_column($summary, 'category_id'))]['partner_name'] ?? ''); ?></h2>
        <table class="widefat striped" style="margin-top:8px;">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Пользователь</th>
                    <th>Username</th>
                    <th>Баланс</th>
                    <th>Уровень</th>
                    <th>Баллов за скан</th>
                    <th>QR-код</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detail as $row): ?>
                    <tr>
                        <td><?php echo date_i18n('d.m.Y H:i', strtotime($row['scanned_at'])); ?></td>
                        <td><strong><?php echo esc_html($row['name']); ?></strong></td>
                        <td><?php echo esc_html($row['username']); ?></td>
                        <td><?php echo number_format_i18n($row['balance']); ?></td>
                        <td><?php echo $row['tree_level']; ?></td>
                        <td><?php echo $row['points_earned']; ?></td>
                        <td><code style="font-size:11px;"><?php echo esc_html($row['qr_code']); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    var chartData = <?php echo json_encode($chart_data); ?>;
    var restUrl = <?php echo json_encode($rest_url); ?>;
    var wpNonce = <?php echo json_encode($nonce); ?>;
    var chart = null;
    var currentPeriod = <?php echo json_encode($period); ?>;
    var currentPartner = <?php echo json_encode($category_id); ?>;

    function renderChart(labels, scans, points, users) {
        var ctx = document.getElementById('wpz-ps-chart');
        if (!ctx) return;
        if (chart) chart.destroy();

        chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Сканирования',
                        data: scans,
                        backgroundColor: 'rgba(34,113,177,0.7)',
                        borderColor: 'rgba(34,113,177,1)',
                        borderWidth: 1,
                        yAxisID: 'y',
                        order: 2
                    },
                    {
                        label: 'Уник. пользователи',
                        data: users,
                        type: 'line',
                        borderColor: 'rgba(0,163,42,1)',
                        backgroundColor: 'rgba(0,163,42,0.1)',
                        fill: false,
                        tension: 0.3,
                        pointRadius: 4,
                        borderWidth: 2,
                        yAxisID: 'y',
                        order: 1
                    },
                    {
                        label: 'Баллы',
                        data: points,
                        type: 'line',
                        borderColor: 'rgba(139,92,246,1)',
                        backgroundColor: 'rgba(139,92,246,0.1)',
                        fill: false,
                        tension: 0.3,
                        pointRadius: 4,
                        borderWidth: 2,
                        yAxisID: 'y1',
                        order: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleFont: { size: 13 },
                        bodyFont: { size: 12 },
                        padding: 10,
                        cornerRadius: 6
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 45, font: { size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        grid: { color: '#f0f0f0' },
                        ticks: { font: { size: 11 } },
                        title: { display: true, text: 'Сканирования / Пользователи' }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { display: false },
                        ticks: { font: { size: 11 } },
                        title: { display: true, text: 'Баллы' }
                    }
                }
            }
        });
    }

    function loadData(partnerId, period, from, to) {
        var url = restUrl + '?period=' + encodeURIComponent(period) + '&partner_id=' + partnerId;
        if (from) url += '&from=' + encodeURIComponent(from);
        if (to) url += '&to=' + encodeURIComponent(to);

        jQuery('#wpz-ps-chart').css('opacity', '0.5');
        jQuery.ajax({
            url: url,
            headers: { 'X-WP-Nonce': wpNonce },
            success: function(resp) {
                var labels = [], scans = [], points = [], users = [];
                (resp.data || []).forEach(function(d) {
                    labels.push(d.label);
                    scans.push(d.cnt || 0);
                    points.push(d.total_points || 0);
                    users.push(d.unique_users || 0);
                });
                renderChart(labels, scans, points, users);
            },
            complete: function() {
                jQuery('#wpz-ps-chart').css('opacity', '1');
            }
        });
    }

    function updateURL(partnerId, period, from, to) {
        var p = new URLSearchParams();
        p.set('page', 'wpz-partner-stats');
        if (partnerId) p.set('partner_id', partnerId);
        p.set('period', period);
        if (from) p.set('from', from);
        if (to) p.set('to', to);
        window.history.replaceState({}, '', 'admin.php?' + p.toString());
    }

    jQuery(function() {
        var labels = [], scans = [], points = [], users = [];
        chartData.forEach(function(d) {
            labels.push(d.label);
            scans.push(d.cnt || 0);
            points.push(d.total_points || 0);
            users.push(d.unique_users || 0);
        });
        renderChart(labels, scans, points, users);

        jQuery('.wpz-ps-period').on('click', function() {
            currentPeriod = jQuery(this).data('period');
            jQuery('.wpz-ps-period').removeClass('button-primary');
            jQuery(this).addClass('button-primary');
            loadData(currentPartner, currentPeriod, jQuery('#wpz-ps-from').val(), jQuery('#wpz-ps-to').val());
            updateURL(currentPartner, currentPeriod, jQuery('#wpz-ps-from').val(), jQuery('#wpz-ps-to').val());
        });

        jQuery('#wpz-ps-partner').on('change', function() {
            currentPartner = jQuery(this).val();
            loadData(currentPartner, currentPeriod, jQuery('#wpz-ps-from').val(), jQuery('#wpz-ps-to').val());
            updateURL(currentPartner, currentPeriod, jQuery('#wpz-ps-from').val(), jQuery('#wpz-ps-to').val());
            window.location.href = 'admin.php?page=wpz-partner-stats&partner_id=' + currentPartner + '&period=' + currentPeriod;
        });

        jQuery('#wpz-ps-apply').on('click', function() {
            loadData(currentPartner, currentPeriod, jQuery('#wpz-ps-from').val(), jQuery('#wpz-ps-to').val());
            updateURL(currentPartner, currentPeriod, jQuery('#wpz-ps-from').val(), jQuery('#wpz-ps-to').val());
        });
    });
})();
</script>
