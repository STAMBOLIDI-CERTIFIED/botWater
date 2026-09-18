<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>🔐 Администраторы</h1>

    <div class="wpz-card">
        <h2>Добавить администратора</h2>
        <form method="post" class="wpz-inline-form">
            <?php wp_nonce_field('wpz_action'); ?>
            <table class="wpz-form-table">
                <tr>
                    <td><label>Telegram ID</label></td>
                    <td><input type="number" name="telegram_id" required class="regular-text"></td>
                    <td><label>Имя</label></td>
                    <td><input type="text" name="name" class="regular-text"></td>
                    <td><button type="submit" name="action" value="add_admin" class="button button-primary">➕ Добавить</button></td>
                </tr>
            </table>
        </form>
    </div>

    <div class="wpz-card">
        <h2>Список администраторов</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Telegram ID</th>
                    <th>Имя</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($admins)): ?>
                    <tr><td colspan="4">Нет администраторов</td></tr>
                <?php else: foreach ($admins as $a): ?>
                    <tr>
                        <td><?php echo esc_html($a['id']); ?></td>
                        <td><code><?php echo esc_html($a['telegram_id']); ?></code></td>
                        <td><?php echo esc_html($a['name'] ?: '—'); ?></td>
                        <td>
                            <form method="post" style="display:inline">
                                <?php wp_nonce_field('wpz_action'); ?>
                                <input type="hidden" name="telegram_id" value="<?php echo esc_attr($a['telegram_id']); ?>">
                                <button type="submit" name="action" value="remove_admin" class="button button-secondary button-small" onclick="return confirm('Удалить?')">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
