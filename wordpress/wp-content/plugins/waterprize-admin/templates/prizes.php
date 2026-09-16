<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1>🎁 Призы</h1>

    <div class="wpz-card">
        <h2>Все призы</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Категория</th>
                    <th>Описание</th>
                    <th>Цена (баллы)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($prizes)): ?>
                    <tr><td colspan="5">Нет призов</td></tr>
                <?php else: foreach ($prizes as $p): ?>
                    <tr>
                        <td><?php echo esc_html($p['id']); ?></td>
                        <td><strong><?php echo esc_html($p['name']); ?></strong></td>
                        <td>
                            <?php
                            $cat_name = '';
                            foreach ($categories as $c) {
                                if ($c['id'] == $p['category_id']) {
                                    $cat_name = $c['icon'] . ' ' . $c['title'];
                                    break;
                                }
                            }
                            echo esc_html($cat_name ?: '—');
                            ?>
                        </td>
                        <td><?php echo esc_html($p['description'] ?: '—'); ?></td>
                        <td><strong><?php echo esc_html($p['price_points']); ?></strong></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
