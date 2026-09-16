<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1>📱 QR-коды <span class="wpz-count"><?php echo esc_html($stats['total']); ?></span></h1>

    <div class="wpz-stats-row">
        <span class="wpz-badge wpz-green">Активных: <?php echo esc_html($stats['active']); ?></span>
        <span class="wpz-badge wpz-gray">Всего: <?php echo esc_html($stats['total']); ?></span>
    </div>

    <div class="wpz-card">
        <h2>Добавить коды</h2>
        <form method="post">
            <?php wp_nonce_field('wpz_action'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="batch">Партия</label></th>
                    <td><input type="text" id="batch" name="batch" class="regular-text" placeholder="batch_20250101"></td>
                </tr>
                <tr>
                    <th><label for="codes">Коды (каждый с новой строки)</label></th>
                    <td><textarea id="codes" name="codes" class="large-text" rows="8" required placeholder="Код1&#10;Код2&#10;Код3"></textarea></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="action" value="add_codes" class="button button-primary">➕ Добавить коды</button>
            </p>
        </form>
    </div>

    <div class="wpz-card">
        <h2>Все коды</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Код</th>
                    <th>Партия</th>
                    <th>Статус</th>
                    <th>Победитель</th>
                    <th>Создан</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($codes)): ?>
                    <tr><td colspan="6">Нет кодов</td></tr>
                <?php else: foreach ($codes as $c): ?>
                    <tr>
                        <td><?php echo esc_html($c['id']); ?></td>
                        <td><code><?php echo esc_html($c['code']); ?></code></td>
                        <td><?php echo esc_html($c['batch'] ?: '—'); ?></td>
                        <td>
                            <?php
                            $cls = match($c['status']) { 'active' => 'wpz-green', 'won' => 'wpz-blue', default => 'wpz-red' };
                            ?>
                            <span class="wpz-badge <?php echo $cls; ?>"><?php echo esc_html($c['status']); ?></span>
                        </td>
                        <td><?php echo esc_html($c['winner_id'] ?: '—'); ?></td>
                        <td><?php echo $c['created_at'] ? date('Y-m-d H:i', strtotime($c['created_at'])) : '—'; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
