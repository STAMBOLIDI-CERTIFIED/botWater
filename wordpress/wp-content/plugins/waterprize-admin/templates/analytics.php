<?php if (!defined('ABSPATH')) exit;
$rest_url = get_rest_url('waterprize/v1/analytics');
$nonce = wp_create_nonce('wp_rest');
?>
<div class="wrap">
    <h1>📊 Аналитика</h1>

    <!-- Tabs -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=wpz-analytics" class="nav-tab <?php echo $tab !== 'journey' ? 'nav-tab-active' : ''; ?>">Статистика</a>
        <a href="?page=wpz-analytics&tab=journey" class="nav-tab <?php echo $tab === 'journey' ? 'nav-tab-active' : ''; ?>">Путь пользователя</a>
    </h2>

<?php if ($tab !== 'journey'): ?>
    <!-- Filters -->
    <div class="wpz-card wpz-analytics-filters">
        <div class="wpz-filter-row">
            <div class="wpz-filter-group">
                <label>График:</label>
                <select id="wpz-chart-type">
                    <option value="registrations" <?php selected($chart, 'registrations'); ?>>Регистрации</option>
                    <option value="points" <?php selected($chart, 'points'); ?>>Баллы</option>
                    <option value="scans" <?php selected($chart, 'scans'); ?>>Сканирования</option>
                </select>
            </div>
            <div class="wpz-filter-group">
                <label>Период:</label>
                <div class="wpz-period-btns">
                    <button type="button" class="button wpz-period-btn <?php echo $period === 'day' ? 'button-primary' : ''; ?>" data-period="day">День</button>
                    <button type="button" class="button wpz-period-btn <?php echo $period === 'week' ? 'button-primary' : ''; ?>" data-period="week">Неделя</button>
                    <button type="button" class="button wpz-period-btn <?php echo $period === 'month' ? 'button-primary' : ''; ?>" data-period="month">Месяц</button>
                    <button type="button" class="button wpz-period-btn <?php echo $period === 'year' ? 'button-primary' : ''; ?>" data-period="year">Год</button>
                </div>
            </div>
            <div class="wpz-filter-group">
                <label>С:</label>
                <input type="date" id="wpz-date-from" value="<?php echo esc_attr($from); ?>">
            </div>
            <div class="wpz-filter-group">
                <label>По:</label>
                <input type="date" id="wpz-date-to" value="<?php echo esc_attr($to); ?>">
            </div>
            <div class="wpz-filter-group">
                <button type="button" class="button button-primary" id="wpz-apply-filter">Применить</button>
            </div>
        </div>
    </div>

    <!-- Chart -->
    <div class="wpz-card wpz-analytics-chart-card">
        <h2 id="wpz-chart-title">Регистрации</h2>
        <div style="height:350px;position:relative;">
            <canvas id="wpz-analytics-chart"></canvas>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="wpz-stats-grid" id="wpz-analytics-stats"></div>

<?php else: ?>
    <!-- Journey Stats -->
    <div class="wpz-stats-grid">
        <div class="wpz-stat-card">
            <div class="wpz-stat-icon" style="background:rgba(14,165,233,0.1);color:#0EA5E9">📱</div>
            <div class="wpz-stat-info">
                <div class="wpz-stat-value"><?php echo number_format($journey_stats['total_scans'], 0, '', ' '); ?></div>
                <div class="wpz-stat-label">Сканирований</div>
            </div>
        </div>
        <div class="wpz-stat-card">
            <div class="wpz-stat-icon" style="background:rgba(61,158,106,0.1);color:#3D9E6A">🎁</div>
            <div class="wpz-stat-info">
                <div class="wpz-stat-value"><?php echo number_format($journey_stats['total_buys'], 0, '', ' '); ?></div>
                <div class="wpz-stat-label">Покупок купонов</div>
            </div>
        </div>
        <div class="wpz-stat-card">
            <div class="wpz-stat-icon" style="background:rgba(234,179,8,0.1);color:#EAB308">✅</div>
            <div class="wpz-stat-info">
                <div class="wpz-stat-value"><?php echo number_format($journey_stats['total_redeems'], 0, '', ' '); ?></div>
                <div class="wpz-stat-label">Использований</div>
            </div>
        </div>
        <div class="wpz-stat-card">
            <div class="wpz-stat-icon" style="background:rgba(168,85,247,0.1);color:#A855F7">💰</div>
            <div class="wpz-stat-info">
                <div class="wpz-stat-value"><?php echo number_format($journey_stats['total_rewards'], 0, '', ' '); ?></div>
                <div class="wpz-stat-label">Наград партнёрам</div>
            </div>
        </div>
    </div>

    <!-- User Journeys Table -->
    <div class="wpz-card">
        <h2>Пути пользователей</h2>
        <div style="overflow-x:auto">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50px">ID</th>
                        <th style="width:120px">Пользователь</th>
                        <th style="width:120px">Telegram ID</th>
                        <th style="width:150px">Партнёр</th>
                        <th style="width:150px">Действие</th>
                        <th style="width:100px">Баллы</th>
                        <th style="width:100px">Награда</th>
                        <th style="width:150px">Дата</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($journeys)): ?>
                        <tr><td colspan="8" style="text-align:center;padding:20px;color:#666">Нет данных о путях пользователей</td></tr>
                    <?php else: ?>
                        <?php foreach ($journeys as $j): ?>
                            <tr>
                                <td><?php echo esc_html($j['id']); ?></td>
                                <td><?php echo esc_html($j['user_name'] ?? '—'); ?></td>
                                <td><?php echo esc_html($j['telegram_id'] ?? '—'); ?></td>
                                <td><?php echo esc_html($j['partner_name'] ?? '—'); ?></td>
                                <td>
                                    <?php
                                    $action_labels = [
                                        'partner_scan' => '📱 Скан QR',
                                        'coupon_buy' => '🎁 Покупка купона',
                                        'coupon_redeem' => '✅ Использование',
                                    ];
                                    echo esc_html($action_labels[$j['action_type']] ?? $j['action_type']);
                                    ?>
                                </td>
                                <td><?php echo $j['points_used'] > 0 ? '-' . esc_html($j['points_used']) : '—'; ?></td>
                                <td><?php echo $j['partner_reward'] > 0 ? '+' . esc_html($j['partner_reward']) : '—'; ?></td>
                                <td><?php echo esc_html(date('d.m.Y H:i', strtotime($j['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Partner Rewards Summary -->
    <div class="wpz-card">
        <h2>Вознаграждения партнёров</h2>
        <div style="overflow-x:auto">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Партнёр</th>
                        <th>Количество конверсий</th>
                        <th>Общая награда</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rewards_summary = [];
                    foreach ($journeys as $j) {
                        if ($j['action_type'] === 'coupon_redeem' && !empty($j['metadata']['reward_for_user'])) {
                            $pid = $j['metadata']['reward_for_user'] ?? 0;
                            if (!isset($rewards_summary[$j['partner_id']])) {
                                $rewards_summary[$j['partner_id']] = [
                                    'name' => $j['partner_name'] ?? 'Партнёр',
                                    'count' => 0,
                                    'total' => 0,
                                ];
                            }
                            $rewards_summary[$j['partner_id']]['count']++;
                            $rewards_summary[$j['partner_id']]['total'] += $j['partner_reward'];
                        }
                    }
                    if (empty($rewards_summary)): ?>
                        <tr><td colspan="3" style="text-align:center;padding:20px;color:#666">Нет вознаграждений</td></tr>
                    <?php else: ?>
                        <?php foreach ($rewards_summary as $r): ?>
                            <tr>
                                <td><?php echo esc_html($r['name']); ?></td>
                                <td><?php echo esc_html($r['count']); ?></td>
                                <td><strong><?php echo esc_html($r['total']); ?> баллов</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
</div>

<?php if ($tab !== 'journey'): ?>
<script>
var WPZ_Analytics = {
    restUrl: <?php echo json_encode($rest_url); ?>,
    nonce: <?php echo json_encode($nonce); ?>,
    chart: <?php echo json_encode($chart); ?>,
    period: <?php echo json_encode($period); ?>,
    data: {
        registrations: <?php echo json_encode($reg_chart); ?>,
        points: <?php echo json_encode($pts_chart); ?>,
        scans: <?php echo json_encode($scans_chart); ?>
    }
};
</script>
<?php endif; ?>
