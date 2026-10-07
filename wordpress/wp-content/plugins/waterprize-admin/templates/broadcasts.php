<?php if (!defined('ABSPATH')) exit;

$status_map = [
    'sending' => ['⏳ В очереди', 'wpz-blue'],
    'sent'    => ['✅ Отправлена', 'wpz-green'],
    'partial' => ['⚠️ Частично', 'wpz-yellow'],
    'failed'  => ['❌ Ошибка', 'wpz-red'],
];
?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>📣 Рассылки <span class="wpz-count"><?php echo esc_html(count($broadcasts)); ?></span></h1>

    <div class="wpz-card">
        <h2>✉️ Новая рассылка</h2>
        <p style="color:#646970;font-size:13px;margin:0 0 16px;">
            Сообщение уйдёт каждому пользователю лично в чат с ботом
            (сейчас: <strong><?php echo esc_html($broadcast_recipients); ?> чел.</strong>).
            Фото отправляются сеткой, текст — подписью к ним (до 1024 символов),
            более длинный текст продолжится отдельным сообщением сразу за фото.
            Рассылки <strong>не удаляются автоматически</strong> — убрать их из чатов можно
            только кнопкой «🗑️ Удалить» ниже.
        </p>
        <form method="post">
            <?php wp_nonce_field('wpz_action'); ?>
            <input type="hidden" id="broadcast_media" name="broadcast_media" value="[]">
            <table class="form-table">
                <tr>
                    <th><label>Фото</label></th>
                    <td>
                        <button type="button" class="button" id="wpz-pick-media">📷 Выбрать фото</button>
                        <span id="wpz-media-count" style="color:#999;font-size:12px;margin-left:6px;">фото не выбрано</span>
                        <div id="wpz-media-preview" style="display:flex;gap:12px;flex-wrap:wrap;margin-top:10px;"></div>
                        <p class="description">До 10 фотографий из медиатеки (внутри окна можно загрузить новые).
                            Без фото текст уйдёт обычным сообщением.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="broadcast_title">Заголовок</label></th>
                    <td>
                        <input type="text" id="broadcast_title" name="broadcast_title"
                               class="regular-text" placeholder="Новости (необязательно)">
                        <p class="description">Если указан — отправляется первой строкой перед текстом.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="broadcast_body">Текст</label></th>
                    <td>
                        <textarea id="broadcast_body" name="broadcast_body"
                                  rows="6" class="large-text" required
                                  placeholder="Текст рассылки..."></textarea>
                        <p class="description">Отправляется обычным текстом, без HTML-разметки.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="action" value="send_broadcast"
                        class="button button-primary"
                        onclick="return confirm('Отправить рассылку всем пользователям?')">
                    📤 Отправить рассылку
                </button>
            </p>
        </form>
    </div>

    <div class="wpz-card">
        <h2>Отправленные рассылки</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Заголовок</th>
                    <th style="width:140px;">Фото</th>
                    <th>Текст</th>
                    <th>Статус</th>
                    <th>Доставлено</th>
                    <th>Дата</th>
                    <th>Автор</th>
                    <th style="width:90px;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($broadcasts)): ?>
                    <tr><td colspan="9">Рассылок пока нет</td></tr>
                <?php else: foreach ($broadcasts as $b):
                    $st = $status_map[$b['status']] ?? [$b['status'], 'wpz-blue'];
                    $media_ids = json_decode((string)($b['media'] ?? '[]'), true);
                    if (!is_array($media_ids)) $media_ids = [];
                ?>
                    <tr>
                        <td><?php echo esc_html($b['id']); ?></td>
                        <td><strong><?php echo esc_html($b['title'] ?: '—'); ?></strong></td>
                        <td>
                            <?php if ($media_ids): ?>
                                <?php foreach (array_slice($media_ids, 0, 4) as $mid):
                                    $thumb = wp_get_attachment_image_url((int)$mid, 'thumbnail');
                                    if (!$thumb) continue; ?>
                                    <img src="<?php echo esc_url($thumb); ?>" alt=""
                                         style="width:36px;height:36px;object-fit:cover;border-radius:4px;margin:0 4px 4px 0;vertical-align:middle;">
                                <?php endforeach; ?>
                                <?php if (count($media_ids) > 4): ?>
                                    <span class="description">+<?php echo esc_html(count($media_ids) - 4); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(mb_strimwidth($b['body'] ?: '', 0, 80, '...')); ?></td>
                        <td>
                            <span class="wpz-badge <?php echo esc_attr($st[1]); ?>"><?php echo esc_html($st[0]); ?></span>
                            <?php if (!empty($b['error'])): ?>
                                <p class="description" style="color:#d63638;margin:4px 0 0;">
                                    <?php echo esc_html($b['error']); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($b['sent_count']); ?> / <?php echo esc_html($b['recipients']); ?>
                        </td>
                        <td><?php echo $b['sent_at'] ? date('Y-m-d H:i', strtotime($b['sent_at'])) : ($b['created_at'] ? date('Y-m-d H:i', strtotime($b['created_at'])) : '—'); ?></td>
                        <td><?php echo esc_html($b['created_by'] ?: '—'); ?></td>
                        <td>
                            <form method="post" style="display:inline">
                                <?php wp_nonce_field('wpz_action'); ?>
                                <input type="hidden" name="action" value="delete_broadcast">
                                <input type="hidden" name="broadcast_id" value="<?php echo esc_attr($b['id']); ?>">
                                <button type="submit" class="button button-small"
                                        onclick="return confirm('Удалить рассылку и попытаться убрать её из чатов пользователей?')">
                                    🗑️
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('broadcast_media');
    var preview = document.getElementById('wpz-media-preview');
    var counter = document.getElementById('wpz-media-count');
    var items = [];
    try {
        var parsed = JSON.parse(input.value);
        if (Array.isArray(parsed)) items = parsed;
    } catch (e) { items = []; }

    function render() {
        input.value = JSON.stringify(items);
        counter.textContent = items.length
            ? 'выбрано фото: ' + items.length + ' (макс. 10)'
            : 'фото не выбрано';
        preview.innerHTML = '';
        items.forEach(function (item, idx) {
            var box = document.createElement('div');
            box.style.cssText = 'position:relative;';

            var img = document.createElement('img');
            img.src = item.url;
            img.alt = '';
            img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #dcdcde;display:block;';

            var del = document.createElement('button');
            del.type = 'button';
            del.textContent = '×';
            del.title = 'Убрать фото';
            del.style.cssText = 'position:absolute;top:-7px;right:-7px;width:20px;height:20px;padding:0;border:0;border-radius:50%;background:#d63638;color:#fff;font-size:14px;line-height:18px;cursor:pointer;';
            del.onclick = function () {
                items.splice(idx, 1);
                render();
            };

            box.appendChild(img);
            box.appendChild(del);
            preview.appendChild(box);
        });
    }

    document.getElementById('wpz-pick-media').addEventListener('click', function () {
        if (!window.wp || !wp.media) {
            alert('Медиатека недоступна — обновите страницу');
            return;
        }
        var frame = wp.media({
            title: 'Фото для рассылки',
            button: { text: 'Добавить' },
            multiple: 'add',
            library: { type: 'image' }
        });
        frame.on('select', function () {
            frame.state().get('selection').toJSON().forEach(function (a) {
                if (items.length >= 10) return;
                var exists = items.some(function (i) { return String(i.id) === String(a.id); });
                if (!exists) items.push({ id: a.id, url: a.url });
            });
            render();
        });
        frame.open();
    });

    render();
})();
</script>
