-- ============================================================
-- WaterPrize / IstokMoscowBot — полная схема базы данных
-- Выполнить целиком в Supabase SQL Editor нового проекта
-- ============================================================

-- ─── Users ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    telegram_id BIGINT UNIQUE NOT NULL,
    name TEXT DEFAULT '',
    username TEXT DEFAULT '',
    phone TEXT DEFAULT '',
    step TEXT DEFAULT 'start',
    start_payload TEXT DEFAULT '',
    agreed_terms INTEGER DEFAULT 0,
    balance INTEGER DEFAULT 0,
    tree_xp INTEGER DEFAULT 0,
    tree_level INTEGER DEFAULT 1,
    passport_fio TEXT DEFAULT '',
    passport_snumber TEXT DEFAULT '',
    passport_inn TEXT DEFAULT '',
    gift_opened BOOLEAN DEFAULT FALSE,
    gift_points INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_users_telegram_id ON users(telegram_id);

-- ─── QR codes (admin-registered codes) ───────────────
CREATE TABLE IF NOT EXISTS qr_codes (
    id BIGSERIAL PRIMARY KEY,
    code TEXT UNIQUE NOT NULL,
    batch TEXT DEFAULT '',
    status TEXT DEFAULT 'active',
    winner_id BIGINT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_qr_codes_status ON qr_codes(status);

-- ─── Scans ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS scans (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    code_id BIGINT REFERENCES qr_codes(id) ON DELETE CASCADE,
    scanned_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_scans_user_id ON scans(user_id);
CREATE INDEX IF NOT EXISTS idx_scans_code_id ON scans(code_id);

-- ─── Points log ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS points_log (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    amount INTEGER DEFAULT 0,
    type TEXT DEFAULT '',
    description TEXT DEFAULT '',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_points_log_user_id ON points_log(user_id);

-- ─── Notifications ───────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    type TEXT DEFAULT '',
    title TEXT DEFAULT '',
    body TEXT DEFAULT '',
    link TEXT DEFAULT '',
    read INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id);

-- ─── Shop categories ─────────────────────────────────
CREATE TABLE IF NOT EXISTS shop_categories (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    subtitle TEXT DEFAULT '',
    description TEXT DEFAULT '',
    icon VARCHAR(10) DEFAULT '🎁',
    image_url TEXT DEFAULT '',
    color VARCHAR(20) DEFAULT '#C9A84C',
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ─── Prizes ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS prizes (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT '',
    image_url TEXT DEFAULT '',
    price_points INTEGER DEFAULT 0,
    category_id INT REFERENCES shop_categories(id) ON DELETE SET NULL,
    active INTEGER DEFAULT 1,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_prizes_category ON prizes(category_id);

-- ─── Orders ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    prize_id BIGINT REFERENCES prizes(id) ON DELETE CASCADE,
    status TEXT DEFAULT 'pending',
    name TEXT DEFAULT '',
    phone TEXT DEFAULT '',
    telegram_id BIGINT,
    prize_name TEXT DEFAULT '',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);

-- ─── Raffles ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS raffles (
    id BIGSERIAL PRIMARY KEY,
    scheduled_at TIMESTAMPTZ DEFAULT NOW(),
    winner_scan_id BIGINT REFERENCES scans(id) ON DELETE SET NULL,
    prize_amount INTEGER DEFAULT 0,
    status TEXT DEFAULT 'completed',
    payout_deadline TIMESTAMPTZ,
    payout_status TEXT,
    payout_choice TEXT,
    winner_name TEXT DEFAULT '',
    winning_code TEXT DEFAULT '',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_raffles_status ON raffles(status);

-- ─── Bottles ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bottles (
    id BIGSERIAL PRIMARY KEY,
    bottle_id TEXT UNIQUE NOT NULL,
    batch TEXT DEFAULT '',
    year TEXT DEFAULT '',
    assigned_to BIGINT,
    assigned_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_bottles_assigned_to ON bottles(assigned_to);
CREATE INDEX IF NOT EXISTS idx_bottles_year_batch ON bottles(year, batch);

-- ─── Admins ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admins (
    id BIGSERIAL PRIMARY KEY,
    telegram_id BIGINT UNIQUE,
    name TEXT DEFAULT '',
    added_by BIGINT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ─── Admin one-time access codes ─────────────────────
CREATE TABLE IF NOT EXISTS admin_codes (
    id BIGSERIAL PRIMARY KEY,
    code TEXT,
    telegram_id BIGINT,
    expires_at TIMESTAMPTZ,
    used INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ─── Settings ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

INSERT INTO settings (key, value) VALUES ('splash_logo_url', '') ON CONFLICT (key) DO NOTHING;

-- ─── User QR activations (gift/scan dedup) ───────────
CREATE TABLE IF NOT EXISTS user_qr_activations (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    qr_code TEXT NOT NULL,
    activated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(user_id, qr_code)
);

CREATE INDEX IF NOT EXISTS idx_user_qr_activations_user_id ON user_qr_activations(user_id);
CREATE INDEX IF NOT EXISTS idx_user_qr_activations_qr_code ON user_qr_activations(qr_code);

-- ─── RLS: разрешить всё (бэкенд использует service_role) ──
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE qr_codes ENABLE ROW LEVEL SECURITY;
ALTER TABLE scans ENABLE ROW LEVEL SECURITY;
ALTER TABLE points_log ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE shop_categories ENABLE ROW LEVEL SECURITY;
ALTER TABLE prizes ENABLE ROW LEVEL SECURITY;
ALTER TABLE orders ENABLE ROW LEVEL SECURITY;
ALTER TABLE raffles ENABLE ROW LEVEL SECURITY;
ALTER TABLE bottles ENABLE ROW LEVEL SECURITY;
ALTER TABLE admins ENABLE ROW LEVEL SECURITY;
ALTER TABLE admin_codes ENABLE ROW LEVEL SECURITY;
ALTER TABLE settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE user_qr_activations ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Allow full access" ON users FOR ALL USING (true);
CREATE POLICY "Allow full access" ON qr_codes FOR ALL USING (true);
CREATE POLICY "Allow full access" ON scans FOR ALL USING (true);
CREATE POLICY "Allow full access" ON points_log FOR ALL USING (true);
CREATE POLICY "Allow full access" ON notifications FOR ALL USING (true);
CREATE POLICY "Allow full access" ON shop_categories FOR ALL USING (true);
CREATE POLICY "Allow full access" ON prizes FOR ALL USING (true);
CREATE POLICY "Allow full access" ON orders FOR ALL USING (true);
CREATE POLICY "Allow full access" ON raffles FOR ALL USING (true);
CREATE POLICY "Allow full access" ON bottles FOR ALL USING (true);
CREATE POLICY "Allow full access" ON admins FOR ALL USING (true);
CREATE POLICY "Allow full access" ON admin_codes FOR ALL USING (true);
CREATE POLICY "Allow full access" ON settings FOR ALL USING (true);
CREATE POLICY "Allow full access" ON user_qr_activations FOR ALL USING (true);
