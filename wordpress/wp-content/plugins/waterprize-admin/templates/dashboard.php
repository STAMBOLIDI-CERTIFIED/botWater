<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>💧 ИСТОКЪ — Дашборд <a href="<?php echo admin_url('admin.php?page=wpz-analytics'); ?>" class="page-title-action">📊 Аналитика</a></h1>

    <?php if (!WaterPrize_DB::instance()->is_connected()): ?>
        <div class="notice notice-error"><p>⚠️ Нет подключения к PostgreSQL. Настройте подключение в <a href="<?php echo admin_url('admin.php?page=wpz-settings'); ?>">Настройках БД</a>.</p></div>
    <?php endif; ?>

    <!-- ═══ Основные карточки ═══ -->
    <div class="wpz-mini-cards">
        <div class="wpz-mini">
            <b><?php echo esc_html($stats['users']); ?></b><span>Пользователей</span>
        </div>
        <div class="wpz-mini wpz-green">
            <b id="wpz-online"><?php echo esc_html($online); ?></b><span>Онлайн (15 мин)</span>
        </div>
        <div class="wpz-mini">
            <b><?php echo esc_html($stats['codes_active']); ?></b><span>Активных QR</span>
        </div>
        <div class="wpz-mini">
            <b><?php echo esc_html($stats['raffles_total']); ?></b><span>Розыгрышей</span>
        </div>
        <div class="wpz-mini">
            <b><?php echo esc_html($stats['bottles_total']); ?></b><span>Бутылок</span>
        </div>
        <div class="wpz-mini">
            <b><?php echo esc_html($stats['orders_pending']); ?></b><span>Заказов</span>
        </div>
    </div>

    <!-- ═══ Финансы ═══ -->
    <div class="wpz-mini-cards">
        <div class="wpz-mini wpz-gold">
            <b><?php echo esc_html($balance['total']); ?></b><span>Общий баланс</span>
        </div>
        <div class="wpz-mini">
            <b><?php echo esc_html(round($balance['avg'], 1)); ?></b><span>Средний баланс</span>
        </div>
        <div class="wpz-mini">
            <b><?php echo esc_html($balance['active']); ?></b><span>С балансом &gt; 0</span>
        </div>
        <div class="wpz-mini">
            <b><?php echo esc_html($points['total']); ?></b><span>Всего начислено</span>
        </div>
        <div class="wpz-mini">
            <b><?php echo esc_html($notif_stats['total']); ?></b><span>Уведомлений</span>
        </div>
        <div class="wpz-mini wpz-red">
            <b><?php echo esc_html($notif_stats['unread']); ?></b><span>Непрочитанных</span>
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
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpz-users&user=' . (int)$u['id'])); ?>"
                                       style="text-decoration:none;color:inherit;" title="Открыть карточку пользователя">
                                        <?php echo esc_html($u['name'] ?: '—'); ?>
                                    </a>
                                </td>
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
