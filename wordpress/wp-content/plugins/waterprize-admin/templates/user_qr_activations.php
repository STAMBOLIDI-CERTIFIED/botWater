<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>📲 Активации QR-кодов <span class="wpz-count"><?php echo esc_html($total); ?></span></h1>

    <div class="wpz-card">
        <h2>История активаций</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Пользователь</th>
                    <th>Telegram ID</th>
                    <th>QR-код</th>
                    <th>Дата активации</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activations)): ?>
                    <tr><td colspan="5">Нет активаций</td></tr>
                <?php else: foreach ($activations as $a): ?>
                    <tr>
                        <td><?php echo esc_html($a['id']); ?></td>
                        <td><?php echo esc_html($a['name'] ?: '—'); ?></td>
                        <td><code><?php echo esc_html($a['telegram_id']); ?></code></td>
                        <td><code><?php echo esc_html($a['qr_code']); ?></code></td>
                        <td><?php echo $a['activated_at'] ? date('Y-m-d H:i', strtotime($a['activated_at'])) : '—'; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
