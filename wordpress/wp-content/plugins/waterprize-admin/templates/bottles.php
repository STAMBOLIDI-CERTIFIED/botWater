<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1>🍾 Бутылки <span class="wpz-count"><?php echo esc_html($total); ?></span></h1>

    <div class="wpz-card">
        <h2>Генерация бутылок</h2>
        <form method="post" class="wpz-inline-form">
            <?php wp_nonce_field('wpz_action'); ?>
            <table class="wpz-form-table">
                <tr>
                    <td><label>Кол-во</label></td>
                    <td><input type="number" name="count" value="100" min="1" max="10000" class="regular-text"></td>
                    <td><label>Партия</label></td>
                    <td><input type="text" name="batch" required placeholder="01" class="regular-text"></td>
                    <td><label>Год</label></td>
                    <td><input type="text" name="year" value="2025" required class="regular-text"></td>
                    <td><button type="submit" name="action" value="generate_bottles" class="button button-primary">⚡ Сгенерировать</button></td>
                </tr>
            </table>
        </form>
    </div>

    <div class="wpz-card">
        <h2>Партии</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Год</th><th>Партия</th><th>Количество</th></tr></thead>
            <tbody>
                <?php if (empty($batches)): ?>
                    <tr><td colspan="3">Нет партий</td></tr>
                <?php else: foreach ($batches as $b): ?>
                    <tr>
                        <td><?php echo esc_html($b['year']); ?></td>
                        <td><?php echo esc_html($b['batch']); ?></td>
                        <td><strong><?php echo esc_html($b['count']); ?></strong></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="wpz-card">
        <h2>Бутылки</h2>
        <form method="get" class="wpz-search-form">
            <input type="hidden" name="page" value="wpz-bottles">
            <input type="search" name="search" placeholder="Поиск по ID, партии, году..." value="<?php echo esc_attr($search); ?>" class="regular-text">
            <button type="submit" class="button button-primary">🔍 Поиск</button>
        </form>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID бутылки</th>
                    <th>Партия</th>
                    <th>Год</th>
                    <th>Назначена</th>
                    <th>Дата</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bottles)): ?>
                    <tr><td colspan="5">Нет бутылок</td></tr>
                <?php else: foreach ($bottles as $b): ?>
                    <tr>
                        <td><code><?php echo esc_html($b['bottle_id']); ?></code></td>
                        <td><?php echo esc_html($b['batch'] ?: '—'); ?></td>
                        <td><?php echo esc_html($b['year'] ?: '—'); ?></td>
                        <td><?php echo $b['assigned_to'] ? '✅ ' . esc_html($b['assigned_to']) : '🔓'; ?></td>
                        <td><?php echo $b['created_at'] ? date('Y-m-d', strtotime($b['created_at'])) : '—'; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
