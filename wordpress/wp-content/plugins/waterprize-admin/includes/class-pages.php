<?php
/**
 * Admin page renderers
 */
class WaterPrize_Pages {

    private static function db() { return WaterPrize_DB::instance(); }

    private static function handle_actions() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'wpz_action')) return;

        $action = $_POST['action'] ?? '';
        $db = self::db();

        switch ($action) {
            case 'add_balance':
                $tg = (int)($_POST['telegram_id'] ?? 0);
                $amt = (int)($_POST['amount'] ?? 0);
                $reason = sanitize_text_field($_POST['reason'] ?? '');
                if ($tg && $amt) {
                    $db->update_balance($tg, $amt);
                    $user = $db->get_user($tg);
                    if ($user) {
                        $db->insert(
                            'INSERT INTO points_log (user_id, amount, type, description) VALUES ($1, $2, $3, $4)',
                            [$user['id'], $amt, 'admin', $reason ?: 'Начисление администратором']
                        );
                    }
                }
                wp_redirect(admin_url('admin.php?page=wpz-users&msg=' . urlencode("Начислено {$amt} баллов")));
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
                wp_redirect(admin_url('admin.php?page=wpz-settings&msg=' . urlencode('Настройки сохранены. Перезагрузите страницу.')));
                exit;
        }
    }

    private static function create_bottles($db, $count, $batch, $year) {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $existing = $db->query(
            "SELECT bottle_id FROM bottles WHERE year = \$1 AND batch = \$2 ORDER BY id DESC LIMIT 1",
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
            $db->insert('INSERT INTO bottles (bottle_id, batch, year) VALUES ($1, $2, $3)', [$bottle_id, $batch, $year]);
        }
    }

    // ─── Dashboard ────────────────────────────────────
    public static function dashboard() {
        self::handle_actions();
        $stats = self::db()->get_stats();
        include __DIR__ . '/../templates/dashboard.php';
    }

    // ─── Users ────────────────────────────────────────
    public static function users() {
        self::handle_actions();
        $search = sanitize_text_field($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['paged'] ?? 1));
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        $total = self::db()->count_users($search);
        $users = self::db()->get_users($search, $per_page, $offset);
        $total_pages = ceil($total / $per_page);
        include __DIR__ . '/../templates/users.php';
    }

    // ─── Codes ────────────────────────────────────────
    public static function codes() {
        self::handle_actions();
        $codes = self::db()->get_codes(500);
        $stats = [
            'total' => self::db()->count_codes(),
            'active' => self::db()->count_active_codes(),
        ];
        include __DIR__ . '/../templates/codes.php';
    }

    // ─── Bottles ──────────────────────────────────────
    public static function bottles() {
        self::handle_actions();
        $search = sanitize_text_field($_GET['search'] ?? '');
        $sort = sanitize_text_field($_GET['sort'] ?? 'id');
        $dir = sanitize_text_field($_GET['dir'] ?? 'DESC');
        $bottles = self::db()->get_bottles($search, $sort, $dir, 200);
        $batches = self::db()->get_batches();
        $total = self::db()->count_bottles($search);
        include __DIR__ . '/../templates/bottles.php';
    }

    // ─── Raffles ──────────────────────────────────────
    public static function raffles() {
        self::handle_actions();
        $raffles = self::db()->get_raffles(200);
        include __DIR__ . '/../templates/raffles.php';
    }

    // ─── Prizes ───────────────────────────────────────
    public static function prizes() {
        self::handle_actions();
        $prizes = self::db()->get_prizes();
        $categories = self::db()->get_categories();
        include __DIR__ . '/../templates/prizes.php';
    }

    // ─── Orders ───────────────────────────────────────
    public static function orders() {
        self::handle_actions();
        $orders = self::db()->get_orders('pending');
        include __DIR__ . '/../templates/orders.php';
    }

    // ─── Admins ───────────────────────────────────────
    public static function admins() {
        self::handle_actions();
        $admins = self::db()->get_admins();
        include __DIR__ . '/../templates/admins.php';
    }

    // ─── Settings ─────────────────────────────────────
    public static function settings() {
        self::handle_actions();
        include __DIR__ . '/../templates/settings.php';
    }
}
