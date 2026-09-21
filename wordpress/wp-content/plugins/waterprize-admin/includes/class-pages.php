<?php
/**
 * Admin page renderers
 */
class WaterPrize_Pages {

    private static function db() { return WaterPrize_DB::instance(); }

    public static function flash_message() {
        if (!empty($_GET['msg'])) {
            $type = isset($_GET['err']) ? 'error' : 'success';
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($_GET['msg']) . '</p></div>';
        }
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
                $url = esc_url_raw($_POST['splash_logo_url'] ?? '');
                $db->set_setting('splash_logo_url', $url);
                $msg = $url ? 'Логотип обновлён!' : 'Логотип удалён.';
                wp_redirect(admin_url('admin.php?page=wpz-settings&msg=' . urlencode($msg)));
                exit;

            case 'update_order_status':
                $order_id = (int)($_POST['order_id'] ?? 0);
                $new_status = sanitize_text_field($_POST['new_status'] ?? '');
                $allowed = ['pending', 'approved', 'shipped', 'completed', 'cancelled'];
                if ($order_id && in_array($new_status, $allowed)) {
                    $db->update_order_status($order_id, $new_status);
                }
                wp_redirect(admin_url('admin.php?page=wpz-orders&msg=' . urlencode("Заказ #{$order_id} обновлён")));
                exit;

            // ─── Prizes CRUD ───────────────────────
            case 'add_prize':
                $db->add_prize(
                    sanitize_text_field($_POST['name'] ?? ''),
                    sanitize_textarea_field($_POST['description'] ?? ''),
                    esc_url_raw($_POST['image_url'] ?? ''),
                    (int)($_POST['price_points'] ?? 0),
                    (int)($_POST['category_id'] ?? 0),
                    isset($_POST['active']) ? 1 : 0
                );
                wp_redirect(admin_url('admin.php?page=wpz-partners&tab=prizes&msg=' . urlencode('Приз добавлен')));
                exit;

            case 'update_prize':
                $db->update_prize(
                    (int)($_POST['prize_id'] ?? 0),
                    sanitize_text_field($_POST['name'] ?? ''),
                    sanitize_textarea_field($_POST['description'] ?? ''),
                    esc_url_raw($_POST['image_url'] ?? ''),
                    (int)($_POST['price_points'] ?? 0),
                    (int)($_POST['category_id'] ?? 0),
                    isset($_POST['active']) ? 1 : 0
                );
                wp_redirect(admin_url('admin.php?page=wpz-partners&tab=prizes&msg=' . urlencode('Приз обновлён')));
                exit;

            case 'delete_prize':
                $db->delete_prize((int)($_POST['prize_id'] ?? 0));
                wp_redirect(admin_url('admin.php?page=wpz-partners&tab=prizes&msg=' . urlencode('Приз удалён')));
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
                    esc_url_raw($_POST['image_url'] ?? ''),
                    esc_url_raw($_POST['logo_url'] ?? ''),
                    esc_url_raw($_POST['website'] ?? ''),
                    esc_url_raw($_POST['telegram'] ?? ''),
                    sanitize_textarea_field($_POST['info'] ?? '')
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
                    esc_url_raw($_POST['image_url'] ?? ''),
                    esc_url_raw($_POST['logo_url'] ?? ''),
                    esc_url_raw($_POST['website'] ?? ''),
                    esc_url_raw($_POST['telegram'] ?? ''),
                    sanitize_textarea_field($_POST['info'] ?? '')
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

            // ─── Shop Prizes CRUD ─────────────────────
            case 'add_shop_prize':
                $shop_cat = $db->get_category_by_title('Истокъ');
                if (!$shop_cat) {
                    wp_redirect(admin_url('admin.php?page=wpz-shop&err=1&msg=' . urlencode('Категория «Истокъ» не найдена')));
                    exit;
                }
                $db->add_prize(
                    sanitize_text_field($_POST['name'] ?? ''),
                    sanitize_textarea_field($_POST['description'] ?? ''),
                    esc_url_raw($_POST['image_url'] ?? ''),
                    (int)($_POST['price_points'] ?? 0),
                    $shop_cat['id'],
                    isset($_POST['active']) ? 1 : 0
                );
                wp_redirect(admin_url('admin.php?page=wpz-shop&tab=products&msg=' . urlencode('Товар добавлен')));
                exit;

            case 'update_shop_prize':
                $db->update_prize(
                    (int)($_POST['prize_id'] ?? 0),
                    sanitize_text_field($_POST['name'] ?? ''),
                    sanitize_textarea_field($_POST['description'] ?? ''),
                    esc_url_raw($_POST['image_url'] ?? ''),
                    (int)($_POST['price_points'] ?? 0),
                    (int)($_POST['category_id'] ?? 0),
                    isset($_POST['active']) ? 1 : 0
                );
                wp_redirect(admin_url('admin.php?page=wpz-shop&tab=products&msg=' . urlencode('Товар обновлён')));
                exit;

            case 'delete_shop_prize':
                $db->delete_prize((int)($_POST['prize_id'] ?? 0));
                wp_redirect(admin_url('admin.php?page=wpz-shop&tab=products&msg=' . urlencode('Товар удалён')));
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

        $reg_chart = $db->get_registrations_analytics($period, $from, $to);
        $pts_chart = $db->get_points_analytics($period, $from, $to);
        $scans_chart = $db->get_scans_analytics($period, $from, $to);
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

    // ─── Unified Partners Page ────────────────────────
    public static function partners() {
        $db = self::db();
        $tab = sanitize_text_field($_GET['tab'] ?? 'list');

        // Partner list data
        $categories = $db->get_categories();
        $edit_category = null;
        if (!empty($_GET['edit'])) {
            $edit_category = $db->get_category((int)$_GET['edit']);
        }

        // Prizes data
        $prizes = $db->get_prizes();
        $edit_prize = null;
        if (!empty($_GET['edit_prize'])) {
            $edit_prize = $db->get_prize((int)$_GET['edit_prize']);
        }

        // Stats data
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

        include __DIR__ . '/../templates/partners.php';
    }

    // ─── Shop Page ────────────────────────────────────
    public static function shop() {
        $db = self::db();
        $tab = sanitize_text_field($_GET['tab'] ?? 'products');
        $search = sanitize_text_field($_GET['search'] ?? '');
        $filter_status = sanitize_text_field($_GET['status'] ?? '');

        // Get or auto-create the "Истокъ" category
        $shop_cat = $db->get_category_by_title('Истокъ');
        if (!$shop_cat) {
            $db->add_category('Истокъ', 'Собственные товары', 'Товары магазина Истокъ', '💧', '#1E88E5', 0, true, 0);
            $shop_cat = $db->get_category_by_title('Истокъ');
        }
        $shop_category_id = $shop_cat ? $shop_cat['id'] : 0;

        // Products tab
        $all_prizes = $db->get_prizes();
        $shop_prizes = array_filter($all_prizes, fn($p) => $p['category_id'] == $shop_category_id);

        // Search filter
        if ($search) {
            $shop_prizes = array_filter($shop_prizes, fn($p) => stripos($p['name'], $search) !== false || stripos($p['description'] ?? '', $search) !== false);
        }
        // Status filter
        if ($filter_status === 'active') {
            $shop_prizes = array_filter($shop_prizes, fn($p) => $p['active']);
        } elseif ($filter_status === 'inactive') {
            $shop_prizes = array_filter($shop_prizes, fn($p) => !$p['active']);
        }

        $edit_prize = null;
        if (!empty($_GET['edit_prize'])) {
            $edit_prize = $db->get_prize((int)$_GET['edit_prize']);
        }

        // Orders tab — only orders for shop prizes
        $shop_prize_ids = array_column($shop_prizes, 'id');
        $all_orders = $db->get_all_orders(500);
        $shop_orders = $shop_prize_ids
            ? array_filter($all_orders, fn($o) => in_array($o['prize_id'], $shop_prize_ids))
            : [];

        include __DIR__ . '/../templates/shop.php';
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

    // ─── Admin Codes ──────────────────────────────────
    public static function admin_codes() {
        $admin_codes = self::db()->get_admin_codes(200);
        $total = self::db()->count_admin_codes();
        include __DIR__ . '/../templates/admin_codes.php';
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
