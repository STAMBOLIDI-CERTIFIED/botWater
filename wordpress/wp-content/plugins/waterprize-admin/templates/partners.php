<?php if (!defined('ABSPATH')) exit;
$tab = $tab ?? 'list';
?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>🤝 Партнёры</h1>

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
        <a href="?page=wpz-partners&tab=list" class="nav-tab <?php echo $tab === 'list' ? 'nav-tab-active' : ''; ?>">Партнёры</a>
        <a href="?page=wpz-partners&tab=prizes" class="nav-tab <?php echo $tab === 'prizes' ? 'nav-tab-active' : ''; ?>">Призы</a>
        <a href="?page=wpz-partners&tab=stats" class="nav-tab <?php echo $tab === 'stats' ? 'nav-tab-active' : ''; ?>">Статистика</a>
    </nav>

    <?php if ($tab === 'list'): ?>
    <!-- ═══ TAB: Партнёры ═══ -->

    <?php if ($edit_category): ?>
    <!-- Карточка партнёра -->
    <div class="wpz-card wpz-bottle-detail">
        <h2>
            Партнёр <strong><?php echo esc_html($edit_category['title']); ?></strong>
            <a href="<?php echo admin_url('admin.php?page=wpz-partners&tab=list'); ?>" class="page-title-action">← Назад</a>
        </h2>
        <div class="wpz-bottle-grid">
            <div class="wpz-bottle-info">
                <table class="form-table">
                    <tr><th>ID</th><td><?php echo esc_html($edit_category['id']); ?></td></tr>
                    <tr><th>Название</th><td><strong><?php echo esc_html($edit_category['title']); ?></strong></td></tr>
                    <tr><th>Подзаголовок</th><td><?php echo esc_html($edit_category['subtitle'] ?: '—'); ?></td></tr>
                    <tr><th>Описание</th><td><?php echo esc_html($edit_category['description'] ?: '—'); ?></td></tr>
                    <tr><th>Иконка</th><td style="font-size:24px;"><?php echo esc_html($edit_category['icon']); ?></td></tr>
                    <tr><th>Цвет</th><td>
                        <span style="display:inline-block;width:24px;height:24px;border-radius:4px;background:<?php echo esc_attr($edit_category['color']); ?>;vertical-align:middle;"></span>
                        <code><?php echo esc_html($edit_category['color']); ?></code>
                    </td></tr>
                    <tr><th>Баллы за скан</th><td><strong><?php echo esc_html($edit_category['scan_points'] ?? 10); ?></strong> баллов</td></tr>
                    <?php if (!empty($edit_category['logo_url'])): ?>
                        <tr><th>Логотип</th><td><img src="<?php echo esc_url($edit_category['logo_url']); ?>" style="max-width:80px;max-height:80px;border-radius:8px;object-fit:cover;"></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($edit_category['image_url'])): ?>
                        <tr><th>Обложка</th><td><img src="<?php echo esc_url($edit_category['image_url']); ?>" style="max-width:200px;max-height:100px;border-radius:8px;object-fit:cover;"></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($edit_category['website'])): ?>
                        <tr><th>Сайт</th><td><a href="<?php echo esc_url($edit_category['website']); ?>" target="_blank"><?php echo esc_html($edit_category['website']); ?></a></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($edit_category['telegram'])): ?>
                        <tr><th>Telegram</th><td><a href="<?php echo esc_url($edit_category['telegram']); ?>" target="_blank"><?php echo esc_html($edit_category['telegram']); ?></a></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($edit_category['info'])): ?>
                        <tr><th>Доп. информация</th><td><?php echo nl2br(esc_html($edit_category['info'])); ?></td></tr>
                    <?php endif; ?>
                    <tr><th>Статус</th><td>
                        <?php if ($edit_category['is_active']): ?>
                            <span style="color:#00a32a;">✅ Активен</span>
                        <?php else: ?>
                            <span style="color:#d63638;">⏸ Выключен</span>
                        <?php endif; ?>
                    </td></tr>
                    <tr><th>QR-код</th><td><code style="font-size:14px;background:#f0f0f0;padding:4px 8px;border-radius:4px;"><?php echo esc_html($edit_category['qr_code'] ?: '—'); ?></code></td></tr>
                </table>
            </div>
            <div class="wpz-bottle-qr">
                <div id="wpz-qr-code"></div>
                <p class="description" style="text-align:center;margin-top:8px;">QR-код партнёра</p>
                <?php if (!empty($edit_category['qr_code'])): ?>
                    <?php $bot_username = $db->get_setting('bot_username') ?: 'WaterPrizeBot'; ?>
                    <?php $deep_link = 'https://t.me/' . esc_attr($bot_username) . '?start=' . $edit_category['qr_code']; ?>
                    <div style="margin-top:10px;display:flex;gap:6px;justify-content:center;">
                        <a href="https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=<?php echo urlencode($deep_link); ?>"
                           download="partner-<?php echo esc_attr($edit_category['id']); ?>-qr.png"
                           class="button button-small">⬇️ Скачать</a>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('wpz_action'); ?>
                            <input type="hidden" name="action" value="regenerate_qr">
                            <input type="hidden" name="category_id" value="<?php echo esc_attr($edit_category['id']); ?>">
                            <button type="submit" class="button button-small"
                                    onclick="return confirm('Сгенерировать новый QR-код? Старый перестанет работать.');">🔄 Новый QR</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Призы этого партнёра -->
    <?php
    $partner_prizes = array_filter($prizes, fn($p) => $p['category_id'] == $edit_category['id']);
    ?>
    <div class="wpz-card">
        <h2>🎁 Призы партнёра «<?php echo esc_html($edit_category['title']); ?>» (<?php echo count($partner_prizes); ?>)</h2>
        <?php if (empty($partner_prizes)): ?>
            <p style="color:#666;">У этого партнёра пока нет призов.</p>
        <?php else: ?>
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
                <?php foreach ($partner_prizes as $p): ?>
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
                    <td><strong><?php echo esc_html($p['price_points']); ?> ₽</strong></td>
                    <td>
                        <?php if ($p['active']): ?>
                            <span style="color:#00a32a;">✅ Активен</span>
                        <?php else: ?>
                            <span style="color:#d63638;">⏸ Выкл</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=wpz-partners&tab=list&edit=' . $edit_category['id'] . '&edit_prize=' . $p['id']); ?>"
                           class="button button-small">✏️</a>
                        <form method="post" action="<?php echo admin_url('admin.php?page=wpz-partners&tab=list&edit=' . $edit_category['id']); ?>" style="display:inline;">
                            <?php wp_nonce_field('wpz_action'); ?>
                            <input type="hidden" name="action" value="delete_prize">
                            <input type="hidden" name="prize_id" value="<?php echo esc_attr($p['id']); ?>">
                            <button type="submit" class="button button-small wpz-btn-danger"
                                onclick="return confirm('Удалить приз «<?php echo esc_js($p['name']); ?>»?');">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Add/Edit Form -->
    <div class="wpz-card" style="max-width:700px;">
        <h2><?php echo $edit_category ? '✏️ Редактировать партнёра' : '➕ Новый партнёр'; ?></h2>
        <form method="post" action="<?php echo admin_url('admin.php?page=wpz-partners&tab=list'); ?>">
            <?php wp_nonce_field('wpz_action'); ?>
            <input type="hidden" name="action" value="<?php echo $edit_category ? 'update_category' : 'add_category'; ?>">
            <?php if ($edit_category): ?>
                <input type="hidden" name="category_id" value="<?php echo esc_attr($edit_category['id']); ?>">
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th><label for="title">Название</label></th>
                    <td><input type="text" id="title" name="title" class="regular-text" required
                               value="<?php echo esc_attr($edit_category['title'] ?? ''); ?>"
                               placeholder="Space, Badmintonist, Истокъ..."></td>
                </tr>
                <tr>
                    <th><label for="subtitle">Подзаголовок</label></th>
                    <td><input type="text" id="subtitle" name="subtitle" class="regular-text"
                               value="<?php echo esc_attr($edit_category['subtitle'] ?? ''); ?>"
                               placeholder="Партнёрские призы"></td>
                </tr>
                <tr>
                    <th><label for="description">Описание</label></th>
                    <td><textarea id="description" name="description" class="large-text" rows="3"
                                  placeholder="Краткое описание партнёра"><?php echo esc_textarea($edit_category['description'] ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="icon">Иконка (эмодзи)</label></th>
                    <td><input type="text" id="icon" name="icon" style="width:60px;" required
                               value="<?php echo esc_attr($edit_category['icon'] ?? ''); ?>"
                               placeholder="🚀">
                        <span class="description">Например: 🚀 🏸 💧 ❤️ 🎾</span>
                    </td>
                </tr>
                <tr>
                    <th><label for="color">Цвет</label></th>
                    <td><input type="color" id="color" name="color" required
                               value="<?php echo esc_attr($edit_category['color'] ?? '#2271b1'); ?>"></td>
                </tr>
                <tr>
                    <th><label for="sort_order">Порядок</label></th>
                    <td><input type="number" id="sort_order" name="sort_order" min="0" style="width:80px;"
                               value="<?php echo esc_attr($edit_category['sort_order'] ?? 0); ?>"></td>
                </tr>
                <tr>
                    <th>Активен</th>
                    <td>
                        <label>
                            <input type="checkbox" name="is_active" value="1"
                                    <?php echo empty($edit_category) || ($edit_category['is_active'] ?? true) ? 'checked' : ''; ?>>
                            Отображается в приложении
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="scan_points">Баллы за сканирование</label></th>
                    <td>
                        <input type="number" id="scan_points" name="scan_points" min="0" style="width:120px;"
                               value="<?php echo esc_attr($edit_category['scan_points'] ?? 10); ?>">
                        <span class="description">баллов получает пользователь при первом сканировании QR</span>
                    </td>
                </tr>
                <tr>
                    <th><label for="logo_url">Логотип</label></th>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <input type="text" id="logo_url" name="logo_url" class="regular-text"
                                   value="<?php echo esc_attr($edit_category['logo_url'] ?? ''); ?>"
                                   placeholder="URL логотипа">
                            <button type="button" class="button wpz-upload-btn" data-target="logo_url">📁 Загрузить</button>
                        </div>
                        <?php if (!empty($edit_category['logo_url'])): ?>
                            <div style="margin-top:8px;"><img src="<?php echo esc_url($edit_category['logo_url']); ?>" style="max-width:80px;max-height:80px;border-radius:8px;object-fit:cover;"></div>
                        <?php endif; ?>
                        <span class="description">Логотип партнёра (отображается в каталоге)</span>
                    </td>
                </tr>
                <tr>
                    <th><label for="image_url">Обложка</label></th>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <input type="text" id="image_url" name="image_url" class="regular-text"
                                   value="<?php echo esc_attr($edit_category['image_url'] ?? ''); ?>"
                                   placeholder="URL обложки">
                            <button type="button" class="button wpz-upload-btn" data-target="image_url">📁 Загрузить</button>
                        </div>
                        <?php if (!empty($edit_category['image_url'])): ?>
                            <div style="margin-top:8px;"><img src="<?php echo esc_url($edit_category['image_url']); ?>" style="max-width:200px;max-height:100px;border-radius:8px;object-fit:cover;"></div>
                        <?php endif; ?>
                        <span class="description">Баннер/обложка партнёра (отображается на странице партнёра)</span>
                    </td>
                </tr>
                <tr>
                    <th><label for="website">Сайт</label></th>
                    <td><input type="url" id="website" name="website" class="regular-text"
                               value="<?php echo esc_attr($edit_category['website'] ?? ''); ?>"
                               placeholder="https://example.com"></td>
                </tr>
                <tr>
                    <th><label for="telegram">Telegram</label></th>
                    <td><input type="url" id="telegram" name="telegram" class="regular-text"
                               value="<?php echo esc_attr($edit_category['telegram'] ?? ''); ?>"
                               placeholder="https://t.me/channel"></td>
                </tr>
                <tr>
                    <th><label for="info">Доп. информация</label></th>
                    <td><textarea id="info" name="info" class="large-text" rows="4"
                                  placeholder="Адрес, режим работы, телефон и т.д."><?php echo esc_textarea($edit_category['info'] ?? ''); ?></textarea></td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo $edit_category ? '💾 Сохранить' : '➕ Добавить'; ?>
                </button>
                <?php if ($edit_category): ?>
                    <a href="<?php echo admin_url('admin.php?page=wpz-partners&tab=list'); ?>" class="button">Отмена</a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <!-- Categories List -->
    <div class="wpz-card">
        <div class="wpz-table-header">
            <h2>Все партнёры <span class="wpz-count"><?php echo esc_html(count($categories)); ?></span></h2>
        </div>
        <div class="wpz-table-wrap">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th style="width:40px">ID</th>
                    <th style="width:50px">Иконка</th>
                    <th>Название</th>
                    <th>Подзаголовок</th>
                    <th style="width:70px">Цвет</th>
                    <th style="width:80px">Баллы</th>
                    <th style="width:60px">Порядок</th>
                    <th style="width:70px">Статус</th>
                    <th style="width:140px">QR-код</th>
                    <th style="width:120px">Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="10">Нет партнёров. Добавьте первого!</td></tr>
                <?php else: foreach ($categories as $c): ?>
                    <tr>
                        <td><?php echo esc_html($c['id']); ?></td>
                        <td style="text-align:center;">
                            <?php if (!empty($c['logo_url'])): ?>
                                <img src="<?php echo esc_url($c['logo_url']); ?>" style="width:36px;height:36px;border-radius:8px;object-fit:cover;">
                            <?php else: ?>
                                <span style="font-size:24px;"><?php echo esc_html($c['icon']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo esc_html($c['title']); ?></strong></td>
                        <td><?php echo esc_html($c['subtitle'] ?: '—'); ?></td>
                        <td>
                            <span style="display:inline-block;width:24px;height:24px;border-radius:4px;background:<?php echo esc_attr($c['color']); ?>;vertical-align:middle;"></span>
                        </td>
                        <td><strong><?php echo esc_html($c['scan_points'] ?? 10); ?></strong></td>
                        <td><?php echo esc_html($c['sort_order']); ?></td>
                        <td>
                            <?php if ($c['is_active']): ?>
                                <span style="color:#00a32a;">✅ Вкл</span>
                            <?php else: ?>
                                <span style="color:#d63638;">⏸ Выкл</span>
                            <?php endif; ?>
                        </td>
                        <td class="wpz-qr-cell">
                            <?php if (!empty($c['qr_code'])): ?>
                                <?php $bot_un = $db->get_setting('bot_username') ?: 'WaterPrizeBot'; ?>
                                <?php $dl = 'https://t.me/' . $bot_un . '?start=' . $c['qr_code']; ?>
                                <a href="https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=<?php echo urlencode($dl); ?>"
                                   target="_blank" rel="noopener" title="Открыть QR в полном размере">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=70x70&data=<?php echo urlencode($dl); ?>"
                                         class="wpz-qr-thumb" alt="QR">
                                </a>
                                <div class="wpz-qr-actions">
                                    <a href="https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=<?php echo urlencode($dl); ?>"
                                       download="partner-<?php echo esc_attr($c['id']); ?>-qr.png" title="Скачать QR">⬇️</a>
                                    <button type="button" class="wpz-qr-copy-icon" data-link="<?php echo esc_attr($c['qr_code']); ?>" title="Скопировать код">🔗</button>
                                </div>
                            <?php else: ?>
                                <span class="description">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=wpz-partners&tab=list&edit=' . $c['id']); ?>"
                               class="button button-small">✏️</a>
                            <form method="post" action="<?php echo admin_url('admin.php?page=wpz-partners&tab=list'); ?>" style="display:inline;">
                                <?php wp_nonce_field('wpz_action'); ?>
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="category_id" value="<?php echo esc_attr($c['id']); ?>">
                                <button type="submit" class="button button-small wpz-btn-danger"
                                        onclick="return confirm('Удалить партнёра «<?php echo esc_js($c['title']); ?>»?');">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($tab === 'prizes'): ?>
    <!-- ═══ TAB: Призы ═══ -->

    <?php if ($edit_prize): ?>
    <div class="wpz-card" style="max-width:700px;">
        <h2>✏️ Редактировать приз</h2>
        <form method="post" action="<?php echo admin_url('admin.php?page=wpz-partners&tab=prizes'); ?>">
            <?php wp_nonce_field('wpz_action'); ?>
            <input type="hidden" name="action" value="update_prize">
            <input type="hidden" name="prize_id" value="<?php echo esc_attr($edit_prize['id']); ?>">

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
                        <div style="display:flex;align-items:center;gap:12px;">
                            <input type="url" id="image_url" name="image_url" class="regular-text"
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
                    <th><label for="category_id">Партнёр</label></th>
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
                <button type="submit" class="button button-primary">💾 Сохранить</button>
                <a href="<?php echo admin_url('admin.php?page=wpz-partners&tab=prizes'); ?>" class="button">Отмена</a>
            </p>
        </form>
    </div>
    <?php endif; ?>

    <!-- Add Prize Form -->
    <?php if (!$edit_prize): ?>
    <div class="wpz-card" style="max-width:700px;">
        <h2>➕ Новый приз</h2>
        <form method="post" action="<?php echo admin_url('admin.php?page=wpz-partners&tab=prizes'); ?>">
            <?php wp_nonce_field('wpz_action'); ?>
            <input type="hidden" name="action" value="add_prize">

            <table class="form-table">
                <tr>
                    <th><label for="name">Название</label></th>
                    <td><input type="text" id="name" name="name" class="regular-text" required
                        value="" placeholder="Название приза"></td>
                </tr>
                <tr>
                    <th><label for="description">Описание</label></th>
                    <td><textarea id="description" name="description" class="large-text" rows="3" placeholder="Описание приза"></textarea></td>
                </tr>
                <tr>
                    <th><label for="image_url">URL изображения</label></th>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <input type="url" id="image_url" name="image_url" class="regular-text" placeholder="https://...">
                            <button type="button" class="button wpz-upload-btn" data-target="image_url">📁 Загрузить</button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="price_points">Цена (баллы)</label></th>
                    <td><input type="number" id="price_points" name="price_points" min="1" required value="100"></td>
                </tr>
                <tr>
                    <th><label for="category_id">Партнёр</label></th>
                    <td>
                        <select id="category_id" name="category_id" required>
                            <option value="">— Выберите —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo esc_attr($c['id']); ?>">
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

    <!-- All Prizes List -->
    <div class="wpz-card">
        <div class="wpz-table-header">
            <h2>Все призы <span class="wpz-count"><?php echo count($prizes); ?></span></h2>
        </div>
        <div class="wpz-table-wrap">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50px">ID</th>
                        <th style="width:60px">Фото</th>
                        <th>Название</th>
                        <th>Партнёр</th>
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
                                <a href="<?php echo admin_url('admin.php?page=wpz-partners&tab=prizes&edit_prize=' . $p['id']); ?>"
                                   class="button button-small">✏️</a>
                                <form method="post" action="<?php echo admin_url('admin.php?page=wpz-partners&tab=prizes'); ?>" style="display:inline;">
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

    <?php elseif ($tab === 'stats'): ?>
    <!-- ═══ TAB: Статистика ═══ -->
    <?php
    $total_scans = 0;
    $total_unique = 0;
    $total_points = 0;
    $total_buyers = 0;
    foreach ($summary as $row) {
        $total_scans += $row['total_scans'];
        $total_unique += $row['unique_users'];
        $total_points += $row['total_points'];
        $total_buyers += $row['buyers_count'];
    }
    $overall_conversion = $total_unique > 0 ? round($total_buyers / $total_unique * 100, 1) : 0;
    ?>

    <!-- Summary Cards -->
    <div class="wpz-stats-grid" style="margin-bottom:20px;">
        <div class="wpz-stat-card wpz-blue">
            <div class="wpz-stat-icon">📡</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($total_scans); ?></div>
            <div class="wpz-stat-label">Всего сканирований</div>
        </div>
        <div class="wpz-stat-card wpz-green">
            <div class="wpz-stat-icon">👥</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($total_unique); ?></div>
            <div class="wpz-stat-label">Уникальных пользователей</div>
        </div>
        <div class="wpz-stat-card wpz-purple">
            <div class="wpz-stat-icon">⭐</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($total_points); ?></div>
            <div class="wpz-stat-label">Всего баллов начислено</div>
        </div>
        <div class="wpz-stat-card wpz-orange">
            <div class="wpz-stat-icon">🏪</div>
            <div class="wpz-stat-val"><?php echo count($summary); ?></div>
            <div class="wpz-stat-label">Партнёров</div>
        </div>
        <div class="wpz-stat-card wpz-red">
            <div class="wpz-stat-icon">🎯</div>
            <div class="wpz-stat-val"><?php echo $overall_conversion; ?>%</div>
            <div class="wpz-stat-label">Конверсия в покупку</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="wpz-card" style="margin-bottom:20px;">
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <label><strong>Партнёр:</strong></label>
            <select id="wpz-ps-partner">
                <option value="0">Все партнёры</option>
                <?php foreach ($summary as $row): ?>
                    <option value="<?php echo esc_attr($row['category_id']); ?>" <?php selected($category_id, $row['category_id']); ?>>
                        <?php echo esc_html($row['partner_name']); ?> (<?php echo $row['total_scans']; ?> сканов)
                    </option>
                <?php endforeach; ?>
            </select>

            <label><strong>Период:</strong></label>
            <div style="display:flex;gap:4px;">
                <button type="button" class="button wpz-ps-period <?php echo $period === 'hour' ? 'button-primary' : ''; ?>" data-period="hour">Час</button>
                <button type="button" class="button wpz-ps-period <?php echo $period === 'day' ? 'button-primary' : ''; ?>" data-period="day">День</button>
                <button type="button" class="button wpz-ps-period <?php echo $period === 'week' ? 'button-primary' : ''; ?>" data-period="week">Неделя</button>
                <button type="button" class="button wpz-ps-period <?php echo $period === 'month' ? 'button-primary' : ''; ?>" data-period="month">Месяц</button>
            </div>

            <label>С:</label>
            <input type="date" id="wpz-ps-from" value="<?php echo esc_attr($from); ?>">
            <label>По:</label>
            <input type="date" id="wpz-ps-to" value="<?php echo esc_attr($to); ?>">
            <button type="button" class="button button-primary" id="wpz-ps-apply">Применить</button>
        </div>
    </div>

    <!-- Chart -->
    <div class="wpz-card" style="margin-bottom:20px;">
        <h2>Динамика сканирований</h2>
        <div style="height:320px;position:relative;">
            <canvas id="wpz-ps-chart"></canvas>
        </div>
    </div>

    <!-- Per-partner table -->
    <div class="wpz-card" style="margin-bottom:20px;">
        <h2>Сводка по партнёрам</h2>
        <table class="widefat striped" style="margin-top:8px;">
            <thead>
                <tr>
                    <th>Партнёр</th>
                    <th>QR-код</th>
                    <th>Баллы за скан</th>
                    <th>Всего сканов</th>
                    <th>Уник. пользователей</th>
                    <th>Покупателей</th>
                    <th>Конверсия</th>
                    <th>Баллов начислено</th>
                    <th>Последний скан</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($summary)): ?>
                    <tr><td colspan="10">Нет данных</td></tr>
                <?php else: ?>
                    <?php foreach ($summary as $row): ?>
                        <tr style="<?php echo $row['category_id'] == $category_id ? 'background:#e8f5e9;' : ''; ?>">
                            <td><strong><?php echo esc_html($row['partner_name']); ?></strong></td>
                            <td><code style="font-size:11px;"><?php echo esc_html($row['qr_code']); ?></code></td>
                            <td><?php echo $row['scan_points']; ?></td>
                            <td><strong><?php echo number_format_i18n($row['total_scans']); ?></strong></td>
                            <td><?php echo number_format_i18n($row['unique_users']); ?></td>
                            <td><?php echo number_format_i18n($row['buyers_count']); ?></td>
                            <td><strong><?php echo $row['unique_users'] > 0 ? round($row['buyers_count'] / $row['unique_users'] * 100, 1) . '%' : '—'; ?></strong></td>
                            <td><?php echo number_format_i18n($row['total_points']); ?></td>
                            <td><?php echo $row['last_scan_at'] ? date_i18n('d.m.Y H:i', strtotime($row['last_scan_at'])) : '—'; ?></td>
                            <td>
                                <a href="?page=wpz-partners&tab=stats&partner_id=<?php echo $row['category_id']; ?>" class="button button-small">Детали</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Top Users who scan partners -->
    <div class="wpz-card" style="margin-bottom:20px;">
        <h2>Топ пользователей по сканированиям партнёров</h2>
        <table class="widefat striped" style="margin-top:8px;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Имя</th>
                    <th>Username</th>
                    <th>Telegram ID</th>
                    <th>Баланс</th>
                    <th>Всего сканов</th>
                    <th>Баллов получено</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($top_users)): ?>
                    <tr><td colspan="7">Нет данных</td></tr>
                <?php else: ?>
                    <?php foreach ($top_users as $i => $u): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo esc_html($u['user_name']); ?></strong></td>
                            <td><?php echo esc_html($u['username']); ?></td>
                            <td><code><?php echo $u['telegram_id']; ?></code></td>
                            <td><?php echo number_format_i18n($u['balance']); ?></td>
                            <td><strong><?php echo number_format_i18n($u['scans_count']); ?></strong></td>
                            <td><?php echo number_format_i18n($u['total_points']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Detail: per-user scans for selected partner -->
    <?php if ($category_id > 0 && !empty($detail)): ?>
    <div class="wpz-card" style="margin-bottom:20px;">
        <h2>Детали сканирований: <?php echo esc_html($summary[array_search($category_id, array_column($summary, 'category_id'))]['partner_name'] ?? ''); ?></h2>
        <table class="widefat striped" style="margin-top:8px;">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Пользователь</th>
                    <th>Username</th>
                    <th>Баланс</th>
                    <th>Уровень</th>
                    <th>Баллов за скан</th>
                    <th>QR-код</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detail as $row): ?>
                    <tr>
                        <td><?php echo date_i18n('d.m.Y H:i', strtotime($row['scanned_at'])); ?></td>
                        <td><strong><?php echo esc_html($row['name']); ?></strong></td>
                        <td><?php echo esc_html($row['username']); ?></td>
                        <td><?php echo number_format_i18n($row['balance']); ?></td>
                        <td><?php echo $row['tree_level']; ?></td>
                        <td><?php echo $row['points_earned']; ?></td>
                        <td><code style="font-size:11px;"><?php echo esc_html($row['qr_code']); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php if ($edit_category): ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var qrCode = <?php echo json_encode($edit_category['qr_code'] ?? ''); ?>;
    var botUsername = <?php echo json_encode($db->get_setting('bot_username') ?: 'WaterPrizeBot'); ?>;
    var deepLink = 'https://t.me/' + botUsername + '?start=' + qrCode;
    new QRCode(document.getElementById('wpz-qr-code'), {
        text: deepLink,
        width: 200,
        height: 200,
        colorDark: '#1e293b',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
});
</script>
<?php endif; ?>

<?php if ($tab === 'stats'): ?>
<script>
(function() {
    var chartData = <?php echo json_encode($chart_data); ?>;
    var currentPeriod = <?php echo json_encode($period); ?>;
    var currentPartner = <?php echo json_encode($category_id); ?>;

    function renderChart(labels, scans, points, users, conversion) {
        var ctx = document.getElementById('wpz-ps-chart');
        if (!ctx) return;
        if (!labels.length) {
            ctx.parentElement.innerHTML = '<p style="text-align:center;padding:40px 0;color:#666;">Нет данных за выбранный период</p>';
            return;
        }

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Сканирования',
                        data: scans,
                        backgroundColor: 'rgba(34,113,177,0.7)',
                        borderColor: 'rgba(34,113,177,1)',
                        borderWidth: 1,
                        yAxisID: 'y',
                        order: 3
                    },
                    {
                        label: 'Уник. пользователи',
                        data: users,
                        type: 'line',
                        borderColor: 'rgba(0,163,42,1)',
                        backgroundColor: 'rgba(0,163,42,0.1)',
                        fill: false,
                        tension: 0.3,
                        pointRadius: 4,
                        borderWidth: 2,
                        yAxisID: 'y',
                        order: 2
                    },
                    {
                        label: 'Баллы',
                        data: points,
                        type: 'line',
                        borderColor: 'rgba(139,92,246,1)',
                        backgroundColor: 'rgba(139,92,246,0.1)',
                        fill: false,
                        tension: 0.3,
                        pointRadius: 4,
                        borderWidth: 2,
                        yAxisID: 'y1',
                        order: 1
                    },
                    {
                        label: 'Конверсия в покупку (%)',
                        data: conversion,
                        type: 'line',
                        borderColor: 'rgba(239,68,68,1)',
                        backgroundColor: 'rgba(239,68,68,0.1)',
                        fill: false,
                        tension: 0.3,
                        pointRadius: 4,
                        borderWidth: 2,
                        borderDash: [5, 5],
                        yAxisID: 'y1',
                        order: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleFont: { size: 13 },
                        bodyFont: { size: 12 },
                        padding: 10,
                        cornerRadius: 6,
                        callbacks: {
                            label: function(ctx) {
                                var label = ctx.dataset.label || '';
                                var value = ctx.parsed.y;
                                if (label.indexOf('Конверсия') !== -1) {
                                    return label + ': ' + value + '%';
                                }
                                return label + ': ' + value;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 45, font: { size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        grid: { color: '#f0f0f0' },
                        ticks: { font: { size: 11 } },
                        title: { display: true, text: 'Сканирования / Пользователи' }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { display: false },
                        ticks: { font: { size: 11 } },
                        title: { display: true, text: 'Баллы / Конверсия (%)' }
                    }
                }
            }
        });
    }

    function navigate(params) {
        var p = new URLSearchParams();
        p.set('page', 'wpz-partners');
        p.set('tab', 'stats');
        for (var k in params) {
            if (params[k] !== undefined && params[k] !== '' && params[k] !== '0') {
                p.set(k, params[k]);
            }
        }
        window.location.href = 'admin.php?' + p.toString();
    }

    jQuery(function() {
        var labels = [], scans = [], points = [], users = [], conversion = [];
        chartData.forEach(function(d) {
            labels.push(d.label);
            scans.push(d.cnt || 0);
            points.push(d.total_points || 0);
            users.push(d.unique_users || 0);
            var u = d.unique_users || 0;
            var b = d.buyers_count || 0;
            conversion.push(u > 0 ? Math.round(b / u * 1000) / 10 : 0);
        });
        renderChart(labels, scans, points, users, conversion);

        jQuery('.wpz-ps-period').on('click', function() {
            navigate({
                period: jQuery(this).data('period'),
                partner_id: currentPartner,
                from: jQuery('#wpz-ps-from').val(),
                to: jQuery('#wpz-ps-to').val()
            });
        });

        jQuery('#wpz-ps-partner').on('change', function() {
            navigate({
                period: currentPeriod,
                partner_id: jQuery(this).val(),
                from: jQuery('#wpz-ps-from').val(),
                to: jQuery('#wpz-ps-to').val()
            });
        });

        jQuery('#wpz-ps-apply').on('click', function() {
            navigate({
                period: currentPeriod,
                partner_id: currentPartner,
                from: jQuery('#wpz-ps-from').val(),
                to: jQuery('#wpz-ps-to').val()
            });
        });
    });
})();
</script>
<?php endif; ?>

<script>
    (function(){
        function bindCopy(selector, iconOk, iconDefault){
            document.querySelectorAll(selector).forEach(function(btn){
                btn.addEventListener('click', function(){
                    var link = btn.getAttribute('data-link');
                    if (!link) return;
                    navigator.clipboard.writeText(link).then(function(){
                        var original = btn.textContent;
                        btn.textContent = iconOk;
                        setTimeout(function(){ btn.textContent = original; }, 1200);
                    });
                });
            });
        }
        bindCopy('.wpz-qr-copy-icon', '✅', '🔗');

        document.querySelectorAll('.wpz-upload-btn').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                var targetId = btn.getAttribute('data-target');
                var input = document.getElementById(targetId);
                var frame = wp.media({
                    title: 'Выберите изображение',
                    button: { text: 'Использовать' },
                    multiple: false,
                    library: { type: 'image' }
                });
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    input.value = attachment.url;
                    var existing = input.parentElement.parentElement.querySelector('img');
                    if (existing) {
                        existing.src = attachment.url;
                    } else {
                        var preview = document.createElement('div');
                        preview.style.marginTop = '8px';
                        preview.innerHTML = '<img src="' + attachment.url + '" style="max-width:200px;max-height:100px;border-radius:8px;object-fit:cover;">';
                        input.parentElement.parentElement.appendChild(preview);
                    }
                });
                frame.open();
            });
        });
    })();
</script>
