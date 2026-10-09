<?php if (!defined('ABSPATH')) exit;
$db = WaterPrize_DB::instance();
$db_host = get_option('wpz_db_host', 'node1.pghost.ru');
$db_port = get_option('wpz_db_port', '15980');
$db_name = get_option('wpz_db_name', 'bothost_db_8d8917dc2bab');
$db_user = get_option('wpz_db_user', 'bothost_db_8d8917dc2bab');
$db_pass = get_option('wpz_db_pass', 'Bi75g85iDTRx8KjIsX2PUnzr6QahElWAy_vdrxCgoVM');
$splash_logo = $db->get_setting('splash_logo_url') ?: '';
$tile_slugs = [
    'shop' => 'Магазин купонов',
    'coupons' => 'Мои купоны',
    'partner' => 'Панель партнёра',
    'support' => 'Поддержка',
];
$tile_images = [];
foreach ($tile_slugs as $tile_slug => $_label) {
    $tile_images[$tile_slug] = $db->get_setting('tile_' . $tile_slug . '_image') ?: '';
}
$daily_bonus_points = $db->get_setting('daily_bonus_points');
if ($daily_bonus_points === null || $daily_bonus_points === false || $daily_bonus_points === '') {
    $daily_bonus_points = '50';
}
?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>⚙️ Настройки</h1>

    <?php if ($db->is_connected()): ?>
        <div class="notice notice-success"><p>✅ Подключение к PostgreSQL активно</p></div>
    <?php else: ?>
        <div class="notice notice-error"><p>⚠️ Подключение не установлено. Проверьте настройки ниже.</p></div>
    <?php endif; ?>

    <!-- ═══ Splash Logo ═══ -->
    <div class="wpz-card">
        <h2>🎨 Логотип SplashScreen</h2>
        <p style="color:#646970;font-size:13px;margin:0 0 16px;">Логотип отображается на экране загрузки мини-приложения.</p>
        <form method="post">
            <?php wp_nonce_field('wpz_action'); ?>
            <div style="display:flex;gap:20px;align-items:flex-start;">
                <div>
                    <div id="splash-logo-preview" style="width:120px;height:120px;border-radius:16px;border:2px dashed #c3c4c7;display:flex;align-items:center;justify-content:center;background:#f6f6f6;overflow:hidden;<?php echo $splash_logo ? 'border-style:solid;' : ''; ?>">
                        <?php if ($splash_logo): ?>
                            <img src="<?php echo esc_url($splash_logo); ?>" style="width:100%;height:100%;object-fit:cover;" alt="logo">
                        <?php else: ?>
                            <span style="color:#999;font-size:40px;">📷</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="flex:1;">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">URL изображения</label>
                    <div style="display:flex;gap:6px;margin-bottom:10px;">
                        <input type="text" id="splash_logo_url" name="splash_logo_url"
                               value="<?php echo esc_attr($splash_logo); ?>"
                               class="regular-text" style="flex:1;">
                        <button type="button" id="splash-logo-upload" class="button">📁 Загрузить</button>
                        <?php if ($splash_logo): ?>
                            <button type="button" id="splash-logo-remove" class="button wpz-btn-danger">🗑️</button>
                        <?php endif; ?>
                    </div>
                    <p class="description">Вставьте URL или загрузите изображение через медиабиблиотеку.</p>
                    <div style="margin-top:12px;">
                        <button type="submit" name="action" value="save_splash_logo" class="button button-primary">💾 Сохранить логотип</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- ═══ Home Tiles ═══ -->
    <div class="wpz-card">
        <h2>🏠 Обложки плиток главной страницы</h2>
        <p style="color:#646970;font-size:13px;margin:0 0 16px;">Фото на плитках меню мини-приложения вместо иконок.
            Если изображение не задано — плитка остаётся с обычной иконкой.</p>
        <form method="post">
            <?php wp_nonce_field('wpz_action'); ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;">
                <?php foreach ($tile_slugs as $tile_slug => $tile_label): ?>
                    <?php $tile_val = $tile_images[$tile_slug]; ?>
                    <div style="border:1px solid #dcdcde;border-radius:10px;padding:12px;">
                        <div style="display:flex;gap:12px;align-items:flex-start;">
                            <div id="tile-preview-<?php echo esc_attr($tile_slug); ?>"
                                 style="width:110px;height:82px;border-radius:8px;border:2px dashed #c3c4c7;display:flex;align-items:center;justify-content:center;background:#f6f6f6;overflow:hidden;flex-shrink:0;<?php echo $tile_val ? 'border-style:solid;' : ''; ?>">
                                <?php if ($tile_val): ?>
                                    <img src="<?php echo esc_url($tile_val); ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                                <?php else: ?>
                                    <span style="color:#999;font-size:28px;">📷</span>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;"><?php echo esc_html($tile_label); ?></label>
                                <input type="text" id="tile_<?php echo esc_attr($tile_slug); ?>_url"
                                       name="tile_<?php echo esc_attr($tile_slug); ?>_url"
                                       value="<?php echo esc_attr($tile_val); ?>"
                                       class="regular-text" style="width:100%;box-sizing:border-box;">
                                <div style="display:flex;gap:6px;margin-top:8px;">
                                    <button type="button" class="button wpz-tile-upload" data-slug="<?php echo esc_attr($tile_slug); ?>">📁 Загрузить</button>
                                    <button type="button" class="button wpz-btn-danger wpz-tile-remove" data-slug="<?php echo esc_attr($tile_slug); ?>">🗑️</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="description" style="margin-top:12px;">Рекомендуемое соотношение сторон — как у плитки (ширина больше высоты).
                Изображение сохраняется в медиатеку WordPress и в базу мини-приложения.</p>
            <p class="submit" style="margin-top:12px;">
                <button type="submit" name="action" value="save_home_tiles" class="button button-primary">💾 Сохранить обложки</button>
            </p>
        </form>
    </div>

    <!-- ═══ Daily Bonus ═══ -->
    <div class="wpz-card">
        <h2>🎯 Ежедневный бонус</h2>
        <p style="color:#646970;font-size:13px;margin:0 0 16px;">Серия входов: <b>100 → 120 → 140 → 160 → 180 → далее 200 баллов/день</b>.
            Бонусы сверху базы: <b>3-й день +200</b>, <b>7-й день +500</b>, <b>14-й день +1000</b>.
            Пропуск дня полностью обнуляет серию; покупка купона серию не сбрасывает.</p>
        <form method="post">
            <?php wp_nonce_field('wpz_action'); ?>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Состояние бонуса</label>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="number" name="daily_bonus_points" min="0" step="1"
                       value="<?php echo esc_attr($daily_bonus_points); ?>"
                       style="width:110px;">
                <span style="color:#646970;font-size:13px;">баллов (значение не влияет на сумму)</span>
            </div>
            <p class="description">Число больше 0 — бонус включён, <b>0</b> — отключить.
                Суммы фиксированы серией (100…200 + бонусы) и из поля не берутся.
                Изменение применяется в течение минуты (кэш настроек).</p>
            <div style="margin-top:12px;">
                <button type="submit" name="action" value="save_daily_bonus" class="button button-primary">💾 Сохранить бонус</button>
            </div>
        </form>
    </div>

    <!-- ═══ DB Connection ═══ -->
    <div class="wpz-card">
        <h2>PostgreSQL — удалённая база бота</h2>
        <form method="post">
            <?php wp_nonce_field('wpz_action'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="db_host">Хост</label></th>
                    <td><input type="text" id="db_host" name="db_host" value="<?php echo esc_attr($db_host); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="db_port">Порт</label></th>
                    <td><input type="text" id="db_port" name="db_port" value="<?php echo esc_attr($db_port); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="db_name">Имя БД</label></th>
                    <td><input type="text" id="db_name" name="db_name" value="<?php echo esc_attr($db_name); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="db_user">Пользователь</label></th>
                    <td><input type="text" id="db_user" name="db_user" value="<?php echo esc_attr($db_user); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="db_pass">Пароль</label></th>
                    <td><input type="password" id="db_pass" name="db_pass" value="<?php echo esc_attr($db_pass); ?>" class="regular-text"></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="action" value="save_db_settings" class="button button-primary">💾 Сохранить</button>
            </p>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var frame;
    var uploadBtn = document.getElementById('splash-logo-upload');
    var removeBtn = document.getElementById('splash-logo-remove');
    var urlInput = document.getElementById('splash_logo_url');
    var preview = document.getElementById('splash-logo-preview');

    if (uploadBtn) {
        uploadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (frame) { frame.open(); return; }
            frame = wp.media({ title: 'Выберите логотип', button: { text: 'Использовать' }, multiple: false });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                urlInput.value = attachment.url;
                preview.innerHTML = '<img src="' + attachment.url + '" style="width:100%;height:100%;object-fit:cover;" alt="logo">';
                preview.style.borderStyle = 'solid';
            });
            frame.open();
        });
    }

    if (removeBtn) {
        removeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            urlInput.value = '';
            preview.innerHTML = '<span style="color:#999;font-size:40px;">📷</span>';
            preview.style.borderStyle = 'dashed';
        });
    }

    var tileFrames = {};
    document.querySelectorAll('.wpz-tile-upload').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var slug = btn.getAttribute('data-slug');
            var input = document.getElementById('tile_' + slug + '_url');
            var tilePreview = document.getElementById('tile-preview-' + slug);
            if (tileFrames[slug]) { tileFrames[slug].open(); return; }
            tileFrames[slug] = wp.media({
                title: 'Выберите изображение для плитки',
                button: { text: 'Использовать' },
                multiple: false
            });
            tileFrames[slug].on('select', function() {
                var attachment = tileFrames[slug].state().get('selection').first().toJSON();
                input.value = attachment.url;
                tilePreview.innerHTML = '<img src="' + attachment.url + '" style="width:100%;height:100%;object-fit:cover;" alt="">';
                tilePreview.style.borderStyle = 'solid';
            });
            tileFrames[slug].open();
        });
    });

    document.querySelectorAll('.wpz-tile-remove').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var slug = btn.getAttribute('data-slug');
            var input = document.getElementById('tile_' + slug + '_url');
            var tilePreview = document.getElementById('tile-preview-' + slug);
            input.value = '';
            tilePreview.innerHTML = '<span style="color:#999;font-size:28px;">📷</span>';
            tilePreview.style.borderStyle = 'dashed';
        });
    });
});
</script>
