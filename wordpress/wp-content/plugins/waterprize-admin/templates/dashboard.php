<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>💧 ИСТОКЪ — Дашборд <a href="<?php echo admin_url('admin.php?page=wpz-analytics'); ?>" class="page-title-action">📊 Аналитика</a></h1>

    <?php if (!WaterPrize_DB::instance()->is_connected()): ?>
        <div class="notice notice-error"><p>⚠️ Нет подключения к PostgreSQL. Настройте подключение в <a href="<?php echo admin_url('admin.php?page=wpz-settings'); ?>">Настройках БД</a>.</p></div>
    <?php endif; ?>

    <!-- ═══ Основные карточки ═══ -->
    <div class="wpz-stats-grid">
        <div class="wpz-stat-card wpz-blue">
            <div class="wpz-stat-icon">👥</div>
            <div class="wpz-stat-val"><?php echo esc_html($stats['users']); ?></div>
            <div class="wpz-stat-label">Пользователей</div>
        </div>
        <div class="wpz-stat-card wpz-green wpz-pulse">
            <div class="wpz-stat-icon">🟢</div>
            <div class="wpz-stat-val" id="wpz-online"><?php echo esc_html($online); ?></div>
            <div class="wpz-stat-label">Онлайн (15 мин)</div>
        </div>
        <div class="wpz-stat-card wpz-cyan">
            <div class="wpz-stat-icon">📱</div>
            <div class="wpz-stat-val"><?php echo esc_html($stats['codes_active']); ?></div>
            <div class="wpz-stat-label">Активных QR</div>
        </div>
        <div class="wpz-stat-card wpz-yellow">
            <div class="wpz-stat-icon">🎰</div>
            <div class="wpz-stat-val"><?php echo esc_html($stats['raffles_total']); ?></div>
            <div class="wpz-stat-label">Розыгрышей</div>
        </div>
        <div class="wpz-stat-card wpz-green">
            <div class="wpz-stat-icon">🍾</div>
            <div class="wpz-stat-val"><?php echo esc_html($stats['bottles_total']); ?></div>
            <div class="wpz-stat-label">Бутылок</div>
        </div>
        <div class="wpz-stat-card wpz-purple">
            <div class="wpz-stat-icon">📦</div>
            <div class="wpz-stat-val"><?php echo esc_html($stats['orders_pending']); ?></div>
            <div class="wpz-stat-label">Заказов</div>
        </div>
    </div>

    <!-- ═══ Финансы ═══ -->
    <div class="wpz-stats-grid">
        <div class="wpz-stat-card wpz-gold">
            <div class="wpz-stat-icon">💰</div>
            <div class="wpz-stat-val"><?php echo esc_html($balance['total']); ?></div>
            <div class="wpz-stat-label">Общий баланс</div>
        </div>
        <div class="wpz-stat-card wpz-teal">
            <div class="wpz-stat-icon">📊</div>
            <div class="wpz-stat-val"><?php echo esc_html(round($balance['avg'], 1)); ?></div>
            <div class="wpz-stat-label">Средний баланс</div>
        </div>
        <div class="wpz-stat-card wpz-orange">
            <div class="wpz-stat-icon">⭐</div>
            <div class="wpz-stat-val"><?php echo esc_html($balance['active']); ?></div>
            <div class="wpz-stat-label">С балансом > 0</div>
        </div>
        <div class="wpz-stat-card wpz-indigo">
            <div class="wpz-stat-icon">🪙</div>
            <div class="wpz-stat-val"><?php echo esc_html($points['total']); ?></div>
            <div class="wpz-stat-label">Всего начислено</div>
        </div>
        <div class="wpz-stat-card wpz-cyan">
            <div class="wpz-stat-icon">🔔</div>
            <div class="wpz-stat-val"><?php echo esc_html($notif_stats['total']); ?></div>
            <div class="wpz-stat-label">Уведомлений</div>
        </div>
        <div class="wpz-stat-card wpz-red">
            <div class="wpz-stat-icon">📩</div>
            <div class="wpz-stat-val"><?php echo esc_html($notif_stats['unread']); ?></div>
            <div class="wpz-stat-label">Непрочитанных</div>
        </div>
    </div>

    <!-- ═══ Графики ═══ -->
    <div class="wpz-charts-row">
        <div class="wpz-card wpz-chart-card">
            <h2>📈 Регистрации (30 дней)</h2>
            <canvas id="chart-registrations" height="100"></canvas>
        </div>
        <div class="wpz-card wpz-chart-card">
            <h2>💰 Начисления баллов (30 дней)</h2>
            <canvas id="chart-points" height="100"></canvas>
        </div>
    </div>

    <div class="wpz-charts-row">
        <div class="wpz-card wpz-chart-card wpz-chart-half">
            <h2>🔔 Уведомления по типам</h2>
            <canvas id="chart-notifications" height="100"></canvas>
        </div>
        <div class="wpz-card wpz-chart-card wpz-chart-half">
            <h2>🍾 Бутылки по партиям</h2>
            <canvas id="chart-bottles" height="100"></canvas>
        </div>
    </div>

    <!-- ═══ Топ пользователей + Активность ═══ -->
    <div class="wpz-charts-row">
        <div class="wpz-card wpz-chart-half">
            <h2>🏆 Топ пользователей</h2>
            <?php if (empty($top_users)): ?>
                <p>Нет данных</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>#</th><th>Имя</th><th>Telegram</th><th>Баланс</th><th>XP</th><th>Ур.</th></tr></thead>
                    <tbody>
                        <?php foreach ($top_users as $i => $u): ?>
                            <tr>
                                <td><strong><?php echo $i + 1; ?></strong></td>
                                <td><?php echo esc_html($u['name'] ?: '—'); ?></td>
                                <td><code><?php echo esc_html($u['telegram_id']); ?></code></td>
                                <td><strong><?php echo esc_html($u['balance']); ?></strong></td>
                                <td><?php echo esc_html($u['tree_xp'] ?? 0); ?></td>
                                <td><?php echo esc_html($u['tree_level'] ?? 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="wpz-card wpz-chart-half">
            <h2>📋 Последние действия</h2>
            <?php if (empty($activity)): ?>
                <p>Нет действий</p>
            <?php else: ?>
                <div class="wpz-activity-list">
                    <?php foreach ($activity as $a): ?>
                        <div class="wpz-activity-item">
                            <span class="wpz-badge wpz-<?php echo $a['src'] === 'scan' ? 'blue' : 'green'; ?>">
                                <?php echo $a['src'] === 'scan' ? '📷 Скан' : '🪙 ' . esc_html($a['type']); ?>
                            </span>
                            <span class="wpz-activity-text">
                                <strong><?php echo esc_html($a['name'] ?: '—'); ?></strong>
                                <?php if ($a['amount'] > 0): ?>
                                    <span class="wpz-activity-amount">+<?php echo esc_html($a['amount']); ?></span>
                                <?php endif; ?>
                                <?php if ($a['description']): ?>
                                    <em><?php echo esc_html(mb_strimwidth($a['description'], 0, 40, '...')); ?></em>
                                <?php endif; ?>
                            </span>
                            <span class="wpz-activity-time"><?php echo $a['created_at'] ? date('d.m H:i', strtotime($a['created_at'])) : ''; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ Экспорт ═══ -->
    <div class="wpz-card">
        <h2>📥 Экспорт данных</h2>
        <div class="wpz-export-btns">
            <a href="<?php echo admin_url('admin.php?page=waterprize&export=users'); ?>" class="button">👥 Пользователи CSV</a>
            <a href="<?php echo admin_url('admin.php?page=waterprize&export=points'); ?>" class="button">🪙 Баллы CSV</a>
            <a href="<?php echo admin_url('admin.php?page=waterprize&export=codes'); ?>" class="button">📱 QR-коды CSV</a>
            <a href="<?php echo admin_url('admin.php?page=waterprize&export=bottles'); ?>" class="button">🍾 Бутылки CSV</a>
        </div>
    </div>
</div>

<script>
var wpzChartData = {
    registrations: <?php echo json_encode($reg_chart); ?>,
    points: <?php echo json_encode($pts_chart); ?>,
    notifications: <?php echo json_encode($notif_chart); ?>,
    bottles: <?php echo json_encode($bottles_chart); ?>
};
</script>
