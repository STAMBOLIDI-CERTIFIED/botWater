<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>🤝 Партнёры <span class="wpz-count"><?php echo esc_html(count($categories)); ?></span></h1>

    <?php if ($edit_category): ?>

    <div class="wpz-card wpz-bottle-detail">
        <h2>
            Партнёр <strong><?php echo esc_html($edit_category['title']); ?></strong>
            <a href="<?php echo admin_url('admin.php?page=wpz-categories'); ?>" class="page-title-action">← Назад</a>
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
                    <div style="margin-top:10px;display:flex;gap:6px;justify-content:center;">
                        <a href="https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=<?php echo urlencode($edit_category['qr_code']); ?>"
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
    <?php endif; ?>

    <!-- Add/Edit Form -->
    <div class="wpz-card" style="max-width:700px;">
        <h2><?php echo $edit_category ? '✏️ Редактировать партнёра' : '➕ Новый партнёр'; ?></h2>
        <form method="post" action="<?php echo admin_url('admin.php?page=wpz-categories'); ?>">
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
                    <a href="<?php echo admin_url('admin.php?page=wpz-categories'); ?>" class="button">Отмена</a>
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
                                <a href="https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=<?php echo urlencode($c['qr_code']); ?>"
                                   target="_blank" rel="noopener" title="Открыть QR в полном размере">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=70x70&data=<?php echo urlencode($c['qr_code']); ?>"
                                         class="wpz-qr-thumb" alt="QR">
                                </a>
                                <div class="wpz-qr-actions">
                                    <a href="https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=<?php echo urlencode($c['qr_code']); ?>"
                                       download="partner-<?php echo esc_attr($c['id']); ?>-qr.png" title="Скачать QR">⬇️</a>
                                    <button type="button" class="wpz-qr-copy-icon" data-link="<?php echo esc_attr($c['qr_code']); ?>" title="Скопировать код">🔗</button>
                                </div>
                            <?php else: ?>
                                <span class="description">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=wpz-categories&edit=' . $c['id']); ?>"
                               class="button button-small">✏️</a>
                            <form method="post" action="<?php echo admin_url('admin.php?page=wpz-categories'); ?>" style="display:inline;">
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
</div>

<?php if ($edit_category): ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new QRCode(document.getElementById('wpz-qr-code'), {
        text: <?php echo json_encode($edit_category['qr_code'] ?? ''); ?>,
        width: 200,
        height: 200,
        colorDark: '#1e293b',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
});
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
