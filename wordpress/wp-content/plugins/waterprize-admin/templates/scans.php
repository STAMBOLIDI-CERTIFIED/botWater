<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>🔍 Сканирования <span class="wpz-count"><?php echo esc_html($total); ?></span></h1>

    <div class="wpz-card">
        <h2>История сканирований QR-кодов</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Пользователь</th>
                    <th>Telegram ID</th>
                    <th>Код</th>
                    <th>Дата</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($scans)): ?>
                    <tr><td colspan="5">Нет сканирований</td></tr>
                <?php else: foreach ($scans as $s): ?>
                    <tr>
                        <td><?php echo esc_html($s['id']); ?></td>
                        <td><?php echo esc_html($s['name'] ?: '—'); ?></td>
                        <td><code><?php echo esc_html($s['telegram_id']); ?></code></td>
                        <td><code><?php echo esc_html($s['code'] ?: '—'); ?></code></td>
                        <td><?php echo $s['scanned_at'] ? date('Y-m-d H:i', strtotime($s['scanned_at'])) : '—'; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
