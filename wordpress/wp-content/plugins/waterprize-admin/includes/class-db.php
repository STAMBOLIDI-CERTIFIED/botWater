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
        $pass = get_option('wpz_db_pass', '');

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
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
            $sql .= ' WHERE name ILIKE $1 OR phone ILIKE $1 OR CAST(telegram_id AS TEXT) LIKE $1';
            $params[] = "%{$search}%";
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        return $this->query($sql, $params);
    }

    public function count_users($search = '') {
        $sql = 'SELECT COUNT(*) as cnt FROM users';
        $params = [];
        if ($search) {
            $sql .= ' WHERE name ILIKE $1 OR phone ILIKE $1 OR CAST(telegram_id AS TEXT) LIKE $1';
            $params[] = "%{$search}%";
        }
        $row = $this->query($sql, $params);
        return $row[0]['cnt'] ?? 0;
    }

    public function get_user($telegram_id) {
        $rows = $this->query('SELECT * FROM users WHERE telegram_id = $1', [$telegram_id]);
        return $rows[0] ?? null;
    }

    public function update_balance($telegram_id, $amount) {
        $user = $this->get_user($telegram_id);
        if (!$user) return false;
        $new = ($user['balance'] ?? 0) + $amount;
        return $this->execute('UPDATE users SET balance = $1 WHERE telegram_id = $2', [$new, $telegram_id]);
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
                    'INSERT INTO qr_codes (code, batch, status) VALUES ($1, $2, $3) ON CONFLICT (code) DO NOTHING',
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
        $sql = "SELECT * FROM bottles";
        $params = [];
        if ($search) {
            $sql .= " WHERE bottle_id ILIKE \$1 OR batch ILIKE \$1 OR year ILIKE \$1";
            $params[] = "%{$search}%";
        }
        $sql .= " ORDER BY {$sort} {$dir} LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        return $this->query($sql, $params);
    }

    public function count_bottles($search = '') {
        $sql = 'SELECT COUNT(*) as cnt FROM bottles';
        $params = [];
        if ($search) {
            $sql .= ' WHERE bottle_id ILIKE $1 OR batch ILIKE $1 OR year ILIKE $1';
            $params[] = "%{$search}%";
        }
        $row = $this->query($sql, $params);
        return $row[0]['cnt'] ?? 0;
    }

    public function get_batches() {
        $rows = $this->query('SELECT year, batch, COUNT(*) as count FROM bottles GROUP BY year, batch ORDER BY year DESC, batch DESC');
        return $rows;
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
        $rows = $this->query(
            "SELECT o.*, u.telegram_id, u.name, u.phone, p.name as prize_name
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             LEFT JOIN prizes p ON o.prize_id = p.id
             WHERE o.status = \$1
             ORDER BY o.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset,
            [$status]
        );
        return $rows;
    }

    // ─── Prizes ───────────────────────────────────────
    public function get_prizes() {
        return $this->query('SELECT * FROM prizes ORDER BY price_points ASC');
    }

    public function get_categories() {
        return $this->query('SELECT * FROM shop_categories ORDER BY sort_order ASC');
    }

    // ─── Admins ───────────────────────────────────────
    public function get_admins() {
        return $this->query('SELECT * FROM admins ORDER BY id DESC');
    }

    public function add_admin($telegram_id, $name) {
        return $this->insert(
            'INSERT INTO admins (telegram_id, name) VALUES ($1, $2) ON CONFLICT (telegram_id) DO NOTHING',
            [$telegram_id, $name]
        );
    }

    public function remove_admin($telegram_id) {
        return $this->delete('DELETE FROM admins WHERE telegram_id = $1', [$telegram_id]);
    }

    // ─── Settings ─────────────────────────────────────
    public function get_setting($key) {
        $rows = $this->query('SELECT value FROM settings WHERE key = $1', [$key]);
        return $rows[0]['value'] ?? null;
    }

    public function set_setting($key, $value) {
        $existing = $this->query('SELECT key FROM settings WHERE key = $1', [$key]);
        if ($existing) {
            return $this->execute('UPDATE settings SET value = $1 WHERE key = $2', [$value, $key]);
        }
        return $this->insert('INSERT INTO settings (key, value) VALUES ($1, $2)', [$key, $value]);
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
}
