<?php if (!defined('ABSPATH')) exit;
$rarity_meta = [
    'common'    => ['Обычное',    '#8a9199', 'rgba(138,145,153,0.12)'],
    'rare'      => ['Редкое',     '#5b9fd4', 'rgba(91,159,212,0.12)'],
    'epic'      => ['Эпическое',  '#a06ee0', 'rgba(160,110,224,0.12)'],
    'legendary' => ['Легендарное','#d4a017', 'rgba(212,160,23,0.15)'],
];
$level_names = [1 => 'Росток', 2 => 'Саженец', 3 => 'Молодое дерево', 4 => 'Крепкое дерево', 5 => 'Могучее дерево', 6 => 'Древо жизни'];
$level_imgs = [1 => 'stage-1.png', 2 => 'stage-2.png', 3 => 'stage-3.png', 4 => 'stage-4.png', 5 => 'stage-5.png', 6 => 'stage-6.png'];

// Авто-вывод NFT из seed (зеркало app/database.py::derive_tree_nft)
function wpz_tree_derive_nft($seed) {
    $seed = (int)$seed;
    $hue = $seed % 360;
    $roll = ($seed >> 10) % 100;
    if ($roll < 3) $rarity = 'legendary';
    elseif ($roll < 15) $rarity = 'epic';
    elseif ($roll < 40) $rarity = 'rare';
    else $rarity = 'common';
    $serial = '#' . strtoupper(str_pad(dechex($seed % 0xFFFF), 4, '0', STR_PAD_LEFT));
    return ['hue' => $hue, 'rarity' => $rarity, 'serial' => $serial];
}

// Итоговые значения с учётом оверрайдов
function wpz_tree_effective_nft($user) {
    $auto = wpz_tree_derive_nft($user['tree_seed'] ?? 0);
    $rarity = $auto['rarity'];
    $hue = $auto['hue'];
    $serial = $auto['serial'];
    $has_override = false;
    if (!empty($user['tree_rarity']) && isset($GLOBALS['wpz_rarity_meta'][$user['tree_rarity']])) {
        $rarity = $user['tree_rarity'];
        $has_override = true;
    }
    if ($user['tree_hue'] !== null && $user['tree_hue'] !== '') {
        $hue = max(0, min(359, (int)$user['tree_hue']));
        $has_override = true;
    }
    if (!empty($user['tree_serial'])) {
        $serial = $user['tree_serial'];
        $has_override = true;
    }
    return ['hue' => $hue, 'rarity' => $rarity, 'serial' => $serial, 'auto' => $auto, 'has_override' => $has_override];
}
$GLOBALS['wpz_rarity_meta'] = $rarity_meta;
$tab = $tab ?? 'nft';
?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>🌳 Дерево</h1>

    <!-- ═══ Вкладки ═══ -->
    <nav class="nav-tab-wrapper" style="margin-bottom:16px;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=wpz-tree&tab=nft' . (!empty($search) ? '&s=' . rawurlencode($search) : ''))); ?>"
           class="nav-tab <?php echo $tab === 'nft' ? 'nav-tab-active' : ''; ?>">🎨 NFT-настройки</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wpz-tree&tab=images')); ?>"
           class="nav-tab <?php echo $tab === 'images' ? 'nav-tab-active' : ''; ?>">🖼 Изображения стадий</a>
    </nav>

    <?php if ($tab === 'images'): ?>
    <!-- ══════════ Вкладка: Изображения стадий ══════════ -->
    <div class="wpz-card">
        <h2 style="margin-top:0;">Изображения деревьев по стадиям</h2>
        <p class="description">
            Замените картинки для 6 базовых стадий. Загрузите файл или вставьте URL.
            Пустое поле = дефолтное изображение <code>/uploads/tree/stage-N.png</code>.
            Изменения применяются сразу после сохранения (кэш статики обновится автоматически).
        </p>
        <form method="post">
            <?php wp_nonce_field('wpz_action'); ?>
            <input type="hidden" name="action" value="save_tree_stage_imgs">
            <table class="form-table">
                <?php for ($lv = 1; $lv <= 6; $lv++):
                    $cur = $stage_imgs[$lv];
                    $shown = $cur ?: $stage_defaults[$lv];
                ?>
                <tr>
                    <th style="width:180px;">
                        <label>Стадия <?php echo $lv; ?> · <?php echo esc_html($level_names[$lv]); ?></label>
                        <div style="font-size:11px;color:#999;margin-top:2px;">0–99 / 100–499 / 500–999 / 1000–1999 / 2000–4999 / 5000+ XP</div>
                    </th>
                    <td>
                        <div style="display:flex;gap:12px;align-items:flex-start;">
                            <div id="stage-preview-<?php echo $lv; ?>"
                                 style="width:110px;height:110px;border-radius:12px;border:2px dashed #c3c4c7;display:flex;align-items:center;justify-content:center;background:#0F1418;overflow:hidden;flex-shrink:0;<?php echo $cur ? 'border-style:solid;border-color:#3d9e6a;' : ''; ?>">
                                <img src="<?php echo esc_url($shown); ?>" alt="stage <?php echo $lv; ?>"
                                     style="max-width:100%;max-height:100%;object-fit:contain;">
                            </div>
                            <div style="flex:1;">
                                <input type="text" id="tree_stage_img_<?php echo $lv; ?>" name="tree_stage_img_<?php echo $lv; ?>"
                                       value="<?php echo esc_attr($cur); ?>" placeholder="<?php echo esc_url($stage_defaults[$lv]); ?>"
                                       style="width:100%;">
                                <div style="margin-top:6px;display:flex;gap:6px;">
                                    <button type="button" class="button wpz-stage-upload" data-lv="<?php echo $lv; ?>">📤 Загрузить</button>
                                    <?php if ($cur): ?>
                                        <button type="button" class="button wpz-stage-remove" data-lv="<?php echo $lv; ?>">✖ Сбросить</button>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url($shown); ?>" target="_blank" class="button">Открыть ↗</a>
                                </div>
                                <p class="description" style="margin-top:4px;">
                                    <?php echo $cur ? '⚙️ Кастомное изображение' : 'Дефолтное изображение'; ?>
                                </p>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endfor; ?>
            </table>
            <?php submit_button('Сохранить изображения стадий'); ?>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var frames = {};
        document.querySelectorAll('.wpz-stage-upload').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var lv = btn.getAttribute('data-lv');
                if (frames[lv]) { frames[lv].open(); return; }
                frames[lv] = wp.media({
                    title: 'Выберите изображение стадии ' + lv,
                    button: { text: 'Использовать' },
                    multiple: false
                });
                frames[lv].on('select', function () {
                    var att = frames[lv].state().get('selection').first().toJSON();
                    var input = document.getElementById('tree_stage_img_' + lv);
                    var prev = document.getElementById('stage-preview-' + lv);
                    input.value = att.url;
                    prev.innerHTML = '<img src="' + att.url + '" alt="">';
                    prev.style.borderStyle = 'solid';
                    prev.style.borderColor = '#3d9e6a';
                });
                frames[lv].open();
            });
        });
        document.querySelectorAll('.wpz-stage-remove').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var lv = btn.getAttribute('data-lv');
                var input = document.getElementById('tree_stage_img_' + lv);
                var prev = document.getElementById('stage-preview-' + lv);
                input.value = '';
                var def = input.getAttribute('placeholder');
                prev.innerHTML = '<img src="' + def + '" alt="">';
                prev.style.borderStyle = 'dashed';
                prev.style.borderColor = '#c3c4c7';
            });
        });
    });
    </script>

    <?php else: ?>
    <!-- ══════════ Вкладка: NFT-настройки ══════════ -->

    <!-- ═══ Поиск ═══ -->
    <div class="wpz-card" style="margin-bottom:16px;">
        <form method="get" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
            <input type="hidden" name="page" value="wpz-tree">
            <div style="flex:1;min-width:220px;">
                <label for="wpz-tree-search"><strong>Поиск пользователя</strong></label>
                <input type="text" id="wpz-tree-search" name="s" value="<?php echo esc_attr($search); ?>"
                       placeholder="Telegram ID или имя…" style="width:100%;">
            </div>
            <button type="submit" class="button button-primary">Найти</button>
            <?php if ($search): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wpz-tree')); ?>" class="button">Сбросить</a>
            <?php endif; ?>
        </form>
        <p class="description" style="margin-top:8px;">
            Найдите пользователя по Telegram ID или имени, откройте карточку и задайте редкость, оттенок (tint) и серийный номер дерева вручную.
            Пустые поля = авто-значение из seed.
        </p>
    </div>

    <?php if ($selected): $eff = wpz_tree_effective_nft($selected);
        $lv = max(1, min(6, (int)($selected['tree_level'] ?? 1)));
        $lv_name = $level_names[$lv];
        $lv_img = $level_imgs[$lv];
        $rmeta = $rarity_meta[$eff['rarity']];
    ?>
    <!-- ═══ Карточка пользователя + редактор ═══ -->
    <div style="display:grid;grid-template-columns:320px 1fr;gap:16px;align-items:start;">
        <!-- Превью -->
        <div class="wpz-card" style="text-align:center;">
            <h2 style="margin-top:0;">Превью</h2>
            <div style="display:inline-block;padding:14px;border-radius:18px;background:#0F1418;border:1px solid <?php echo esc_attr($rmeta[1]); ?>33;">
                <img src="https://istokmoscowbot.bothost.tech/uploads/tree/<?php echo esc_attr($lv_img); ?>?v=202610112"
                     alt="<?php echo esc_attr($lv_name); ?>"
                     style="max-width:220px;max-height:220px;display:block;margin:0 auto;border-radius:12px;filter:hue-rotate(<?php echo (int)$eff['hue']; ?>deg);">
            </div>
            <div style="margin-top:10px;">
                <span style="display:inline-block;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:700;background:rgba(61,158,106,0.12);color:#3d9e6a;border:1px solid rgba(61,158,106,0.22);">
                    Уровень <?php echo $lv; ?> · <?php echo esc_html($lv_name); ?>
                </span>
            </div>
            <div style="margin-top:8px;display:flex;gap:6px;justify-content:center;align-items:center;">
                <code style="padding:3px 10px;border-radius:6px;background:rgba(255,255,255,0.05);border:1px solid #ffffff14;font-size:12px;letter-spacing:1px;"><?php echo esc_html($eff['serial']); ?></code>
                <span style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:800;text-transform:uppercase;background:<?php echo esc_attr($rmeta[2]); ?>;color:<?php echo esc_attr($rmeta[1]); ?>;border:1px solid <?php echo esc_attr($rmeta[1]); ?>44;">
                    <?php echo esc_html($rmeta[0]); ?>
                </span>
            </div>
            <?php if ($eff['has_override']): ?>
                <p class="description" style="margin-top:8px;">⚙️ Применены ручные настройки (оверрайд)</p>
            <?php endif; ?>
            <p class="description" style="margin-top:4px;">Tint: hue-rotate(<?php echo (int)$eff['hue']; ?>°)</p>
        </div>

        <!-- Форма -->
        <div class="wpz-card">
            <h2 style="margin-top:0;">
                <?php echo esc_html($selected['name'] ?: 'Без имени'); ?>
                <small style="color:#888;font-weight:400;">· Telegram ID <?php echo esc_html($selected['telegram_id']); ?></small>
            </h2>
            <p class="description">
                Опыт: <strong><?php echo esc_html($selected['tree_xp'] ?? 0); ?> XP</strong> ·
                Уровень: <strong><?php echo $lv; ?></strong> ·
                Seed: <code><?php echo esc_html($selected['tree_seed'] ?? '—'); ?></code>
                (авто: rarity <strong><?php echo esc_html($rarity_meta[$eff['auto']['rarity']][0]); ?></strong>,
                hue <strong><?php echo (int)$eff['auto']['hue']; ?>°</strong>,
                serial <strong><?php echo esc_html($eff['auto']['serial']); ?></strong>)
            </p>

            <form method="post">
                <?php wp_nonce_field('wpz_action'); ?>
                <input type="hidden" name="action" value="save_tree_nft">
                <input type="hidden" name="tree_tg" value="<?php echo esc_attr($selected['telegram_id']); ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="tree_rarity">Редкость</label></th>
                        <td>
                            <select id="tree_rarity" name="tree_rarity">
                                <option value="">— авто (из seed) —</option>
                                <?php foreach ($rarity_meta as $key => $meta): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($selected['tree_rarity'] ?? '', $key); ?>>
                                        <?php echo esc_html($meta[0]); ?> (<?php echo esc_html($key); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Легендарное — золотая рамка + частицы; Эпическое — фиолетовое свечение; Редкое — голубая рамка.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tree_hue">Оттенок (tint), 0–359°</label></th>
                        <td>
                            <input type="number" id="tree_hue" name="tree_hue" min="0" max="359"
                                   value="<?php echo esc_attr($selected['tree_hue'] ?? ''); ?>" class="small-text" placeholder="авто">
                            <span class="description">hue-rotate CSS. 0° = оригинальная зелень, 210° = фиолет, 40° = золото, 280° = розовый.</span>
                            <div id="wpz-hue-slider" style="margin-top:8px;width:260px;height:22px;border-radius:6px;cursor:pointer;
                                background:linear-gradient(to right,#e74c3c,#e67e22,#f1c40f,#2ecc71,#1abc9c,#3498db,#9b59b6,#e74c3c);"></div>
                            <div id="wpz-hue-preview" style="margin-top:6px;display:inline-block;padding:2px 10px;border-radius:6px;background:rgba(255,255,255,0.05);font-size:12px;">Превью hue: <span id="wpz-hue-val">—</span>°</div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tree_serial">Серийный номер</label></th>
                        <td>
                            <input type="text" id="tree_serial" name="tree_serial" maxlength="32"
                                   value="<?php echo esc_attr($selected['tree_serial'] ?? ''); ?>" placeholder="авто" style="width:200px;">
                            <p class="description">Например #A7F3. Пусто = авто из seed.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Сохранить NFT-настройки'); ?>
            </form>

            <form method="post" style="margin-top:8px;">
                <?php wp_nonce_field('wpz_action'); ?>
                <input type="hidden" name="action" value="regen_tree_seed">
                <input type="hidden" name="tree_tg" value="<?php echo esc_attr($selected['telegram_id']); ?>">
                <?php submit_button('Перегенерировать seed (новый авто-NFT)', 'secondary', 'submit', false); ?>
                <p class="description">Создаст новый случайный seed — авто-редкость/оттенок/серийник изменятся. Ручные оверрайды при этом сохранятся.</p>
            </form>

            <p class="description" style="margin-top:12px;">
                <a href="https://istokmoscowbot.bothost.tech/?page=tree&user_id=<?php echo esc_attr($selected['telegram_id']); ?>" target="_blank">Открыть дерево пользователя ↗</a>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══ Список пользователей ═══ -->
    <div class="wpz-card" style="margin-top:16px;">
        <h2><?php echo $search ? 'Результаты поиска' : 'Пользователи с наибольшим опытом'; ?></h2>
        <?php if (!$users): ?>
            <p>Ничего не найдено.</p>
        <?php else: ?>
            <table class="wpz-table widefat">
                <thead>
                <tr>
                    <th>Telegram ID</th>
                    <th>Имя</th>
                    <th>XP</th>
                    <th>Уровень</th>
                    <th>Редкость</th>
                    <th>Tint</th>
                    <th>Серийник</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $eff = wpz_tree_effective_nft($u);
                    $rmeta = $rarity_meta[$eff['rarity']];
                    $lv = max(1, min(6, (int)($u['tree_level'] ?? 1)));
                ?>
                    <tr>
                        <td><code><?php echo esc_html($u['telegram_id']); ?></code></td>
                        <td><?php echo esc_html($u['name'] ?: '—'); ?></td>
                        <td><?php echo esc_html($u['tree_xp'] ?? 0); ?></td>
                        <td><?php echo $lv; ?> · <?php echo esc_html($level_names[$lv]); ?></td>
                        <td>
                            <span style="padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:<?php echo esc_attr($rmeta[2]); ?>;color:<?php echo esc_attr($rmeta[1]); ?>;">
                                <?php echo esc_html($rmeta[0]); ?>
                            </span>
                            <?php if ($eff['has_override']): ?><span title="Ручные настройки">⚙️</span><?php endif; ?>
                        </td>
                        <td><?php echo (int)$eff['hue']; ?>°</td>
                        <td><code><?php echo esc_html($eff['serial']); ?></code></td>
                        <td><a href="<?php echo esc_url(admin_url('admin.php?page=wpz-tree&user=' . $u['telegram_id'] . ($search ? '&s=' . rawurlencode($search) : ''))); ?>" class="button button-small">Открыть</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('tree_hue');
    var slider = document.getElementById('wpz-hue-slider');
    var val = document.getElementById('wpz-hue-val');
    if (!input || !slider) return;
    function show(v) {
        if (val) val.textContent = (v === '' || v === null) ? 'авто' : v;
    }
    show(input.value);
    slider.addEventListener('click', function (e) {
        var rect = slider.getBoundingClientRect();
        var pct = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        var deg = Math.round(pct * 359);
        input.value = deg;
        show(deg);
    });
    input.addEventListener('input', function () { show(input.value); });
})();
</script>

    <?php endif; // tab === 'images' ?>
</div>
