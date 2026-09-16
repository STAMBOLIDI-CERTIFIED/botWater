<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1>💧 WaterPrize — Дашборд</h1>
    <?php if (!WaterPrize_DB::instance()->is_connected()): ?>
        <div class="notice notice-error"><p>⚠️ Нет подключения к PostgreSQL. Настройте подключение в <a href="<?php echo admin_url('admin.php?page=wpz-settings'); ?>">Настройках БД</a>.</p></div>
    <?php endif; ?>
    <div class="wpz-stats-grid">
        <div class="wpz-stat-card wpz-blue">
            <div class="wpz-stat-icon">👥</div>
            <div class="wpz-stat-val"><?php echo esc_html($stats['users']); ?></div>
            <div class="wpz-stat-label">Пользователей</div>
        </div>
        <div class="wpz-stat-card wpz-cyan">
            <div class="wpz-stat-icon">📱</div>
            <div class="wpz-stat-val"><?php echo esc_html($stats['codes_active']); ?></div>
            <div class="wpz-stat-label">Активных QR-кодов</div>
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
            <div class="wpz-stat-label">Ожидающих заказов</div>
        </div>
        <div class="wpz-stat-card wpz-red">
            <div class="wpz-stat-icon">📱</div>
            <div class="wpz-stat-val"><?php echo esc_html($stats['codes_total']); ?></div>
            <div class="wpz-stat-label">Всего QR-кодов</div>
        </div>
    </div>
</div>
