<?php if (!defined('ABSPATH')) exit;
$db_host = get_option('wpz_db_host', 'node1.pghost.ru');
$db_port = get_option('wpz_db_port', '15980');
$db_name = get_option('wpz_db_name', 'bothost_db_8d8917dc2bab');
$db_user = get_option('wpz_db_user', 'bothost_db_8d8917dc2bab');
$db_pass = get_option('wpz_db_pass', 'Bi75g85iDTRx8KjIsX2PUnzr6QahElWAy_vdrxCgoVM');
?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>⚙️ Настройки подключения к БД</h1>

    <?php if (WaterPrize_DB::instance()->is_connected()): ?>
        <div class="notice notice-success"><p>✅ Подключение к PostgreSQL активно</p></div>
    <?php else: ?>
        <div class="notice notice-error"><p>⚠️ Подключение не установлено. Проверьте настройки ниже.</p></div>
    <?php endif; ?>

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
