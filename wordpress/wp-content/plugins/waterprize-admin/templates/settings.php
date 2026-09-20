<?php if (!defined('ABSPATH')) exit;
$db = WaterPrize_DB::instance();
$db_host = get_option('wpz_db_host', 'node1.pghost.ru');
$db_port = get_option('wpz_db_port', '15980');
$db_name = get_option('wpz_db_name', 'bothost_db_8d8917dc2bab');
$db_user = get_option('wpz_db_user', 'bothost_db_8d8917dc2bab');
$db_pass = get_option('wpz_db_pass', 'Bi75g85iDTRx8KjIsX2PUnzr6QahElWAy_vdrxCgoVM');
$splash_logo = $db->get_setting('splash_logo_url') ?: '';
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
});
</script>
