<?php if (!defined('ABSPATH')) exit;
$statuses = ['pending' => 'Ожидает', 'approved' => 'Одобрен', 'shipped' => 'Отправлен', 'completed' => 'Выполнен', 'cancelled' => 'Отменён'];
$status_colors = ['pending' => 'yellow', 'approved' => 'blue', 'shipped' => 'blue', 'completed' => 'green', 'cancelled' => 'red'];
?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>📦 Заказы <span class="wpz-count"><?php echo esc_html(count($orders)); ?></span></h1>

    <div class="wpz-card">
        <h2>Все заказы</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Пользователь</th>
                    <th>Приз</th>
                    <th>Дата</th>
                    <th>Статус</th>
                    <th>Действие</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="6">Нет заказов</td></tr>
                <?php else: foreach ($orders as $o): ?>
                    <tr>
                        <td><?php echo esc_html($o['id']); ?></td>
                        <td>
                            <?php echo esc_html($o['name'] ?: '—'); ?>
                            <code><?php echo esc_html($o['telegram_id']); ?></code>
                            <br><small><?php echo esc_html($o['phone'] ?? ''); ?></small>
                        </td>
                        <td><?php echo esc_html($o['prize_name'] ?? '—'); ?></td>
                        <td><?php echo $o['created_at'] ? date('Y-m-d H:i', strtotime($o['created_at'])) : '—'; ?></td>
                        <td>
                            <?php $color = $status_colors[$o['status']] ?? 'gray'; ?>
                            <span class="wpz-badge wpz-<?php echo $color; ?>">
                                <?php echo esc_html($statuses[$o['status']] ?? $o['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($o['status'] === 'pending'): ?>
                                <form method="post" style="display:inline">
                                    <?php wp_nonce_field('wpz_action'); ?>
                                    <input type="hidden" name="action" value="update_order_status">
                                    <input type="hidden" name="order_id" value="<?php echo esc_attr($o['id']); ?>">
                                    <button type="submit" name="new_status" value="approved" class="button button-small">✅ Одобрить</button>
                                </form>
                                <form method="post" style="display:inline">
                                    <?php wp_nonce_field('wpz_action'); ?>
                                    <input type="hidden" name="action" value="update_order_status">
                                    <input type="hidden" name="order_id" value="<?php echo esc_attr($o['id']); ?>">
                                    <button type="submit" name="new_status" value="cancelled" class="button button-small" onclick="return confirm('Отменить заказ?')">❌ Отменить</button>
                                </form>
                            <?php elseif ($o['status'] === 'approved'): ?>
                                <form method="post" style="display:inline">
                                    <?php wp_nonce_field('wpz_action'); ?>
                                    <input type="hidden" name="action" value="update_order_status">
                                    <input type="hidden" name="order_id" value="<?php echo esc_attr($o['id']); ?>">
                                    <button type="submit" name="new_status" value="shipped" class="button button-small">📦 Отправлен</button>
                                </form>
                            <?php elseif ($o['status'] === 'shipped'): ?>
                                <form method="post" style="display:inline">
                                    <?php wp_nonce_field('wpz_action'); ?>
                                    <input type="hidden" name="action" value="update_order_status">
                                    <input type="hidden" name="order_id" value="<?php echo esc_attr($o['id']); ?>">
                                    <button type="submit" name="new_status" value="completed" class="button button-small">✅ Выполнен</button>
                                </form>
                            <?php else: ?>
                                <span class="wpz-badge wpz-gray">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
