<?php if (!defined('ABSPATH')) exit;
$tab = $tab ?? 'products';
$statuses = ['pending' => 'Ожидает', 'approved' => 'Одобрен', 'shipped' => 'Отправлен', 'completed' => 'Выполнен', 'cancelled' => 'Отменён'];
$status_colors = ['pending' => 'yellow', 'approved' => 'blue', 'shipped' => 'blue', 'completed' => 'green', 'cancelled' => 'red'];
?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>🛒 Магазин</h1>

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
        <a href="?page=wpz-shop&tab=products" class="nav-tab <?php echo $tab === 'products' ? 'nav-tab-active' : ''; ?>">Товары <span class="wpz-count"><?php echo count($shop_prizes); ?></span></a>
        <a href="?page=wpz-shop&tab=orders" class="nav-tab <?php echo $tab === 'orders' ? 'nav-tab-active' : ''; ?>">Заказы <span class="wpz-count"><?php echo count($shop_orders); ?></span></a>
    </nav>

    <?php if ($tab === 'products'): ?>
    <!-- ═══ TAB: Товары ═══ -->

    <?php if ($edit_prize): ?>
    <!-- Edit Form -->
    <div class="wpz-card" style="max-width:700px;">
        <h2>
            ✏️ Редактировать товар
            <a href="<?php echo admin_url('admin.php?page=wpz-shop&tab=products'); ?>" class="page-title-action">← Назад</a>
        </h2>
        <form method="post" action="<?php echo admin_url('admin.php?page=wpz-shop&tab=products'); ?>">
            <?php wp_nonce_field('wpz_action'); ?>
            <input type="hidden" name="action" value="update_shop_prize">
            <input type="hidden" name="prize_id" value="<?php echo esc_attr($edit_prize['id']); ?>">
            <input type="hidden" name="category_id" value="<?php echo esc_attr($shop_category_id); ?>">

            <table class="form-table">
                <tr>
                    <th><label for="name">Название</label></th>
                    <td><input type="text" id="name" name="name" class="regular-text" required
                        value="<?php echo esc_attr($edit_prize['name'] ?? ''); ?>"></td>
                </tr>
                <tr>
                    <th><label for="description">Описание</label></th>
                    <td><textarea id="description" name="description" class="large-text" rows="3"><?php echo esc_textarea($edit_prize['description'] ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="image_url">Изображение</label></th>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <input type="text" id="image_url" name="image_url" class="regular-text"
                                value="<?php echo esc_attr($edit_prize['image_url'] ?? ''); ?>">
                            <button type="button" class="button wpz-upload-btn" data-target="image_url">📁 Загрузить</button>
                        </div>
                        <?php if (!empty($edit_prize['image_url'])): ?>
                            <div style="margin-top:8px;"><img src="<?php echo esc_url($edit_prize['image_url']); ?>" style="max-width:120px;border-radius:8px;"></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="price_points">Цена (баллы)</label></th>
                    <td><input type="number" id="price_points" name="price_points" min="1" required
                        value="<?php echo esc_attr($edit_prize['price_points'] ?? 100); ?>"></td>
                </tr>
                <tr>
                    <th>Активен</th>
                    <td>
                        <label>
                            <input type="checkbox" name="active" value="1"
                                <?php echo ($edit_prize['active'] ?? 1) ? 'checked' : ''; ?>>
                            Доступен для обмена
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">💾 Сохранить</button>
                <a href="<?php echo admin_url('admin.php?page=wpz-shop&tab=products'); ?>" class="button">Отмена</a>
            </p>
        </form>
    </div>
    <?php endif; ?>

    <!-- Add Form -->
    <?php if (!$edit_prize): ?>
    <div class="wpz-card" style="max-width:700px;">
        <h2>➕ Новый товар</h2>
        <form method="post" action="<?php echo admin_url('admin.php?page=wpz-shop&tab=products'); ?>">
            <?php wp_nonce_field('wpz_action'); ?>
            <input type="hidden" name="action" value="add_shop_prize">

            <table class="form-table">
                <tr>
                    <th><label for="name">Название</label></th>
                    <td><input type="text" id="name" name="name" class="regular-text" required
                        value="" placeholder="Название товара"></td>
                </tr>
                <tr>
                    <th><label for="description">Описание</label></th>
                    <td><textarea id="description" name="description" class="large-text" rows="3" placeholder="Описание товара"></textarea></td>
                </tr>
                <tr>
                    <th><label for="image_url">Изображение</label></th>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <input type="text" id="image_url" name="image_url" class="regular-text" placeholder="https://...">
                            <button type="button" class="button wpz-upload-btn" data-target="image_url">📁 Загрузить</button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="price_points">Цена (баллы)</label></th>
                    <td><input type="number" id="price_points" name="price_points" min="1" required value="100"></td>
                </tr>
                <tr>
                    <th>Активен</th>
                    <td>
                        <label>
                            <input type="checkbox" name="active" value="1" checked>
                            Доступен для обмена
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">➕ Добавить</button>
            </p>
        </form>
    </div>
    <?php endif; ?>

    <!-- Products Table -->
    <div class="wpz-card">
        <div class="wpz-table-header">
            <h2>Товары магазина <span class="wpz-count"><?php echo count($shop_prizes); ?></span></h2>
            <div class="wpz-search-row">
                <form method="get" style="display:flex;gap:6px;align-items:center;">
                    <input type="hidden" name="page" value="wpz-shop">
                    <input type="hidden" name="tab" value="products">
                    <input type="search" name="search" placeholder="Поиск по названию..."
                           value="<?php echo esc_attr($search); ?>">
                    <select name="status">
                        <option value="">Все статусы</option>
                        <option value="active" <?php selected($filter_status, 'active'); ?>>✅ Активные</option>
                        <option value="inactive" <?php selected($filter_status, 'inactive'); ?>>⏸ Выключенные</option>
                    </select>
                    <button type="submit" class="button">🔍 Найти</button>
                    <?php if ($search || $filter_status): ?>
                        <a href="<?php echo admin_url('admin.php?page=wpz-shop&tab=products'); ?>" class="button">Сбросить</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <div class="wpz-table-wrap">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50px">ID</th>
                        <th style="width:60px">Фото</th>
                        <th>Название</th>
                        <th>Описание</th>
                        <th style="width:100px">Цена</th>
                        <th style="width:70px">Статус</th>
                        <th style="width:120px">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shop_prizes)): ?>
                        <tr><td colspan="7">
                            <?php if ($search || $filter_status): ?>
                                Ничего не найдено. <a href="<?php echo admin_url('admin.php?page=wpz-shop&tab=products'); ?>">Сбросить фильтры</a>
                            <?php else: ?>
                                Товаров пока нет. Добавьте первый товар выше!
                            <?php endif; ?>
                        </td></tr>
                    <?php else: foreach ($shop_prizes as $p): ?>
                        <tr>
                            <td><?php echo esc_html($p['id']); ?></td>
                            <td>
                                <?php if ($p['image_url']): ?>
                                    <img src="<?php echo esc_url($p['image_url']); ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;">
                                <?php else: ?>
                                    <span style="display:inline-block;width:40px;height:40px;background:#f0f0f0;border-radius:6px;line-height:40px;text-align:center;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($p['name']); ?></strong></td>
                            <td><?php echo esc_html(wp_trim_words($p['description'] ?: '—', 12)); ?></td>
                            <td><strong><?php echo esc_html($p['price_points']); ?> баллов</strong></td>
                            <td>
                                <?php if ($p['active']): ?>
                                    <span style="color:#00a32a;">✅ Активен</span>
                                <?php else: ?>
                                    <span style="color:#d63638;">⏸ Выкл</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=wpz-shop&tab=products&edit_prize=' . $p['id']); ?>"
                                   class="button button-small">✏️</a>
                                <form method="post" action="<?php echo admin_url('admin.php?page=wpz-shop&tab=products'); ?>" style="display:inline;">
                                    <?php wp_nonce_field('wpz_action'); ?>
                                    <input type="hidden" name="action" value="delete_shop_prize">
                                    <input type="hidden" name="prize_id" value="<?php echo esc_attr($p['id']); ?>">
                                    <button type="submit" class="button button-small wpz-btn-danger"
                                        onclick="return confirm('Удалить товар «<?php echo esc_js($p['name']); ?>»?');">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($tab === 'orders'): ?>
    <!-- ═══ TAB: Заказы ═══ -->

    <div class="wpz-card">
        <div class="wpz-table-header">
            <h2>Заказы на товары магазина <span class="wpz-count"><?php echo count($shop_orders); ?></span></h2>
        </div>
        <div class="wpz-table-wrap">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50px">ID</th>
                        <th>Пользователь</th>
                        <th>Товар</th>
                        <th style="width:140px">Дата</th>
                        <th style="width:100px">Статус</th>
                        <th style="width:160px">Действие</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shop_orders)): ?>
                        <tr><td colspan="6">Нет заказов на товары магазина</td></tr>
                    <?php else: foreach ($shop_orders as $o): ?>
                        <tr>
                            <td><?php echo esc_html($o['id']); ?></td>
                            <td>
                                <?php echo esc_html($o['name'] ?: '—'); ?>
                                <code><?php echo esc_html($o['telegram_id']); ?></code>
                                <br><small><?php echo esc_html($o['phone'] ?? ''); ?></small>
                            </td>
                            <td><strong><?php echo esc_html($o['prize_name'] ?? '—'); ?></strong></td>
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

    <?php endif; ?>
</div>

<script>
(function(){
    var targetField = null;
    jQuery(document).on('click', '.wpz-upload-btn', function(e){
        e.preventDefault();
        targetField = jQuery('#' + jQuery(this).data('target'));
        var frame = wp.media({
            title: 'Выберите изображение',
            multiple: false,
            library: { type: 'image' }
        });
        frame.on('select', function(){
            var url = frame.state().get('selection').first().toJSON().url;
            targetField.val(url);
        });
        frame.open();
    });
})();
</script>
