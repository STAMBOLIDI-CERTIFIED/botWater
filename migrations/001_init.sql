-- WaterPrize Database Schema
-- PostgreSQL

-- Users
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    telegram_id BIGINT UNIQUE NOT NULL,
    name TEXT DEFAULT '',
    phone TEXT DEFAULT '',
    step TEXT DEFAULT 'start',
    start_payload TEXT DEFAULT '',
    balance INTEGER DEFAULT 0,
    tree_xp INTEGER DEFAULT 0,
    tree_level INTEGER DEFAULT 1,
    gift_opened BOOLEAN DEFAULT FALSE,
    gift_points INTEGER DEFAULT 0,
    passport_fio TEXT DEFAULT '',
    passport_snumber TEXT DEFAULT '',
    passport_inn TEXT DEFAULT '',
    agreed_terms SMALLINT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- QR Codes
CREATE TABLE IF NOT EXISTS qr_codes (
    id SERIAL PRIMARY KEY,
    code TEXT UNIQUE NOT NULL,
    batch TEXT DEFAULT '',
    status TEXT DEFAULT 'active',
    winner_id INTEGER REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Scans
CREATE TABLE IF NOT EXISTS scans (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    code_id INTEGER REFERENCES qr_codes(id),
    scanned_at TIMESTAMPTZ DEFAULT NOW()
);

-- Points Log
CREATE TABLE IF NOT EXISTS points_log (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    amount INTEGER NOT NULL,
    type TEXT DEFAULT 'admin',
    description TEXT DEFAULT '',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    type TEXT DEFAULT '',
    title TEXT DEFAULT '',
    body TEXT DEFAULT '',
    link TEXT DEFAULT '',
    read SMALLINT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Shop Categories
CREATE TABLE IF NOT EXISTS shop_categories (
    id SERIAL PRIMARY KEY,
    title TEXT NOT NULL DEFAULT '',
    subtitle TEXT DEFAULT '',
    description TEXT DEFAULT '',
    icon TEXT DEFAULT '🎁',
    color TEXT DEFAULT '#C9A84C',
    image_url TEXT DEFAULT '',
    sort_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Prizes
CREATE TABLE IF NOT EXISTS prizes (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL DEFAULT '',
    description TEXT DEFAULT '',
    image_url TEXT DEFAULT '',
    price_points INTEGER NOT NULL DEFAULT 0,
    category_id INTEGER DEFAULT 0 REFERENCES shop_categories(id),
    active SMALLINT DEFAULT 1,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Orders
CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    prize_id INTEGER NOT NULL REFERENCES prizes(id),
    status TEXT DEFAULT 'pending',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Raffles
CREATE TABLE IF NOT EXISTS raffles (
    id SERIAL PRIMARY KEY,
    scheduled_at TIMESTAMPTZ DEFAULT NOW(),
    winner_scan_id INTEGER REFERENCES scans(id),
    prize_amount INTEGER DEFAULT 0,
    status TEXT DEFAULT 'pending',
    payout_deadline TIMESTAMPTZ,
    winner_name TEXT DEFAULT '',
    winning_code TEXT DEFAULT '',
    payout_status TEXT DEFAULT '',
    payout_choice TEXT DEFAULT '',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Bottles
CREATE TABLE IF NOT EXISTS bottles (
    id SERIAL PRIMARY KEY,
    bottle_id TEXT UNIQUE NOT NULL,
    batch TEXT DEFAULT '',
    year TEXT DEFAULT '',
    assigned_to INTEGER REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Admins
CREATE TABLE IF NOT EXISTS admins (
    id SERIAL PRIMARY KEY,
    telegram_id BIGINT UNIQUE NOT NULL,
    name TEXT DEFAULT '',
    added_by INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Admin Codes
CREATE TABLE IF NOT EXISTS admin_codes (
    id SERIAL PRIMARY KEY,
    code TEXT NOT NULL,
    telegram_id BIGINT NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    used SMALLINT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Settings
CREATE TABLE IF NOT EXISTS settings (
    id SERIAL PRIMARY KEY,
    key TEXT UNIQUE NOT NULL,
    value TEXT DEFAULT '',
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- User QR Activations
CREATE TABLE IF NOT EXISTS user_qr_activations (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    qr_code TEXT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_users_telegram_id ON users(telegram_id);
CREATE INDEX IF NOT EXISTS idx_qr_codes_code ON qr_codes(code);
CREATE INDEX IF NOT EXISTS idx_qr_codes_status ON qr_codes(status);
CREATE INDEX IF NOT EXISTS idx_scans_user_id ON scans(user_id);
CREATE INDEX IF NOT EXISTS idx_scans_code_id ON scans(code_id);
CREATE INDEX IF NOT EXISTS idx_points_log_user_id ON points_log(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(user_id, read);
CREATE INDEX IF NOT EXISTS idx_bottles_bottle_id ON bottles(bottle_id);
CREATE INDEX IF NOT EXISTS idx_bottles_assigned_to ON bottles(assigned_to);
CREATE INDEX IF NOT EXISTS idx_bottles_year_batch ON bottles(year, batch);
CREATE INDEX IF NOT EXISTS idx_admins_telegram_id ON admins(telegram_id);
CREATE INDEX IF NOT EXISTS idx_prizes_category_id ON prizes(category_id);
CREATE INDEX IF NOT EXISTS idx_orders_user_id ON orders(user_id);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_raffles_status ON raffles(status);
CREATE INDEX IF NOT EXISTS idx_settings_key ON settings(key);
CREATE INDEX IF NOT EXISTS idx_user_qr_activations_user_id ON user_qr_activations(user_id);
CREATE INDEX IF NOT EXISTS idx_user_qr_activations_qr_code ON user_qr_activations(qr_code);
