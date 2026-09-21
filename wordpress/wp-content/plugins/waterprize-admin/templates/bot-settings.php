<?php if (!defined('ABSPATH')) exit;
$db = WaterPrize_DB::instance();

$reward_keys = [
    'scan_balance' => ['label' => 'Баллы за скан бутылки', 'default' => '10', 'type' => 'number'],
    'scan_xp' => ['label' => 'Опыт за скан бутылки', 'default' => '10', 'type' => 'number'],
    'partner_scan_default' => ['label' => 'Баллы за скан партнёра (дефолт)', 'default' => '10', 'type' => 'number'],
    'gift_min' => ['label' => 'Мин. баллы за подарок', 'default' => '10', 'type' => 'number'],
    'gift_max' => ['label' => 'Макс. баллы за подарок', 'default' => '100', 'type' => 'number'],
    'conversion_multiplier' => ['label' => 'Множитель конвертации выигрыша', 'default' => '10', 'type' => 'number'],
    'expired_conversion_multiplier' => ['label' => 'Множитель автоконвертации (просрочка)', 'default' => '5', 'type' => 'number'],
    'level_up_bonus' => ['label' => 'Баллы за повышение уровня дерева', 'default' => '0', 'type' => 'number'],
    'tree_threshold_2' => ['label' => 'Опыт для уровня 2 (Саженец)', 'default' => '100', 'type' => 'number'],
    'tree_threshold_3' => ['label' => 'Опыт для уровня 3 (Молодое)', 'default' => '500', 'type' => 'number'],
    'tree_threshold_4' => ['label' => 'Опыт для уровня 4 (Крепкое)', 'default' => '1000', 'type' => 'number'],
    'tree_threshold_5' => ['label' => 'Опыт для уровня 5 (Могучее)', 'default' => '2000', 'type' => 'number'],
    'tree_threshold_6' => ['label' => 'Опыт для уровня 6 (Древо)', 'default' => '5000', 'type' => 'number'],
];

$message_keys = [
    'msg_welcome' => ['label' => 'Главное меню', 'hint' => 'Переменные: {name}, {balance}', 'rows' => 3],
    'msg_scan_success_1' => ['label' => 'Сканирование — поздравление', 'hint' => '', 'rows' => 3],
    'msg_scan_success_2' => ['label' => 'Сканирование — про подарок', 'hint' => '', 'rows' => 3],
    'msg_gift_prompt' => ['label' => 'Напоминание про подарок', 'hint' => '', 'rows' => 3],
    'msg_balance' => ['label' => 'Баланс', 'hint' => 'Переменные: {balance}, {total_scans}', 'rows' => 2],
    'msg_stats' => ['label' => 'Статистика', 'hint' => 'Переменные: {total_scans}, {balance}, {codes_count}, {unassigned}', 'rows' => 3],
    'msg_donation_success' => ['label' => 'Пожертвование — спасибо', 'hint' => 'Переменная: {amount}', 'rows' => 2],
    'msg_exchange_success' => ['label' => 'Обмен — заказ оформлен', 'hint' => 'Переменные: {name}, {order_id}', 'rows' => 2],
    'msg_level_up' => ['label' => 'Повышение уровня дерева', 'hint' => 'Переменная: {level_name}', 'rows' => 2],
];

$values = [];
foreach (array_merge(array_keys($reward_keys), array_keys($message_keys)) as $key) {
    $values[$key] = $db->get_setting($key) ?: '';
}
?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>🤖 Настройки бота</h1>
    <p style="color:#646970;">Управление наградами и текстовыми сообщениями бота. Изменения вступают в силу сразу (кеш обновляется автоматически).</p>

    <form method="post">
        <?php wp_nonce_field('wpz_action'); ?>

        <!-- ═══ Rewards ═══ -->
        <div class="wpz-card">
            <h2>💰 Награды</h2>
            <p style="color:#646970;font-size:13px;margin:0 0 16px;">Суммы баллов, опыта и множители. Оставьте 0 чтобы отключить.</p>
            <table class="form-table">
                <?php foreach ($reward_keys as $key => $cfg): ?>
                <tr>
                    <th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($cfg['label']); ?></label></th>
                    <td>
                        <input type="number" id="<?php echo esc_attr($key); ?>"
                               name="<?php echo esc_attr($key); ?>"
                               value="<?php echo esc_attr($values[$key] ?: $cfg['default']); ?>"
                               min="0" class="small-text">
                        <span style="color:#999;font-size:12px;margin-left:6px;">по умолчанию: <?php echo $cfg['default']; ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- ═══ Messages ═══ -->
        <div class="wpz-card">
            <h2>💬 Сообщения</h2>
            <p style="color:#646970;font-size:13px;margin:0 0 16px;">Тексты, которые бот отправляет пользователям. Поддерживаются HTML-теги и переменные в фигурных скобках.</p>
            <table class="form-table">
                <?php foreach ($message_keys as $key => $cfg): ?>
                <tr>
                    <th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($cfg['label']); ?></label></th>
                    <td>
                        <textarea id="<?php echo esc_attr($key); ?>"
                                  name="<?php echo esc_attr($key); ?>"
                                  rows="<?php echo $cfg['rows']; ?>"
                                  class="large-text code"
                                  style="font-family:inherit;font-size:13px;"><?php echo esc_textarea($values[$key]); ?></textarea>
                        <?php if (!empty($cfg['hint'])): ?>
                        <p class="description"><?php echo esc_html($cfg['hint']); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <p class="submit">
            <button type="submit" name="action" value="save_bot_settings" class="button button-primary">💾 Сохранить все настройки</button>
        </p>
    </form>
</div>
