<?php
/**
 * PostgreSQL connection to remote bot database
 */
class WaterPrize_DB {
    private static $instance = null;
    private $conn = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->connect();
    }

    private function connect() {
        $host = get_option('wpz_db_host', 'node1.pghost.ru');
        $port = get_option('wpz_db_port', '15980');
        $dbname = get_option('wpz_db_name', 'bothost_db_8d8917dc2bab');
        $user = get_option('wpz_db_user', 'bothost_db_8d8917dc2bab');
        $pass = get_option('wpz_db_pass', 'Bi75g85iDTRx8KjIsX2PUnzr6QahElWAy_vdrxCgoVM');

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=disable";
        try {
            $this->conn = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            error_log('WaterPrize DB error: ' . $e->getMessage());
            $this->conn = null;
        }
    }

    public function is_connected() {
        return $this->conn !== null;
    }

    public function reconnect() {
        $this->conn = null;
        $this->connect();
        return $this->conn !== null;
    }

    public function query($sql, $params = []) {
        if (!$this->conn) return [];
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('WaterPrize query error: ' . $e->getMessage());
            return [];
        }
    }

    public function execute($sql, $params = []) {
        if (!$this->conn) return false;
        try {
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log('WaterPrize execute error: ' . $e->getMessage());
            return false;
        }
    }

    public function insert($sql, $params = []) {
        if (!$this->conn) return false;
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log('WaterPrize insert error: ' . $e->getMessage());
            return false;
        }
    }

    public function delete($sql, $params = []) {
        return $this->execute($sql, $params);
    }

    // ─── Users ────────────────────────────────────────
    public function get_users($search = '', $limit = 100, $offset = 0) {
        $sql = 'SELECT * FROM users';
        $params = [];
        if ($search) {
            $sql .= ' WHERE name ILIKE ? OR phone ILIKE ? OR CAST(telegram_id AS TEXT) LIKE ?';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        return $this->query($sql, $params);
    }

    public function count_users($search = '') {
        $sql = 'SELECT COUNT(*) as cnt FROM users';
        $params = [];
        if ($search) {
            $sql .= ' WHERE name ILIKE ? OR phone ILIKE ? OR CAST(telegram_id AS TEXT) LIKE ?';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        $row = $this->query($sql, $params);
        return $row[0]['cnt'] ?? 0;
    }

    public function get_user($telegram_id) {
        $rows = $this->query('SELECT * FROM users WHERE telegram_id = ?', [(int)$telegram_id]);
        return $rows[0] ?? null;
    }

    public function update_balance($telegram_id, $amount) {
        $user = $this->get_user($telegram_id);
        if (!$user) return false;
        $new = ($user['balance'] ?? 0) + $amount;
        return $this->execute('UPDATE users SET balance = ? WHERE telegram_id = ?', [$new, (int)$telegram_id]);
    }

    public function update_tree_xp($telegram_id, $xp) {
        $user = $this->get_user($telegram_id);
        if (!$user) return false;
        $new_xp = max(0, ($user['tree_xp'] ?? 0) + $xp);
        $new_level = 1;
        if ($new_xp >= 5000) $new_level = 6;
        elseif ($new_xp >= 2000) $new_level = 5;
        elseif ($new_xp >= 1000) $new_level = 4;
        elseif ($new_xp >= 500) $new_level = 3;
        elseif ($new_xp >= 100) $new_level = 2;
        return $this->execute('UPDATE users SET tree_xp = ?, tree_level = ? WHERE telegram_id = ?', [$new_xp, $new_level, (int)$telegram_id]);
    }

    public function get_users_stats() {
        $row = $this->query("SELECT
            COUNT(*)::int as total,
            COUNT(*) FILTER (WHERE balance > 0)::int as with_balance,
            COALESCE(SUM(balance), 0)::int as total_balance,
            COALESCE(AVG(balance), 0)::int as avg_balance,
            COUNT(*) FILTER (WHERE phone != '')::int as with_phone,
            COUNT(*) FILTER (WHERE passport_fio != '')::int as with_passport,
            COUNT(*) FILTER (WHERE tree_level > 1)::int as above_level1,
            COUNT(*) FILTER (WHERE created_at > NOW() - INTERVAL '1 day')::int as new_today,
            COUNT(*) FILTER (WHERE created_at > NOW() - INTERVAL '7 days')::int as new_week,
            COUNT(*) FILTER (WHERE created_at > NOW() - INTERVAL '30 days')::int as new_month,
            COUNT(*) FILTER (WHERE updated_at > NOW() - INTERVAL '15 minutes')::int as online,
            COUNT(*) FILTER (WHERE is_banned = TRUE)::int as banned
        FROM users");
        return $row[0] ?? [];
    }

    public function get_users_registrations_chart($days = 30) {
        return $this->query(
            "SELECT TO_CHAR(DATE(created_at), 'DD.MM') as day, COUNT(*)::int as cnt
             FROM users
             WHERE created_at > NOW() - INTERVAL '{$days} days'
             GROUP BY DATE(created_at)
             ORDER BY DATE(created_at)"
        );
    }

    public function get_top_balances($limit = 5) {
        return $this->query(
            "SELECT name, telegram_id, balance, tree_level
             FROM users WHERE balance > 0
             ORDER BY balance DESC LIMIT " . (int)$limit
        );
    }

    public function get_users_by_level() {
        return $this->query(
            "SELECT tree_level, COUNT(*)::int as cnt
             FROM users GROUP BY tree_level ORDER BY tree_level"
        );
    }

    // ─── User Management (Ban/Unban/Delete) ─────────────
    public function ban_user($telegram_id, $reason = '') {
        return $this->execute(
            'UPDATE users SET is_banned = TRUE, ban_reason = ?, banned_at = NOW() WHERE telegram_id = ?',
            [$reason, (int)$telegram_id]
        );
    }

    public function unban_user($telegram_id) {
        return $this->execute(
            'UPDATE users SET is_banned = FALSE, ban_reason = \'\', banned_at = NULL WHERE telegram_id = ?',
            [(int)$telegram_id]
        );
    }

    public function delete_user($telegram_id) {
        $user = $this->get_user($telegram_id);
        if (!$user) return false;
        $uid = $user['id'];
        $this->execute('DELETE FROM points_log WHERE user_id = ?', [$uid]);
        $this->execute('DELETE FROM notifications WHERE user_id = ?', [$uid]);
        $this->execute('DELETE FROM scans WHERE user_id = ?', [$uid]);
        $this->execute('DELETE FROM orders WHERE user_id = ?', [$uid]);
        $this->execute('DELETE FROM user_qr_activations WHERE user_id = ?', [$uid]);
        $chat_ids = $this->query('SELECT id FROM support_chats WHERE user_id = ?', [$uid]);
        foreach ($chat_ids as $c) {
            $this->execute('DELETE FROM support_messages WHERE chat_id = ?', [$c['id']]);
        }
        $this->execute('DELETE FROM support_chats WHERE user_id = ?', [$uid]);
        return $this->execute('DELETE FROM users WHERE id = ?', [$uid]);
    }

    public function count_users_banned() {
        $row = $this->query("SELECT COUNT(*) as cnt FROM users WHERE is_banned = TRUE");
        return $row[0]['cnt'] ?? 0;
    }

    // ─── QR Codes ─────────────────────────────────────
    public function get_codes($limit = 200, $offset = 0) {
        return $this->query('SELECT * FROM qr_codes ORDER BY id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset);
    }

    public function count_codes() {
        $row = $this->query('SELECT COUNT(*) as cnt FROM qr_codes');
        return $row[0]['cnt'] ?? 0;
    }

    public function count_active_codes() {
        $row = $this->query("SELECT COUNT(*) as cnt FROM qr_codes WHERE status = 'active'");
        return $row[0]['cnt'] ?? 0;
    }

    public function add_codes($codes, $batch) {
        $count = 0;
        foreach ($codes as $code) {
            $code = trim($code);
            if (!$code) continue;
            try {
                $this->insert(
                    'INSERT INTO qr_codes (code, batch, status) VALUES (?, ?, ?) ON CONFLICT (code) DO NOTHING',
                    [$code, $batch, 'active']
                );
                $count++;
            } catch (\Exception $e) {}
        }
        return $count;
    }

    // ─── Bottles ──────────────────────────────────────
    public function get_bottles($search = '', $sort = 'id', $dir = 'DESC', $limit = 200, $offset = 0) {
        $allowed = ['id', 'bottle_id', 'year', 'batch', 'assigned_to', 'created_at'];
        $sort = in_array($sort, $allowed) ? $sort : 'id';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $sql = "SELECT b.*, u.name AS user_name, u.telegram_id AS user_telegram_id
                FROM bottles b
                LEFT JOIN users u ON b.assigned_to = u.id";
        $params = [];
        if ($search) {
            $sql .= " WHERE b.bottle_id ILIKE ? OR b.batch ILIKE ? OR b.year ILIKE ?";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        $sql .= " ORDER BY b.{$sort} {$dir} LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        return $this->query($sql, $params);
    }

    public function get_bottle($id) {
        $rows = $this->query(
            "SELECT b.*, u.name AS user_name, u.telegram_id AS user_telegram_id
             FROM bottles b
             LEFT JOIN users u ON b.assigned_to = u.id
             WHERE b.id = ?",
            [(int)$id]
        );
        return $rows[0] ?? null;
    }

    public function count_bottles($search = '') {
        $sql = 'SELECT COUNT(*) as cnt FROM bottles';
        $params = [];
        if ($search) {
            $sql .= ' WHERE bottle_id ILIKE ? OR batch ILIKE ? OR year ILIKE ?';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        $row = $this->query($sql, $params);
        return $row[0]['cnt'] ?? 0;
    }

    public function get_batches() {
        return $this->query('SELECT year, batch, COUNT(*) as count FROM bottles GROUP BY year, batch ORDER BY year DESC, batch DESC');
    }

    // ─── Raffles ──────────────────────────────────────
    public function get_raffles($limit = 200, $offset = 0) {
        return $this->query('SELECT * FROM raffles ORDER BY id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset);
    }

    public function count_raffles() {
        $row = $this->query('SELECT COUNT(*) as cnt FROM raffles');
        return $row[0]['cnt'] ?? 0;
    }

    // ─── Orders ───────────────────────────────────────
    public function get_orders($status = 'pending', $limit = 200, $offset = 0) {
        return $this->query(
            "SELECT o.*, u.telegram_id, u.name, u.phone, p.name as prize_name
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             LEFT JOIN prizes p ON o.prize_id = p.id
             WHERE o.status = ?
             ORDER BY o.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset,
            [$status]
        );
    }

    public function get_all_orders($limit = 200, $offset = 0) {
        return $this->query(
            "SELECT o.*, u.telegram_id, u.name, u.phone, p.name as prize_name
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             LEFT JOIN prizes p ON o.prize_id = p.id
             ORDER BY o.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
    }

    public function update_order_status($order_id, $status) {
        return $this->execute('UPDATE orders SET status = ? WHERE id = ?', [$status, (int)$order_id]);
    }

    // ─── Prizes ───────────────────────────────────────
    public function get_prizes() {
        return $this->query('SELECT * FROM prizes ORDER BY price_points ASC');
    }

    public function get_prize($id) {
        $rows = $this->query('SELECT * FROM prizes WHERE id = ?', [(int)$id]);
        return $rows[0] ?? null;
    }

    public function add_prize($name, $description, $image_url, $price_points, $category_id, $active = 1) {
        return $this->insert(
            'INSERT INTO prizes (name, description, image_url, price_points, category_id, active) VALUES (?, ?, ?, ?, ?, ?)',
            [$name, $description, $image_url, (int)$price_points, (int)$category_id, (int)$active]
        );
    }

    public function update_prize($id, $name, $description, $image_url, $price_points, $category_id, $active) {
        return $this->execute(
            'UPDATE prizes SET name=?, description=?, image_url=?, price_points=?, category_id=?, active=? WHERE id=?',
            [$name, $description, $image_url, (int)$price_points, (int)$category_id, (int)$active, (int)$id]
        );
    }

    public function delete_prize($id) {
        return $this->delete('DELETE FROM prizes WHERE id = ?', [(int)$id]);
    }

    // ─── Shop Categories ──────────────────────────────
    public function get_categories() {
        return $this->query('SELECT * FROM shop_categories ORDER BY sort_order ASC');
    }

    public function get_category($id) {
        $rows = $this->query('SELECT * FROM shop_categories WHERE id = ?', [(int)$id]);
        return $rows[0] ?? null;
    }

    public function get_category_by_title($title) {
        $rows = $this->query('SELECT * FROM shop_categories WHERE title = ?', [$title]);
        return $rows[0] ?? null;
    }

    public function add_category($title, $subtitle, $description, $icon, $color, $sort_order, $is_active = true, $scan_points = 10, $image_url = '', $logo_url = '', $website = '', $telegram = '', $info = '') {
        $qr_code = 'partner_' . time() . '_' . bin2hex(random_bytes(4));
        return $this->insert(
            'INSERT INTO shop_categories (title, subtitle, description, icon, image_url, logo_url, color, sort_order, is_active, qr_code, scan_points, website, telegram, info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$title, $subtitle, $description, $icon, $image_url, $logo_url, $color, (int)$sort_order, $is_active, $qr_code, (int)$scan_points, $website, $telegram, $info]
        );
    }

    public function update_category($id, $title, $subtitle, $description, $icon, $color, $sort_order, $is_active, $scan_points = 10, $image_url = '', $logo_url = '', $website = '', $telegram = '', $info = '') {
        return $this->execute(
            'UPDATE shop_categories SET title=?, subtitle=?, description=?, icon=?, image_url=?, logo_url=?, color=?, sort_order=?, is_active=?, scan_points=?, website=?, telegram=?, info=? WHERE id=?',
            [$title, $subtitle, $description, $icon, $image_url, $logo_url, $color, (int)$sort_order, $is_active, (int)$scan_points, $website, $telegram, $info, (int)$id]
        );
    }

    public function regenerate_qr_code($id) {
        $qr_code = 'partner_' . time() . '_' . bin2hex(random_bytes(4));
        $this->execute('UPDATE shop_categories SET qr_code=? WHERE id=?', [$qr_code, (int)$id]);
        return $qr_code;
    }

    public function delete_category($id) {
        return $this->delete('DELETE FROM shop_categories WHERE id = ?', [(int)$id]);
    }

    public function count_prizes_in_category($category_id) {
        $row = $this->query('SELECT COUNT(*) as cnt FROM prizes WHERE category_id = ?', [(int)$category_id]);
        return $row[0]['cnt'] ?? 0;
    }

    // ─── Admins ───────────────────────────────────────
    public function get_admins() {
        return $this->query('SELECT * FROM admins ORDER BY id DESC');
    }

    public function add_admin($telegram_id, $name) {
        return $this->insert(
            'INSERT INTO admins (telegram_id, name) VALUES (?, ?) ON CONFLICT (telegram_id) DO NOTHING',
            [(int)$telegram_id, $name]
        );
    }

    public function remove_admin($telegram_id) {
        return $this->delete('DELETE FROM admins WHERE telegram_id = ?', [(int)$telegram_id]);
    }

    // ─── Settings ─────────────────────────────────────
    public function get_setting($key) {
        $rows = $this->query('SELECT value FROM settings WHERE key = ?', [$key]);
        return $rows[0]['value'] ?? null;
    }

    public function set_setting($key, $value) {
        $existing = $this->query('SELECT key FROM settings WHERE key = ?', [$key]);
        if ($existing) {
            return $this->execute('UPDATE settings SET value = ? WHERE key = ?', [$value, $key]);
        }
        return $this->insert('INSERT INTO settings (key, value) VALUES (?, ?)', [$key, $value]);
    }

    // ─── Scans ────────────────────────────────────────
    public function get_scans($limit = 200, $offset = 0) {
        return $this->query(
            "SELECT s.*, u.name, u.telegram_id, q.code
             FROM scans s
             LEFT JOIN users u ON s.user_id = u.id
             LEFT JOIN qr_codes q ON s.code_id = q.id
             ORDER BY s.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
    }

    public function count_scans() {
        $row = $this->query('SELECT COUNT(*) as cnt FROM scans');
        return $row[0]['cnt'] ?? 0;
    }

    // ─── Notifications ────────────────────────────────
    public function get_notifications($limit = 200, $offset = 0) {
        return $this->query(
            "SELECT n.*, u.name, u.telegram_id
             FROM notifications n
             LEFT JOIN users u ON n.user_id = u.id
             ORDER BY n.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
    }

    public function count_notifications() {
        $row = $this->query('SELECT COUNT(*) as cnt FROM notifications');
        return $row[0]['cnt'] ?? 0;
    }

    // ─── Admin Codes ──────────────────────────────────
    public function get_admin_codes($limit = 200, $offset = 0) {
        return $this->query(
            "SELECT * FROM admin_codes ORDER BY id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
    }

    public function count_admin_codes() {
        $row = $this->query('SELECT COUNT(*) as cnt FROM admin_codes');
        return $row[0]['cnt'] ?? 0;
    }

    // ─── User QR Activations ──────────────────────────
    public function get_user_qr_activations($limit = 200, $offset = 0) {
        return $this->query(
            "SELECT a.*, u.name, u.telegram_id
             FROM user_qr_activations a
             LEFT JOIN users u ON a.user_id = u.id
             ORDER BY a.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
    }

    public function count_user_qr_activations() {
        $row = $this->query('SELECT COUNT(*) as cnt FROM user_qr_activations');
        return $row[0]['cnt'] ?? 0;
    }

    // ─── Dashboard Extended Stats ─────────────────────
    public function count_online($minutes = 15) {
        $row = $this->query("SELECT COUNT(*) as cnt FROM users WHERE updated_at > NOW() - INTERVAL '{$minutes} minutes'");
        return $row[0]['cnt'] ?? 0;
    }

    public function get_balance_stats() {
        $row = $this->query('SELECT COALESCE(SUM(balance), 0) as total, COALESCE(AVG(balance), 0) as avg, COUNT(*) FILTER (WHERE balance > 0) as active FROM users');
        return $row[0] ?? ['total' => 0, 'avg' => 0, 'active' => 0];
    }

    public function get_points_stats() {
        $row = $this->query("SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as cnt FROM points_log");
        return $row[0] ?? ['total' => 0, 'cnt' => 0];
    }

    public function get_notifications_stats() {
        $row = $this->query("SELECT COUNT(*) as total, COUNT(*) FILTER (WHERE read = 0) as unread FROM notifications");
        return $row[0] ?? ['total' => 0, 'unread' => 0];
    }

    public function get_registrations_chart($days = 30) {
        return $this->query(
            "SELECT TO_CHAR(DATE(created_at), 'DD.MM') as day, COUNT(*) as cnt
             FROM users
             WHERE created_at > NOW() - INTERVAL '{$days} days'
             GROUP BY DATE(created_at)
             ORDER BY DATE(created_at)"
        );
    }

    public function get_points_chart($days = 30) {
        return $this->query(
            "SELECT TO_CHAR(DATE(created_at), 'DD.MM') as day, SUM(amount) as total
             FROM points_log
             WHERE created_at > NOW() - INTERVAL '{$days} days'
             GROUP BY DATE(created_at)
             ORDER BY DATE(created_at)"
        );
    }

    public function get_notifications_chart() {
        return $this->query(
            "SELECT type, COUNT(*) as cnt FROM notifications GROUP BY type ORDER BY cnt DESC"
        );
    }

    public function get_bottles_chart() {
        return $this->query(
            "SELECT COALESCE(batch, '—') as batch, COUNT(*) as cnt FROM bottles GROUP BY batch ORDER BY batch"
        );
    }

    public function get_top_users($limit = 10) {
        return $this->query(
            "SELECT name, telegram_id, balance, tree_xp, tree_level
             FROM users ORDER BY balance DESC LIMIT " . (int)$limit
        );
    }

    public function get_recent_activity($limit = 20) {
        $points = $this->query(
            "SELECT 'points' as src, p.amount, p.type, p.description, p.created_at, u.name, u.telegram_id
             FROM points_log p LEFT JOIN users u ON p.user_id = u.id
             ORDER BY p.id DESC LIMIT " . (int)$limit
        );
        $scans = $this->query(
            "SELECT 'scan' as src, 1 as amount, 'scan' as type, q.code as description, s.scanned_at as created_at, u.name, u.telegram_id
             FROM scans s LEFT JOIN users u ON s.user_id = u.id LEFT JOIN qr_codes q ON s.code_id = q.id
             ORDER BY s.id DESC LIMIT " . (int)$limit
        );
        $all = array_merge($points, $scans);
        usort($all, fn($a, $b) => strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'));
        return array_slice($all, 0, $limit);
    }

    // ─── Stats ────────────────────────────────────────
    public function get_stats() {
        $users = $this->count_users();
        $codes_total = $this->count_codes();
        $codes_active = $this->count_active_codes();
        $bottles_total = $this->count_bottles();
        $raffles_total = $this->count_raffles();
        $orders_pending = count($this->get_orders('pending', 9999));
        return compact('users', 'codes_total', 'codes_active', 'bottles_total', 'raffles_total', 'orders_pending');
    }

    // ─── Analytics ────────────────────────────────────
    public function get_registrations_analytics($period = 'day', $from = '', $to = '') {
        $group = match($period) {
            'week'  => "TO_CHAR(DATE_TRUNC('week', created_at), 'DD.MM.YYYY')",
            'month' => "TO_CHAR(DATE_TRUNC('month', created_at), 'MM.YYYY')",
            'year'  => "TO_CHAR(DATE_TRUNC('year', created_at), 'YYYY')",
            default => "TO_CHAR(created_at, 'DD.MM.YYYY')",
        };
        $order = match($period) {
            'week'  => "DATE_TRUNC('week', created_at)",
            'month' => "DATE_TRUNC('month', created_at)",
            'year'  => "DATE_TRUNC('year', created_at)",
            default => "DATE_TRUNC('day', created_at)",
        };
        $where = '';
        $params = [];
        if ($from) { $where .= " AND created_at >= ?"; $params[] = $from; }
        if ($to)   { $where .= " AND created_at <= ?"; $params[] = $to . ' 23:59:59'; }

        return $this->query(
            "SELECT {$group} as label, COUNT(*)::int as cnt
             FROM users
             WHERE 1=1 {$where}
             GROUP BY {$order}
             ORDER BY {$order} ASC",
            $params
        );
    }

    public function get_points_analytics($period = 'day', $from = '', $to = '') {
        $group = match($period) {
            'week'  => "TO_CHAR(DATE_TRUNC('week', created_at), 'DD.MM.YYYY')",
            'month' => "TO_CHAR(DATE_TRUNC('month', created_at), 'MM.YYYY')",
            'year'  => "TO_CHAR(DATE_TRUNC('year', created_at), 'YYYY')",
            default => "TO_CHAR(created_at, 'DD.MM.YYYY')",
        };
        $order = match($period) {
            'week'  => "DATE_TRUNC('week', created_at)",
            'month' => "DATE_TRUNC('month', created_at)",
            'year'  => "DATE_TRUNC('year', created_at)",
            default => "DATE_TRUNC('day', created_at)",
        };
        $where = '';
        $params = [];
        if ($from) { $where .= " AND created_at >= ?"; $params[] = $from; }
        if ($to)   { $where .= " AND created_at <= ?"; $params[] = $to . ' 23:59:59'; }

        return $this->query(
            "SELECT {$group} as label, SUM(amount)::int as total, COUNT(*)::int as cnt
             FROM points_log
             WHERE 1=1 {$where}
             GROUP BY {$order}
             ORDER BY {$order} ASC",
            $params
        );
    }

    public function get_scans_analytics($period = 'day', $from = '', $to = '') {
        $group = match($period) {
            'week'  => "TO_CHAR(DATE_TRUNC('week', created_at), 'DD.MM.YYYY')",
            'month' => "TO_CHAR(DATE_TRUNC('month', created_at), 'MM.YYYY')",
            'year'  => "TO_CHAR(DATE_TRUNC('year', created_at), 'YYYY')",
            default => "TO_CHAR(created_at, 'DD.MM.YYYY')",
        };
        $order = match($period) {
            'week'  => "DATE_TRUNC('week', created_at)",
            'month' => "DATE_TRUNC('month', created_at)",
            'year'  => "DATE_TRUNC('year', created_at)",
            default => "DATE_TRUNC('day', created_at)",
        };
        $where = '';
        $params = [];
        if ($from) { $where .= " AND created_at >= ?"; $params[] = $from; }
        if ($to)   { $where .= " AND created_at <= ?"; $params[] = $to . ' 23:59:59'; }

        return $this->query(
            "SELECT {$group} as label, COUNT(*)::int as cnt
             FROM scans
             WHERE 1=1 {$where}
             GROUP BY {$order}
             ORDER BY {$order} ASC",
            $params
        );
    }

    // ─── Partner Statistics ──────────────────────────────
    public function get_partner_stats_summary() {
        return $this->query(
            "SELECT
                sc.id as category_id,
                sc.title as partner_name,
                sc.qr_code,
                sc.scan_points,
                COALESCE(ps.total_scans, 0) as total_scans,
                COALESCE(ps.unique_users, 0) as unique_users,
                COALESCE(ps.total_points, 0) as total_points,
                COALESCE(ps.last_scan_at, NULL) as last_scan_at,
                COALESCE(pb.buyers_count, 0) as buyers_count
             FROM shop_categories sc
             LEFT JOIN (
                SELECT
                    category_id,
                    COUNT(*)::int as total_scans,
                    COUNT(DISTINCT user_id)::int as unique_users,
                    SUM(points_earned)::int as total_points,
                    MAX(scanned_at) as last_scan_at
                FROM partner_scans
                GROUP BY category_id
             ) ps ON ps.category_id = sc.id
             LEFT JOIN (
                SELECT
                    sc2.id as category_id,
                    COUNT(DISTINCT o.user_id)::int as buyers_count
                FROM shop_categories sc2
                JOIN prizes pr ON pr.category_id = sc2.id
                JOIN orders o ON o.prize_id = pr.id
                GROUP BY sc2.id
             ) pb ON pb.category_id = sc.id
             ORDER BY ps.total_scans DESC NULLS LAST"
        );
    }

    public function get_partner_scans_detail($category_id, $limit = 200, $offset = 0) {
        return $this->query(
            "SELECT
                ps.id,
                ps.user_id,
                u.name,
                u.username,
                u.balance,
                u.tree_level,
                ps.qr_code,
                ps.points_earned,
                ps.scanned_at
             FROM partner_scans ps
             JOIN users u ON u.id = ps.user_id
             WHERE ps.category_id = ?
             ORDER BY ps.scanned_at DESC
             LIMIT ? OFFSET ?",
            [(int)$category_id, (int)$limit, (int)$offset]
        );
    }

    public function get_partner_scans_chart($period = 'day', $from = '', $to = '', $category_id = 0) {
        $where = '1=1';
        $params = [];

        if ($category_id > 0) {
            $where .= ' AND ps.category_id = ?';
            $params[] = (int)$category_id;
        }

        if ($from) {
            $where .= ' AND ps.scanned_at >= ?';
            $params[] = $from;
        }
        if ($to) {
            $where .= " AND ps.scanned_at <= ?::date + interval '1 day'";
            $params[] = $to;
        }

        $group = "to_char(ps.scanned_at, 'YYYY-MM-DD')";
        $raw_group = 'YYYY-MM-DD';

        if ($period === 'hour') {
            $group = "to_char(ps.scanned_at, 'YYYY-MM-DD HH24:00')";
            $raw_group = 'YYYY-MM-DD HH24:00';
        } elseif ($period === 'week') {
            $group = "to_char(ps.scanned_at, 'IYYY-IW')";
            $raw_group = 'IYYY-IW';
        } elseif ($period === 'month') {
            $group = "to_char(ps.scanned_at, 'YYYY-MM')";
            $raw_group = 'YYYY-MM';
        } elseif ($period === 'year') {
            $group = "to_char(ps.scanned_at, 'YYYY')";
            $raw_group = 'YYYY';
        }

        $rows = $this->query(
            "SELECT
                {$group} as raw_label,
                COUNT(*)::int as cnt,
                COUNT(DISTINCT ps.user_id)::int as unique_users,
                COALESCE(SUM(ps.points_earned), 0)::int as total_points,
                COUNT(DISTINCT CASE WHEN EXISTS (
                    SELECT 1 FROM orders o
                    JOIN prizes pr ON pr.id = o.prize_id
                    WHERE pr.category_id = ps.category_id AND o.user_id = ps.user_id
                ) THEN ps.user_id END)::int as buyers_count
             FROM partner_scans ps
             WHERE {$where}
             GROUP BY raw_label
             ORDER BY raw_label ASC",
            $params
        );

        $ru_days = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
        $ru_months = ['','Январь','Февраль','Март','Апрель','Май','Июнь',
                       'Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'];

        foreach ($rows as &$row) {
            $raw = $row['raw_label'];
            if ($period === 'hour' && preg_match('/(\d{4})-(\d{2})-(\d{2}) (\d{2}):00/', $raw, $m)) {
                $ts = mktime((int)$m[4], 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
                $row['label'] = $m[3] . '.' . $m[2] . ' ' . $m[4] . ':00';
            } elseif ($period === 'day' && preg_match('/(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
                $ts = mktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
                $row['label'] = $m[3] . '.' . $m[2] . ' (' . $ru_days[(date('w', $ts) + 6) % 7] . ')';
            } elseif ($period === 'week' && preg_match('/(\d{4})-(\d{2})/', $raw, $m)) {
                $row['label'] = 'Нед. ' . ltrim($m[2], '0') . ', ' . $m[1];
            } elseif ($period === 'month' && preg_match('/(\d{4})-(\d{2})/', $raw, $m)) {
                $row['label'] = $ru_months[(int)$m[2]] . ' ' . $m[1];
            } elseif ($period === 'year') {
                $row['label'] = $raw;
            } else {
                $row['label'] = $raw;
            }
        }
        unset($row);

        return $rows;
    }

    public function get_top_partners_users($limit = 20) {
        return $this->query(
            "SELECT
                sc.title as partner_name,
                u.name as user_name,
                u.username,
                u.telegram_id,
                u.balance,
                COUNT(*)::int as scans_count,
                SUM(ps.points_earned)::int as total_points
             FROM partner_scans ps
             JOIN users u ON u.id = ps.user_id
             JOIN shop_categories sc ON sc.id = ps.category_id
             GROUP BY sc.title, u.name, u.username, u.telegram_id, u.balance
             ORDER BY scans_count DESC
             LIMIT ?",
            [(int)$limit]
        );
    }

    // ─── Support Chat ─────────────────────────────────
    public function get_support_chats($limit = 200, $offset = 0) {
        return $this->query(
            "SELECT sc.*, u.name as user_name, u.telegram_id as user_telegram_id,
                    sm.message as last_message, sm.sender_type as last_sender, sm.created_at as last_message_at,
                    (SELECT COUNT(*)::int FROM support_messages WHERE chat_id = sc.id AND sender_type = 'user') as unread_count
             FROM support_chats sc
             LEFT JOIN users u ON sc.user_id = u.id
             LEFT JOIN support_messages sm ON sm.id = (
                SELECT id FROM support_messages WHERE chat_id = sc.id ORDER BY created_at DESC LIMIT 1
             )
             ORDER BY sc.updated_at DESC
             LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
    }

    public function count_support_chats() {
        $row = $this->query('SELECT COUNT(*) as cnt FROM support_chats');
        return $row[0]['cnt'] ?? 0;
    }

    public function get_support_chat($chat_id) {
        $rows = $this->query(
            "SELECT sc.*, u.name as user_name, u.telegram_id as user_telegram_id
             FROM support_chats sc
             LEFT JOIN users u ON sc.user_id = u.id
             WHERE sc.id = ?",
            [(int)$chat_id]
        );
        return $rows[0] ?? null;
    }

    public function get_support_messages($chat_id, $limit = 200) {
        return $this->query(
            "SELECT * FROM support_messages WHERE chat_id = ? ORDER BY created_at ASC LIMIT " . (int)$limit,
            [(int)$chat_id]
        );
    }

    public function send_support_message($chat_id, $sender_type, $message) {
        $id = $this->insert(
            'INSERT INTO support_messages (chat_id, sender_type, message) VALUES (?, ?, ?)',
            [(int)$chat_id, $sender_type, $message]
        );
        $this->execute('UPDATE support_chats SET updated_at = NOW() WHERE id = ?', [(int)$chat_id]);
        return $id;
    }

    public function close_support_chat($chat_id) {
        return $this->execute('UPDATE support_chats SET status = ? WHERE id = ?', ['closed', (int)$chat_id]);
    }

    public function count_unread_support_chats() {
        $row = $this->query(
            "SELECT COUNT(*) as cnt FROM support_chats WHERE status = 'open'"
        );
        return $row[0]['cnt'] ?? 0;
    }
}
