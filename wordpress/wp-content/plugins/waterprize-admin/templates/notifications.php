<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>🔔 Уведомления <span class="wpz-count"><?php echo esc_html($total); ?></span></h1>

    <div class="wpz-card">
        <h2>Все уведомления</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Пользователь</th>
                    <th>Тип</th>
                    <th>Заголовок</th>
                    <th>Текст</th>
                    <th>Прочитано</th>
                    <th>Дата</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($notifications)): ?>
                    <tr><td colspan="7">Нет уведомлений</td></tr>
                <?php else: foreach ($notifications as $n): ?>
                    <tr>
                        <td><?php echo esc_html($n['id']); ?></td>
                        <td><?php echo esc_html($n['name'] ?: '—'); ?> <code><?php echo esc_html($n['telegram_id']); ?></code></td>
                        <td><span class="wpz-badge wpz-blue"><?php echo esc_html($n['type'] ?: '—'); ?></span></td>
                        <td><strong><?php echo esc_html($n['title'] ?: '—'); ?></strong></td>
                        <td><?php echo esc_html(mb_strimwidth($n['body'] ?: '', 0, 80, '...')); ?></td>
                        <td><?php echo $n['read'] ? '✅' : '❌'; ?></td>
                        <td><?php echo $n['created_at'] ? date('Y-m-d H:i', strtotime($n['created_at'])) : '—'; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
