<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1>📦 Заказы</h1>

    <div class="wpz-card">
        <h2>Ожидающие заказы</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Пользователь</th>
                    <th>Приз</th>
                    <th>Дата</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="5">Нет ожидающих заказов</td></tr>
                <?php else: foreach ($orders as $o): ?>
                    <tr>
                        <td><?php echo esc_html($o['id']); ?></td>
                        <td><?php echo esc_html($o['name'] ?: '—'); ?> <code><?php echo esc_html($o['telegram_id']); ?></code><br><small><?php echo esc_html($o['phone'] ?? ''); ?></small></td>
                        <td><?php echo esc_html($o['prize_name'] ?? '—'); ?></td>
                        <td><?php echo $o['created_at'] ? date('Y-m-d H:i', strtotime($o['created_at'])) : '—'; ?></td>
                        <td><span class="wpz-badge wpz-yellow"><?php echo esc_html($o['status']); ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
