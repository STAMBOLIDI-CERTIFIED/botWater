<?php
/**
 * Admin page renderers
 */
class WaterPrize_Pages {

    private static function db() { return WaterPrize_DB::instance(); }

    /**
     * Локальные медиа WP (127.0.0.1/localhost) недоступны с телефона и по HTTPS.
     * Копируем файл в public/uploads репозитория и возвращаем относительный URL /uploads/...
     * Остальные URL (абсолютные на другие домены, пустые) возвращаем без изменений.
     */
    private static function normalize_media_url($url) {
        $url = trim((string)$url);
        if ($url === '') return '';
        if (!preg_match('#^https?://(?:127\.0\.0\.1|localhost)(?::\d+)?/wp-content/uploads/(.+)$#', $url, $m)) {
            return $url;
        }
        $rel = ltrim($m[1], '/');
        $src = (defined('ABSPATH') ? ABSPATH : '') . 'wp-content/uploads/' . $rel;
        if (!is_readable($src)) return $url;
        $base = dirname(__FILE__, 6) . '/public/uploads/';
        if (!is_dir($base)) return $url;
        $rel_dir = str_replace('\\', '/', dirname($rel));
        if ($rel_dir === '.') $rel_dir = '';
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        $name = 'wpz_' . substr(md5($rel . '|' . filesize($src) . '|' . filemtime($src)), 0, 14) . ($ext ? '.' . $ext : '');
        $dst_rel = ($rel_dir ? $rel_dir . '/' : '') . $name;
        $dst = $base . $dst_rel;
        if (!is_file($dst)) {
            if (!is_dir(dirname($dst))) @mkdir(dirname($dst), 0755, true);
            if (!@copy($src, $dst)) return $url;
        }
        return '/uploads/' . $dst_rel;
    }

    /**
     * Изображение купона по умолчанию — фото партнёра:
     * сначала логотип, затем обложка.
     */
    private static function default_prize_image($category_id) {
        if ((int)$category_id <= 0) return '';
        $cat = self::db()->get_category((int)$category_id);
        if (!$cat) return '';
        $logo = trim((string)($cat['logo_url'] ?? ''));
        if ($logo !== '') return $logo;
        $cover = trim((string)($cat['image_url'] ?? ''));
        return $cover;
    }

    public static function flash_message() {
        if (!empty($_GET['msg'])) {
            $type = isset($_GET['err']) ? 'error' : 'success';
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($_GET['msg']) . '</p></div>';
        }
    }

    /**
     * Ссылка сортировки для заголовка колонки.
     * Сохраняет текущие GET-параметры, переключает dir при повторном клике.
     */
    public static function sort_link($label, $key, $default_dir = 'ASC') {
        $current = sanitize_text_field($_GET['sort'] ?? '');
        $dir_req = strtoupper(sanitize_text_field($_GET['dir'] ?? ''));
        $dir = $dir_req === 'DESC' ? 'DESC' : ($dir_req === 'ASC' ? 'ASC' : $default_dir);
        $active = ($current === $key);
        $next = $active ? ($dir === 'ASC' ? 'DESC' : 'ASC') : $default_dir;

        $params = [];
        foreach ($_GET as $k => $v) {
            if (in_array($k, ['edit', 'edit_prize', 'edit_account'], true)) continue;
            if (is_array($v)) continue;
            if ($v === '' || $v === null) continue;
            $params[$k] = (string)$v;
        }
        $params['sort'] = $key;
        $params['dir'] = $next;

        // Берём путь без query, т.к. add_query_arg() переиспользует REQUEST_URI
        // вместе с edit/edit_prize/edit_account, которые нам нужно убрать.
        $base = strtok($_SERVER['REQUEST_URI'] ?? admin_url('admin.php'), '?');
        $url = $base . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $arrow = $active ? ($dir === 'ASC' ? '▲' : '▼') : '<span class="wpz-sort-idle">↕</span>';
        $cls = 'wpz-sort' . ($active ? ' wpz-sort-active' : '');

        return '<a class="' . $cls . '" href="' . esc_url($url) . '">' . esc_html($label) . '<span class="wpz-sort-arrow">' . $arrow . '</span></a>';
    }

    /**
     * Кнопка «Отменить заказ». Возврат отзывает начисленный по заказу опыт
     * (см. WaterPrize_DB::sync_order_xp).
     */
    public static function cancel_order_button($order) {
        ob_start();
        ?>
        <form method="post" style="display:inline">
            <?php wp_nonce_field('wpz_action'); ?>
            <input type="hidden" name="action" value="update_order_status">
            <input type="hidden" name="order_id" value="<?php echo esc_attr($order['id']); ?>">
            <button type="submit" name="new_status" value="cancelled" class="button button-small"
                    onclick="return confirm('Отменить заказ? Начисленный по нему опыт будет списан.')">❌ Отменить</button>
        </form>
        <?php
        return ob_get_clean();
    }

    public static function handle_actions() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        if (!current_user_can('manage_options')) return;
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'wpz_action')) return;

        $action = $_POST['action'] ?? '';
        $db = self::db();

        switch ($action) {
            case 'add_balance':
                $tg = (int)($_POST['telegram_id'] ?? 0);
                $amt = (int)($_POST['amount'] ?? 0);
                $reason = sanitize_text_field($_POST['reason'] ?? '');
                if ($tg && $amt) {
                    $ok = $db->update_balance($tg, $amt);
                    $user = $db->get_user($tg);
                    if ($user) {
                        $db->insert(
                            'INSERT INTO points_log (user_id, amount, type, description) VALUES (?, ?, ?, ?)',
                            [$user['id'], $amt, 'admin', $reason ?: 'Начисление администратором']
                        );
                    }
                    wp_redirect(admin_url('admin.php?page=wpz-users&msg=' . urlencode($ok ? "Баланс {$user['name']} изменён на {$amt}" : 'Ошибка: пользователь не найден')));
                } else {
                    wp_redirect(admin_url('admin.php?page=wpz-users&err=1&msg=' . urlencode('Заполните Telegram ID и количество')));
                }
                exit;

            case 'add_tree_xp':
                $tg = (int)($_POST['telegram_id'] ?? 0);
                $xp = (int)($_POST['xp_amount'] ?? 0);
                $reason = sanitize_text_field($_POST['xp_reason'] ?? '');
                if ($tg && $xp) {
                    $ok = $db->update_tree_xp($tg, $xp);
                    $user = $db->get_user($tg);
                    $uname = $user ? $user['name'] : $tg;
                    if ($user && $xp > 0) {
                        $db->insert(
                            'INSERT INTO points_log (user_id, amount, type, description) VALUES (?, ?, ?, ?)',
                            [$user['id'], $xp, 'admin_xp', $reason ?: 'Начисление опыта администратором']
                        );
                    }
                    wp_redirect(admin_url('admin.php?page=wpz-users&msg=' . urlencode($ok ? "Опыт {$uname} изменён на {$xp} XP (уровень {$user['tree_level']})" : 'Ошибка: пользователь не найден')));
                } else {
                    wp_redirect(admin_url('admin.php?page=wpz-users&err=1&msg=' . urlencode('Заполните Telegram ID и количество XP')));
                }
                exit;

            case 'ban_user':
                $tg = (int)($_POST['telegram_id'] ?? 0);
                $reason = sanitize_text_field($_POST['ban_reason'] ?? '');
                if ($tg) {
                    $user = $db->get_user($tg);
                    if ($user) {
                        $db->ban_user($tg, $reason);
                        $uname = $user['name'] ?: $tg;
                        wp_redirect(admin_url('admin.php?page=wpz-users&msg=' . urlencode("Пользователь {$uname} заблокирован")));
                    } else {
                        wp_redirect(admin_url('admin.php?page=wpz-users&err=1&msg=' . urlencode('Пользователь не найден')));
                    }
                }
                exit;

            case 'unban_user':
                $tg = (int)($_POST['telegram_id'] ?? 0);
                if ($tg) {
                    $user = $db->get_user($tg);
                    if ($user) {
                        $db->unban_user($tg);
                        $uname = $user['name'] ?: $tg;
                        wp_redirect(admin_url('admin.php?page=wpz-users&msg=' . urlencode("Пользователь {$uname} разблокирован")));
                    } else {
                        wp_redirect(admin_url('admin.php?page=wpz-users&err=1&msg=' . urlencode('Пользователь не найден')));
                    }
                }
                exit;

            case 'delete_user':
                $tg = (int)($_POST['telegram_id'] ?? 0);
                if ($tg) {
                    $user = $db->get_user($tg);
                    if ($user) {
                        $db->delete_user($tg);
                        $uname = $user['name'] ?: $tg;
                        wp_redirect(admin_url('admin.php?page=wpz-users&msg=' . urlencode("Пользователь {$uname} удалён")));
                    } else {
                        wp_redirect(admin_url('admin.php?page=wpz-users&err=1&msg=' . urlencode('Пользователь не найден')));
                    }
                }
                exit;

            case 'add_codes':
                $codes_text = sanitize_textarea_field($_POST['codes'] ?? '');
                $batch = sanitize_text_field($_POST['batch'] ?? '');
                $codes = array_filter(array_map('trim', explode("\n", $codes_text)));
                if ($codes && $batch) {
                    $count = $db->add_codes($codes, $batch);
                    wp_redirect(admin_url('admin.php?page=wpz-codes&msg=' . urlencode("Добавлено {$count} кодов")));
                } else {
                    wp_redirect(admin_url('admin.php?page=wpz-codes&msg=' . urlencode('Заполните все поля')));
                }
                exit;

            case 'generate_bottles':
                $count = min(max((int)($_POST['count'] ?? 100), 1), 10000);
                $batch = sanitize_text_field($_POST['batch'] ?? '');
                $year = sanitize_text_field($_POST['year'] ?? '');
                if ($batch && $year) {
                    self::create_bottles($db, $count, $batch, $year);
                    wp_redirect(admin_url('admin.php?page=wpz-bottles&msg=' . urlencode("Создано {$count} бутылок")));
                }
                exit;

            case 'add_admin':
                $tg = (int)($_POST['telegram_id'] ?? 0);
                $name = sanitize_text_field($_POST['name'] ?? '');
                if ($tg) {
                    $db->add_admin($tg, $name);
                    wp_redirect(admin_url('admin.php?page=wpz-admins&msg=' . urlencode("Администратор {$tg} добавлен")));
                }
                exit;

            case 'remove_admin':
                $tg = (int)($_POST['telegram_id'] ?? 0);
                if ($tg) {
                    $db->remove_admin($tg);
                    wp_redirect(admin_url('admin.php?page=wpz-admins&msg=' . urlencode("Администратор удален")));
                }
                exit;

            case 'save_db_settings':
                update_option('wpz_db_host', sanitize_text_field($_POST['db_host'] ?? ''));
                update_option('wpz_db_port', sanitize_text_field($_POST['db_port'] ?? ''));
                update_option('wpz_db_name', sanitize_text_field($_POST['db_name'] ?? ''));
                update_option('wpz_db_user', sanitize_text_field($_POST['db_user'] ?? ''));
                update_option('wpz_db_pass', sanitize_text_field($_POST['db_pass'] ?? ''));
                $db->reconnect();
                $ok = $db->is_connected();
                $msg = $ok ? 'Подключение установлено!' : 'Ошибка подключения. Проверьте настройки.';
                wp_redirect(admin_url('admin.php?page=wpz-settings&msg=' . urlencode($msg)));
                exit;

            case 'save_splash_logo':
                $url = self::normalize_media_url($_POST['splash_logo_url'] ?? '');
                $db->set_setting('splash_logo_url', $url);
                $msg = $url ? 'Логотип обновлён!' : 'Логотип удалён.';
                wp_redirect(admin_url('admin.php?page=wpz-settings&msg=' . urlencode($msg)));
                exit;

            case 'save_daily_bonus':
                $points = (int)($_POST['daily_bonus_points'] ?? 0);
                if ($points < 0) {
                    $points = 0;
                }
                $db->set_setting('daily_bonus_points', (string)$points);
                $msg = $points > 0 ? "Ежедневный бонус обновлён: {$points} баллов" : 'Ежедневный бонус отключён.';
                wp_redirect(admin_url('admin.php?page=wpz-settings&msg=' . urlencode($msg)));
                exit;

            case 'update_order_status':
                $order_id = (int)($_POST['order_id'] ?? 0);
                $new_status = sanitize_text_field($_POST['new_status'] ?? '');
                $allowed = ['pending', 'approved', 'shipped', 'completed', 'cancelled'];
                if ($order_id && in_array($new_status, $allowed)) {
                    $db->update_order_status($order_id, $new_status);
                    // Возврат (cancel) отзывает опыт по заказу, подтверждение — возвращает
                    $db->sync_order_xp($order_id);
                }
                wp_redirect(admin_url('admin.php?page=wpz-orders&msg=' . urlencode("Заказ #{$order_id} обновлён")));
                exit;

            // ─── Prizes CRUD ───────────────────────
            case 'add_prize':
                $prize_image = self::normalize_media_url(esc_url_raw(trim((string)($_POST['image_url'] ?? ''))));
                if ($prize_image === '') {
                    $prize_image = self::default_prize_image((int)($_POST['category_id'] ?? 0));
                }
                $db->add_prize(
                    sanitize_text_field($_POST['name'] ?? ''),
                    sanitize_textarea_field($_POST['description'] ?? ''),
                    $prize_image,
                    (int)($_POST['price_points'] ?? 0),
                    (int)($_POST['category_id'] ?? 0),
                    isset($_POST['active']) ? 1 : 0
                );
                wp_redirect(admin_url('admin.php?page=wpz-partners&tab=prizes&msg=' . urlencode('Купон добавлен')));
                exit;

            case 'update_prize':
                $prize_image = self::normalize_media_url(esc_url_raw(trim((string)($_POST['image_url'] ?? ''))));
                if ($prize_image === '') {
                    $prize_image = self::default_prize_image((int)($_POST['category_id'] ?? 0));
                }
                $db->update_prize(
                    (int)($_POST['prize_id'] ?? 0),
                    sanitize_text_field($_POST['name'] ?? ''),
                    sanitize_textarea_field($_POST['description'] ?? ''),
                    $prize_image,
                    (int)($_POST['price_points'] ?? 0),
                    (int)($_POST['category_id'] ?? 0),
                    isset($_POST['active']) ? 1 : 0
                );
                wp_redirect(admin_url('admin.php?page=wpz-partners&tab=prizes&msg=' . urlencode('Купон обновлён')));
                exit;

            case 'delete_prize':
                $db->delete_prize((int)($_POST['prize_id'] ?? 0));
                wp_redirect(admin_url('admin.php?page=wpz-partners&tab=prizes&msg=' . urlencode('Купон удалён')));
                exit;

            // ─── Partner Accounts CRUD ──────────────
            case 'add_partner_account':
                $db->add_partner_account(
                    (int)($_POST['telegram_id'] ?? 0),
                    sanitize_text_field($_POST['account_name'] ?? ''),
                    (int)($_POST['category_id'] ?? 0)
                );
                wp_redirect(admin_url('admin.php?page=wpz-partners&tab=accounts&msg=' . urlencode('Партнёр добавлен')));
                exit;

            case 'update_partner_account':
                $db->update_partner_account(
                    (int)($_POST['account_id'] ?? 0),
                    (int)($_POST['telegram_id'] ?? 0),
                    sanitize_text_field($_POST['account_name'] ?? ''),
                    (int)($_POST['category_id'] ?? 0),
                    isset($_POST['is_active'])
                );
                wp_redirect(admin_url('admin.php?page=wpz-partners&tab=accounts&msg=' . urlencode('Партнёр обновлён')));
                exit;

            case 'delete_partner_account':
                $db->delete_partner_account((int)($_POST['account_id'] ?? 0));
                wp_redirect(admin_url('admin.php?page=wpz-partners&tab=accounts&msg=' . urlencode('Партнёр удалён')));
                exit;

            // ─── Settlements (взаиморасчёты) ─────────
            case 'add_settlement':
                $account_id = (int)($_POST['account_id'] ?? 0);
                $period = sanitize_text_field($_POST['period_month'] ?? '');
                $amount = (int)($_POST['amount'] ?? 0);
                $comment = sanitize_text_field($_POST['comment'] ?? '');
                $back = sanitize_text_field($_POST['report_month'] ?? '');
                if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $back)) {
                    $back = date('Y-m');
                }
                if ($account_id && $amount > 0 && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
                    $login = wp_get_current_user()->user_login;
                    $db->add_settlement($account_id, $period . '-01', $amount, $comment, $login);
                    wp_redirect(admin_url('admin.php?page=wpz-monthly-report&month=' . urlencode($back) . '&msg=' . urlencode('Оплата записана')));
                } else {
                    wp_redirect(admin_url('admin.php?page=wpz-monthly-report&month=' . urlencode($back) . '&err=1&msg=' . urlencode('Заполните партнёра, месяц и сумму')));
                }
                exit;

            case 'delete_settlement':
                $sid = (int)($_POST['settlement_id'] ?? 0);
                $back = sanitize_text_field($_POST['report_month'] ?? '');
                if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $back)) {
                    $back = date('Y-m');
                }
                if ($sid) {
                    $db->delete_settlement($sid);
                }
                wp_redirect(admin_url('admin.php?page=wpz-monthly-report&month=' . urlencode($back) . '&msg=' . urlencode('Запись журнала удалена')));
                exit;

            // ─── Categories CRUD ───────────────────
            case 'add_category':
                $db->add_category(
                    sanitize_text_field($_POST['title'] ?? ''),
                    sanitize_text_field($_POST['subtitle'] ?? ''),
                    sanitize_textarea_field($_POST['description'] ?? ''),
                    sanitize_text_field($_POST['icon'] ?? ''),
                    sanitize_text_field($_POST['color'] ?? '#2271b1'),
                    (int)($_POST['sort_order'] ?? 0),
                    isset($_POST['is_active']),
                    (int)($_POST['scan_points'] ?? 10),
                    self::normalize_media_url($_POST['image_url'] ?? ''),
                    self::normalize_media_url($_POST['logo_url'] ?? ''),
                    esc_url_raw($_POST['website'] ?? ''),
                    esc_url_raw($_POST['telegram'] ?? ''),
                    sanitize_textarea_field($_POST['info'] ?? ''),
                    (int)($_POST['gift_threshold_xp'] ?? 0),
                    (int)($_POST['gift_limit'] ?? 0)
                );
                wp_redirect(admin_url('admin.php?page=wpz-partners&msg=' . urlencode('Партнёр добавлен')));
                exit;

            case 'update_category':
                $db->update_category(
                    (int)($_POST['category_id'] ?? 0),
                    sanitize_text_field($_POST['title'] ?? ''),
                    sanitize_text_field($_POST['subtitle'] ?? ''),
                    sanitize_textarea_field($_POST['description'] ?? ''),
                    sanitize_text_field($_POST['icon'] ?? ''),
                    sanitize_text_field($_POST['color'] ?? '#2271b1'),
                    (int)($_POST['sort_order'] ?? 0),
                    isset($_POST['is_active']),
                    (int)($_POST['scan_points'] ?? 10),
                    self::normalize_media_url($_POST['image_url'] ?? ''),
                    self::normalize_media_url($_POST['logo_url'] ?? ''),
                    esc_url_raw($_POST['website'] ?? ''),
                    esc_url_raw($_POST['telegram'] ?? ''),
                    sanitize_textarea_field($_POST['info'] ?? ''),
                    (int)($_POST['gift_threshold_xp'] ?? 0),
                    (int)($_POST['gift_limit'] ?? 0)
                );
                wp_redirect(admin_url('admin.php?page=wpz-partners&msg=' . urlencode('Партнёр обновлён')));
                exit;

            case 'delete_category':
                $db->delete_category((int)($_POST['category_id'] ?? 0));
                wp_redirect(admin_url('admin.php?page=wpz-partners&msg=' . urlencode('Партнёр удалён')));
                exit;

            case 'regenerate_qr':
                $cat_id = (int)($_POST['category_id'] ?? 0);
                if ($cat_id) {
                    $new_qr = $db->regenerate_qr_code($cat_id);
                    wp_redirect(admin_url('admin.php?page=wpz-partners&edit=' . $cat_id . '&msg=' . urlencode('QR-код обновлён')));
                } else {
                    wp_redirect(admin_url('admin.php?page=wpz-partners&err=1&msg=' . urlencode('Партнёр не найден')));
                }
                exit;

            case 'close_support_chat':
                $chat_id = (int)($_POST['chat_id'] ?? 0);
                if ($chat_id) {
                    $db->close_support_chat($chat_id);
                }
                wp_redirect(admin_url('admin.php?page=wpz-support&chat=' . $chat_id . '&msg=' . urlencode('Чат закрыт')));
                exit;

            case 'reply_support_chat':
                $chat_id = (int)($_POST['chat_id'] ?? 0);
                $message = sanitize_textarea_field($_POST['message'] ?? '');
                if ($chat_id && $message) {
                    $db->send_support_message($chat_id, 'admin', $message);
                    try {
                        $chat = $db->get_support_chat($chat_id);
                        if ($chat && !empty($chat['user_telegram_id'])) {
                            $prod_env = file_get_contents(ABSPATH . '../prod.env');
                            $bot_token = '';
                            if ($prod_env && preg_match('/BOT_TOKEN=(.+)/', $prod_env, $m)) {
                                $bot_token = trim($m[1]);
                            }
                            if ($bot_token) {
                                $text = "💬 Ответ поддержки:\n\n" . $message;
                                wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
                                    'body' => json_encode([
                                        'chat_id' => $chat['user_telegram_id'],
                                        'text' => $text,
                                    ]),
                                    'headers' => ['Content-Type' => 'application/json'],
                                    'timeout' => 10,
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        error_log('Support reply error: ' . $e->getMessage());
                    }
                }
                wp_redirect(admin_url('admin.php?page=wpz-support&chat=' . $chat_id . '&msg=' . urlencode('Ответ отправлен')));
                exit;

            case 'save_bot_settings':
                $db = self::db();
                $reward_keys = ['scan_balance','scan_xp','partner_scan_default','gift_min','gift_max','conversion_multiplier','expired_conversion_multiplier','level_up_bonus','tree_threshold_2','tree_threshold_3','tree_threshold_4','tree_threshold_5','tree_threshold_6'];
                $message_keys = ['msg_welcome','msg_scan_success_1','msg_scan_success_2','msg_gift_prompt','msg_balance','msg_stats','msg_donation_success','msg_exchange_success','msg_level_up'];
                if (isset($_POST['bot_username'])) {
                    $db->set_setting('bot_username', sanitize_text_field($_POST['bot_username']));
                }
                if (isset($_POST['partner_referral_reward'])) {
                    $db->set_setting('partner_referral_reward', sanitize_text_field($_POST['partner_referral_reward']));
                }
                if (isset($_POST['partner_referral_value'])) {
                    $db->set_setting('partner_referral_value', sanitize_text_field($_POST['partner_referral_value']));
                }
                foreach ($reward_keys as $k) {
                    if (isset($_POST[$k])) {
                        $db->set_setting($k, sanitize_text_field($_POST[$k]));
                    }
                }
                foreach ($message_keys as $k) {
                    if (isset($_POST[$k])) {
                        $db->set_setting($k, wp_kses_post($_POST[$k]));
                    }
                }
                wp_redirect(admin_url('admin.php?page=wpz-bot-settings&msg=' . urlencode('Настройки бота сохранены')));
                exit;

            case 'save_gift_settings':
                $db->set_setting('gift_prize_id', (int)($_POST['gift_prize_id'] ?? 0));
                $db->set_setting('gift_ttl_days', max(0, (int)($_POST['gift_ttl_days'] ?? 30)));
                wp_redirect(admin_url('admin.php?page=wpz-gifts&msg=' . urlencode('Настройки подарка сохранены')));
                exit;
        }
    }

    private static function create_bottles($db, $count, $batch, $year) {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $existing = $db->query(
            "SELECT bottle_id FROM bottles WHERE year = ? AND batch = ? ORDER BY id DESC LIMIT 1",
            [$year, $batch]
        );
        $seq = 1;
        if ($existing) {
            $parts = explode('-', $existing[0]['bottle_id']);
            $seq = (int)($parts[3] ?? 0) + 1;
        }
        for ($i = 0; $i < $count; $i++) {
            $rand = '';
            for ($j = 0; $j < 4; $j++) $rand .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            $bottle_id = sprintf("BTL-%s-%s-%04d-%s", $year, $batch, $seq + $i, $rand);
            $db->insert('INSERT INTO bottles (bottle_id, batch, year) VALUES (?, ?, ?)', [$bottle_id, $batch, $year]);
        }
    }

    // ─── Dashboard ────────────────────────────────────
    public static function dashboard() {
        $db = self::db();

        // CSV export
        if (!empty($_GET['export'])) {
            self::handle_export($_GET['export'], $db);
            exit;
        }

        $stats = $db->get_stats();
        $balance = $db->get_balance_stats();
        $points = $db->get_points_stats();
        $notif_stats = $db->get_notifications_stats();
        $online = $db->count_online(15);
        $reg_chart = $db->get_registrations_chart(30);
        $pts_chart = $db->get_points_chart(30);
        $notif_chart = $db->get_notifications_chart();
        $bottles_chart = $db->get_bottles_chart();
        $top_users = $db->get_top_users(10);
        $activity = $db->get_recent_activity(20);
        include __DIR__ . '/../templates/dashboard.php';
    }

    private static function handle_export($type, $db) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="waterprize_' . $type . '_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

        switch ($type) {
            case 'users':
                fputcsv($out, ['ID', 'Telegram ID', 'Имя', 'Username', 'Телефон', 'Баланс', 'XP', 'Уровень', 'Забанен', 'Причина бана', 'Дата'], ';');
                foreach ($db->get_users('', 10000) as $r) {
                    fputcsv($out, [$r['id'], $r['telegram_id'], $r['name'], $r['username'], $r['phone'], $r['balance'], $r['tree_xp'], $r['tree_level'], $r['is_banned'] ? 'Да' : 'Нет', $r['ban_reason'] ?? '', $r['created_at']], ';');
                }
                break;
            case 'points':
                fputcsv($out, ['ID', 'Пользователь', 'Telegram ID', 'Сумма', 'Тип', 'Описание', 'Дата'], ';');
                $rows = $db->query("SELECT p.*, u.name, u.telegram_id FROM points_log p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.id DESC");
                foreach ($rows as $r) {
                    fputcsv($out, [$r['id'], $r['name'], $r['telegram_id'], $r['amount'], $r['type'], $r['description'], $r['created_at']], ';');
                }
                break;
            case 'codes':
                fputcsv($out, ['ID', 'Код', 'Партия', 'Статус', 'Победитель ID', 'Дата'], ';');
                foreach ($db->get_codes(10000) as $r) {
                    fputcsv($out, [$r['id'], $r['code'], $r['batch'], $r['status'], $r['winner_id'], $r['created_at']], ';');
                }
                break;
            case 'bottles':
                fputcsv($out, ['ID', 'Бутылка ID', 'Партия', 'Год', 'Назначена', 'Дата'], ';');
                foreach ($db->get_bottles('', 'id', 'DESC', 10000) as $r) {
                    fputcsv($out, [$r['id'], $r['bottle_id'], $r['batch'], $r['year'], $r['assigned_to'], $r['created_at']], ';');
                }
                break;
        }
        fclose($out);
    }

    // ─── Users ────────────────────────────────────────
    public static function users() {
        // ── User detail card (?page=wpz-users&user=<id>) ──
        if (!empty($_GET['user'])) {
            $detail_user = self::db()->get_user_by_id((int)$_GET['user']);
            $detail_history = $detail_user ? self::db()->get_user_history($detail_user['id']) : [];
            include __DIR__ . '/../templates/user-detail.php';
            return;
        }

        $search = sanitize_text_field($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['paged'] ?? 1));
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        $total = self::db()->count_users($search);
        $users = self::db()->get_users($search, $per_page, $offset);
        $total_pages = ceil($total / $per_page);
        $stats = self::db()->get_users_stats();
        $reg_chart = self::db()->get_users_registrations_chart(30);
        $top_balances = self::db()->get_top_balances(5);
        $by_level = self::db()->get_users_by_level();
        $banned_count = self::db()->count_users_banned();
        include __DIR__ . '/../templates/users.php';
    }

    // ─── Codes ────────────────────────────────────────
    public static function codes() {
        $codes = self::db()->get_codes(500);
        $stats = [
            'total' => self::db()->count_codes(),
            'active' => self::db()->count_active_codes(),
        ];
        include __DIR__ . '/../templates/codes.php';
    }

    // ─── Bottles ──────────────────────────────────────
    public static function bottles() {
        // PDF export (GET, no nonce — link download)
        if (!empty($_GET['export_batch']) && !empty($_GET['export_year'])) {
            if (!current_user_can('manage_options')) wp_die('Forbidden');
            $batch = sanitize_text_field($_GET['export_batch']);
            $year = sanitize_text_field($_GET['export_year']);
            WaterPrize_PDF_Export::export_batch($batch, $year);
        }

        $db = self::db();
        $search = sanitize_text_field($_GET['search'] ?? '');
        $sort = sanitize_text_field($_GET['sort'] ?? 'id');
        $dir = sanitize_text_field($_GET['dir'] ?? 'DESC');
        $bottles = $db->get_bottles($search, $sort, $dir, 200);
        $batches = $db->get_batches();
        $total = $db->count_bottles($search);
        $edit_bottle = null;
        if (!empty($_GET['edit'])) {
            $edit_bottle = $db->get_bottle((int)$_GET['edit']);
        }
        include __DIR__ . '/../templates/bottles.php';
    }

    // ─── Raffles ──────────────────────────────────────
    public static function raffles() {
        $raffles = self::db()->get_raffles(200);
        include __DIR__ . '/../templates/raffles.php';
    }

    // ─── Prizes ───────────────────────────────────────
    public static function prizes() {
        $db = self::db();
        $prizes = $db->get_prizes();
        $categories = $db->get_categories();
        $edit_prize = null;
        if (!empty($_GET['edit'])) {
            $edit_prize = $db->get_prize((int)$_GET['edit']);
        }
        include __DIR__ . '/../templates/prizes.php';
    }

    // ─── Categories (Partners) ────────────────────────
    public static function categories() {
        $db = self::db();
        $categories = $db->get_categories();
        $edit_category = null;
        if (!empty($_GET['edit'])) {
            $edit_category = $db->get_category((int)$_GET['edit']);
        }
        include __DIR__ . '/../templates/categories.php';
    }

    // ─── Analytics ────────────────────────────────────
    public static function analytics() {
        $db = self::db();
        $period = sanitize_text_field($_GET['period'] ?? 'day');
        $from = sanitize_text_field($_GET['from'] ?? '');
        $to = sanitize_text_field($_GET['to'] ?? '');
        $chart = sanitize_text_field($_GET['chart'] ?? 'registrations');
        $tab = sanitize_text_field($_GET['tab'] ?? 'stats');

        $reg_chart = $db->get_registrations_analytics($period, $from, $to);
        $pts_chart = $db->get_points_analytics($period, $from, $to);
        $scans_chart = $db->get_scans_analytics($period, $from, $to);

        $journey_stats = $db->get_journey_stats();
        $journeys = $db->get_user_journeys_with_details(200);

        include __DIR__ . '/../templates/analytics.php';
    }

    // ─── Orders ───────────────────────────────────────
    public static function orders() {
        $orders = self::db()->get_all_orders(500);
        include __DIR__ . '/../templates/orders.php';
    }

    // ─── Admins ───────────────────────────────────────
    public static function admins() {
        $admins = self::db()->get_admins();
        include __DIR__ . '/../templates/admins.php';
    }

    // ─── Settings ─────────────────────────────────────
    public static function settings() {
        include __DIR__ . '/../templates/settings.php';
    }

    // ─── Bot Settings ────────────────────────────────
    public static function bot_settings() {
        include __DIR__ . '/../templates/bot-settings.php';
    }

    // ─── Подарки от любимого партнёра ─────────────────
    public static function gifts() {
        $db = self::db();
        $partner_id = (int)($_GET['g_partner'] ?? 0);
        $gifts = $db->get_gifts($partner_id);
        $stats = $db->get_gift_stats();
        $favorites = $db->get_favorite_partners();
        $summary = $db->get_partner_xp_summary();
        $categories = $db->get_categories('title', 'ASC');
        $prizes = $db->get_prizes();
        $values = [
            'gift_prize_id' => (int)($db->get_setting('gift_prize_id') ?: 0),
            'gift_ttl_days' => (int)($db->get_setting('gift_ttl_days') ?: 30),
        ];
        include __DIR__ . '/../templates/gifts.php';
    }

    // ─── Unified Partners Page ────────────────────────
    public static function partners() {
        $db = self::db();
        $tab = sanitize_text_field($_GET['tab'] ?? 'list');

        $sort = sanitize_text_field($_GET['sort'] ?? '');
        $dir_req = strtoupper(sanitize_text_field($_GET['dir'] ?? ''));
        $dir = $dir_req === 'DESC' ? 'DESC' : ($dir_req === 'ASC' ? 'ASC' : '');

        // Дефолтные направления сортировки, когда параметры не заданы
        $list_dir = $dir ?: 'ASC';
        $acc_dir = $dir ?: 'DESC';
        $stats_dir = $dir ?: 'DESC';

        // Partner list data
        $categories = $db->get_categories($tab === 'list' && $sort ? $sort : 'sort_order', $list_dir);
        $edit_category = null;
        if (!empty($_GET['edit'])) {
            $edit_category = $db->get_category((int)$_GET['edit']);
        }

        // Partner accounts data
        $accounts = $db->get_partner_accounts($tab === 'accounts' && $sort ? $sort : 'created_at', $acc_dir);
        $edit_account = null;
        if (!empty($_GET['edit_account'])) {
            $edit_account = $db->get_partner_account((int)$_GET['edit_account']);
        }

        // Prizes data (optional filter by partner/brand)
        $prize_partner = $tab === 'prizes' ? (int)($_GET['p_partner'] ?? 0) : 0;
        $prizes = $db->get_prizes($prize_partner);
        $edit_prize = null;
        if (!empty($_GET['edit_prize'])) {
            $edit_prize = $db->get_prize((int)$_GET['edit_prize']);
        }

        // Stats data
        $period = sanitize_text_field($_GET['period'] ?? 'day');
        $from = sanitize_text_field($_GET['from'] ?? '');
        $to = sanitize_text_field($_GET['to'] ?? '');
        $category_id = (int)($_GET['partner_id'] ?? 0);
        $summary = $db->get_partner_stats_summary($tab === 'stats' && $sort ? $sort : 'total_scans', $stats_dir);
        $chart_data = $db->get_partner_scans_chart($period, $from, $to, $category_id);
        $detail = [];
        if ($category_id > 0) {
            $detail = $db->get_partner_scans_detail($category_id);
        }
        $top_users = $db->get_top_partners_users(20);

        // Movement (path) data
        $m_search = sanitize_text_field($_GET['m_search'] ?? '');
        $m_partner = (int)($_GET['m_partner'] ?? 0);
        $m_status = sanitize_text_field($_GET['m_status'] ?? '');
        $movement = $tab === 'movement' ? $db->get_user_movement($m_search, $m_partner, $m_status, 500) : [];
        $movement_summary = $tab === 'movement' ? $db->get_movement_summary($m_partner) : [];

        // Orders data (optional filter by partner/brand)
        $order_partner = $tab === 'orders' ? (int)($_GET['o_partner'] ?? 0) : 0;
        $all_orders = $db->get_all_orders(500, 0, $order_partner);
        $order_search = sanitize_text_field($_GET['order_search'] ?? '');
        $order_status = sanitize_text_field($_GET['order_status'] ?? '');
        if ($order_search) {
            $all_orders = array_filter($all_orders, function($o) use ($order_search) {
                return stripos($o['user_name'] ?? '', $order_search) !== false
                    || stripos($o['prize_name'] ?? '', $order_search) !== false
                    || stripos($o['id'] ?? '', $order_search) !== false;
            });
        }
        if ($order_status) {
            $all_orders = array_filter($all_orders, fn($o) => $o['status'] === $order_status);
        }

        include __DIR__ . '/../templates/partners.php';
    }

    // ─── Partner Stats ──────────────────────────────────
    public static function partner_stats() {
        $db = self::db();
        $period = sanitize_text_field($_GET['period'] ?? 'day');
        $from = sanitize_text_field($_GET['from'] ?? '');
        $to = sanitize_text_field($_GET['to'] ?? '');
        $category_id = (int)($_GET['partner_id'] ?? 0);

        $summary = $db->get_partner_stats_summary();
        $chart_data = $db->get_partner_scans_chart($period, $from, $to, $category_id);
        $detail = [];
        if ($category_id > 0) {
            $detail = $db->get_partner_scans_detail($category_id);
        }
        $top_users = $db->get_top_partners_users(20);
        include __DIR__ . '/../templates/partners_stats.php';
    }

    // ─── Partner Cabinet (KPI) ─────────────────────────
    public static function partner_cabinet() {
        $db = self::db();
        $stats = $db->get_partner_cabinet_stats();
        $detail = null;
        $detail_coupons = [];
        $detail_payments = [];
        $detail_referred = [];
        if (!empty($_GET['account'])) {
            $detail = $db->get_partner_account((int)$_GET['account']);
            if ($detail) {
                $detail_coupons = $db->get_partner_cabinet_used_coupons($detail['id']);
                $detail_payments = $db->get_partner_cabinet_payments($detail['id']);
                $detail_referred = $db->get_partner_cabinet_referred($detail['id']);
            }
        }
        include __DIR__ . '/../templates/partner-cabinet.php';
    }

    // ─── Monthly Report (купоны + взаиморасчёты) ──────
    public static function monthly_report() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        $db = self::db();

        $month = sanitize_text_field($_GET['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = date('Y-m');
        }
        $month_start = $month . '-01';
        // Границы периода — в UTC (used_at хранится как UTC)
        $from_ts = $month . '-01 00:00:00+00';
        $to_ts = date('Y-m', strtotime($month_start . ' +1 month')) . '-01 00:00:00+00';

        if (!empty($_GET['export'])) {
            self::handle_monthly_export(sanitize_text_field($_GET['export']), $month, $month_start, $from_ts, $to_ts, $db);
            exit;
        }

        $usage = $db->get_monthly_coupon_usage($from_ts, $to_ts);
        $settlements = $db->get_monthly_settlements($month_start, $from_ts, $to_ts);
        $journal = $db->get_settlements($month_start);
        $accounts = $db->get_partner_accounts();

        $totals = [
            'cnt' => count($usage),
            'nominal' => 0,
            'commission' => 0,
            'used_month' => 0,
            'accrued_month' => 0,
            'paid_month' => 0,
            'accrued_total' => 0,
            'paid_total' => 0,
            'debt' => 0,
        ];
        foreach ($usage as $u) {
            $totals['nominal'] += (int)($u['nominal'] ?? 0);
            $totals['commission'] += (int)($u['commission'] ?? 0);
        }
        foreach ($settlements as $s) {
            $totals['used_month'] += (int)$s['used_month'];
            $totals['accrued_month'] += (int)$s['accrued_month'];
            $totals['paid_month'] += (int)$s['paid_month'];
            $totals['accrued_total'] += (int)$s['accrued_total'];
            $totals['paid_total'] += (int)$s['paid_total'];
            $totals['debt'] += (int)$s['debt'];
        }

        // Последние 24 месяца для селектора
        $months = [];
        for ($i = 0; $i < 24; $i++) {
            $months[] = date('Y-m', strtotime(date('Y-m-01') . " -{$i} months"));
        }

        if (!empty($_GET['print'])) {
            include __DIR__ . '/../templates/monthly-report-print.php';
            exit;
        }

        include __DIR__ . '/../templates/monthly-report.php';
    }

    private static function handle_monthly_export($type, $month, $month_start, $from_ts, $to_ts, $db) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="waterprize_' . $type . '_' . $month . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

        switch ($type) {
            case 'coupons':
                fputcsv($out, [
                    'ID купона', 'Дата активации', 'QR-код', 'Пользователь', 'Telegram ID',
                    'Купон', 'Номинал', 'Комиссия 10%', 'Категория',
                    'Активировал (партнёр)', 'Telegram партнёра', 'Источник пользователя',
                ], ';');
                foreach ($db->get_monthly_coupon_usage($from_ts, $to_ts) as $r) {
                    fputcsv($out, [
                        $r['id'],
                        $r['used_at'],
                        $r['qr_code'],
                        $r['user_name'],
                        $r['telegram_id'],
                        $r['prize_name'],
                        $r['nominal'],
                        $r['commission'],
                        $r['category_title'],
                        $r['activator_name'],
                        $r['activator_tg'],
                        $r['source_title'],
                    ], ';');
                }
                break;

            case 'settlements':
                fputcsv($out, [
                    'Партнёр', 'Telegram ID', 'Категория', 'Активен',
                    'Использовано купонов за месяц', 'Начислено за месяц (10%)',
                    'Оплачено за месяц', 'Начислено всего', 'Оплачено всего', 'Долг ISTOK',
                ], ';');
                foreach ($db->get_monthly_settlements($month_start, $from_ts, $to_ts) as $r) {
                    fputcsv($out, [
                        $r['name'],
                        $r['telegram_id'],
                        $r['category_title'],
                        $r['is_active'] ? 'Да' : 'Нет',
                        $r['used_month'],
                        $r['accrued_month'],
                        $r['paid_month'],
                        $r['accrued_total'],
                        $r['paid_total'],
                        $r['debt'],
                    ], ';');
                }
                break;
        }
        fclose($out);
    }

    // ─── Scans ────────────────────────────────────────
    public static function scans() {
        $scans = self::db()->get_scans(200);
        $total = self::db()->count_scans();
        include __DIR__ . '/../templates/scans.php';
    }

    // ─── Notifications ────────────────────────────────
    public static function notifications() {
        $notifications = self::db()->get_notifications(200);
        $total = self::db()->count_notifications();
        include __DIR__ . '/../templates/notifications.php';
    }

    // ─── User QR Activations ──────────────────────────
    public static function user_qr_activations() {
        $activations = self::db()->get_user_qr_activations(200);
        $total = self::db()->count_user_qr_activations();
        include __DIR__ . '/../templates/user_qr_activations.php';
    }

    // ─── Support Chat ─────────────────────────────────
    public static function support() {
        $db = self::db();
        $chats = $db->get_support_chats(200);
        $total = $db->count_support_chats();
        include __DIR__ . '/../templates/support.php';
    }
}
