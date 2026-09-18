<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>🎁 Призы</h1>

    <!-- Add/Edit Form -->
    <div class="wpz-card" style="max-width:700px;">
        <h2><?php echo $edit_prize ? '✏️ Редактировать приз' : '➕ Новый приз'; ?></h2>
        <form method="post" action="<?php echo admin_url('admin.php?page=wpz-prizes'); ?>">
            <?php wp_nonce_field('wpz_action'); ?>
            <input type="hidden" name="action" value="<?php echo $edit_prize ? 'update_prize' : 'add_prize'; ?>">
            <?php if ($edit_prize): ?>
                <input type="hidden" name="prize_id" value="<?php echo esc_attr($edit_prize['id']); ?>">
            <?php endif; ?>

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
                    <th><label for="image_url">URL изображения</label></th>
                    <td>
                        <input type="url" id="image_url" name="image_url" class="regular-text"
                            value="<?php echo esc_attr($edit_prize['image_url'] ?? ''); ?>">
                        <?php if (!empty($edit_prize['image_url'])): ?>
                            <br><img src="<?php echo esc_url($edit_prize['image_url']); ?>" style="max-width:120px;margin-top:8px;border-radius:8px;">
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="price_points">Цена (баллы)</label></th>
                    <td><input type="number" id="price_points" name="price_points" min="1" required
                        value="<?php echo esc_attr($edit_prize['price_points'] ?? 100); ?>"></td>
                </tr>
                <tr>
                    <th><label for="category_id">Категория</label></th>
                    <td>
                        <select id="category_id" name="category_id" required>
                            <option value="">— Выберите —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo esc_attr($c['id']); ?>"
                                    <?php echo ($edit_prize['category_id'] ?? '') == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo esc_html($c['icon'] . ' ' . $c['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Активен</th>
                    <td>
                        <label>
                            <input type="checkbox" name="active" value="1"
                                <?php echo empty($edit_prize) || ($edit_prize['active'] ?? 1) ? 'checked' : ''; ?>>
                            Доступен для обмена
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo $edit_prize ? '💾 Сохранить' : '➕ Добавить'; ?>
                </button>
                <?php if ($edit_prize): ?>
                    <a href="<?php echo admin_url('admin.php?page=wpz-prizes'); ?>" class="button">Отмена</a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <!-- Prizes List -->
    <div class="wpz-card">
        <h2>Все призы (<?php echo count($prizes); ?>)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px">ID</th>
                    <th style="width:60px">Фото</th>
                    <th>Название</th>
                    <th>Категория</th>
                    <th>Описание</th>
                    <th style="width:100px">Цена</th>
                    <th style="width:70px">Статус</th>
                    <th style="width:120px">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($prizes)): ?>
                    <tr><td colspan="8">Нет призов. Добавьте первый!</td></tr>
                <?php else: foreach ($prizes as $p): ?>
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
                        <td><?php echo esc_html(wp_trim_words($p['description'] ?: '—', 12)); ?></td>
                        <td><strong><?php echo esc_html($p['price_points']); ?> ₽</strong></td>
                        <td>
                            <?php if ($p['active']): ?>
                                <span style="color:#00a32a;">✅ Активен</span>
                            <?php else: ?>
                                <span style="color:#d63638;">⏸ Выкл</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=wpz-prizes&edit=' . $p['id']); ?>"
                               class="button button-small">✏️</a>
                            <form method="post" action="<?php echo admin_url('admin.php?page=wpz-prizes'); ?>" style="display:inline;">
                                <?php wp_nonce_field('wpz_action'); ?>
                                <input type="hidden" name="action" value="delete_prize">
                                <input type="hidden" name="prize_id" value="<?php echo esc_attr($p['id']); ?>">
                                <button type="submit" class="button button-small wpz-btn-danger"
                                    onclick="return confirm('Удалить приз «<?php echo esc_js($p['name']); ?>»?');">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
