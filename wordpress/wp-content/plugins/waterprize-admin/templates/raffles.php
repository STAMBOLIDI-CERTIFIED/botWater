<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>🎰 Розыгрыши</h1>

    <div class="wpz-card">
        <h2>История розыгрышей</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Дата</th>
                    <th>Победитель</th>
                    <th>Код</th>
                    <th>Приз</th>
                    <th>Статус</th>
                    <th>Выплата</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($raffles)): ?>
                    <tr><td colspan="7">Нет розыгрышей</td></tr>
                <?php else: foreach ($raffles as $r): ?>
                    <tr>
                        <td><?php echo esc_html($r['id']); ?></td>
                        <td><?php echo $r['created_at'] ? date('Y-m-d H:i', strtotime($r['created_at'])) : '—'; ?></td>
                        <td><?php echo esc_html($r['winner_name'] ?: '—'); ?></td>
                        <td><code><?php echo esc_html($r['winning_code'] ?: '—'); ?></code></td>
                        <td><?php echo esc_html($r['prize_amount']); ?> руб.</td>
                        <td><span class="wpz-badge wpz-<?php echo $r['status'] === 'completed' ? 'green' : 'yellow'; ?>"><?php echo esc_html($r['status']); ?></span></td>
                        <td><?php echo $r['payout_status'] ? '<span class="wpz-badge wpz-blue">' . esc_html($r['payout_status']) . '</span>' : '—'; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
