<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>🔑 Админ-коды <span class="wpz-count"><?php echo esc_html($total); ?></span></h1>

    <div class="wpz-card">
        <h2>Все админ-коды</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Код</th>
                    <th>Telegram ID</th>
                    <th>Использован</th>
                    <th>Срок действия</th>
                    <th>Создан</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($admin_codes)): ?>
                    <tr><td colspan="6">Нет админ-кодов</td></tr>
                <?php else: foreach ($admin_codes as $ac): ?>
                    <tr>
                        <td><?php echo esc_html($ac['id']); ?></td>
                        <td><code><?php echo esc_html($ac['code']); ?></code></td>
                        <td><?php echo $ac['telegram_id'] ? '<code>' . esc_html($ac['telegram_id']) . '</code>' : '—'; ?></td>
                        <td><?php echo $ac['used'] ? '✅' : '❌'; ?></td>
                        <td><?php echo $ac['expires_at'] ? date('Y-m-d H:i', strtotime($ac['expires_at'])) : '—'; ?></td>
                        <td><?php echo $ac['created_at'] ? date('Y-m-d H:i', strtotime($ac['created_at'])) : '—'; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
