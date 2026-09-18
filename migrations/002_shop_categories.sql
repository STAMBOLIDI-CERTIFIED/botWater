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
    qr_code TEXT DEFAULT '',
    scan_points INTEGER DEFAULT 10,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE prizes ADD COLUMN IF NOT EXISTS category_id INT REFERENCES shop_categories(id) ON DELETE SET NULL;

INSERT INTO shop_categories (title, subtitle, description, icon, color, sort_order, qr_code, scan_points) VALUES
('Space', 'Партнёрские призы', 'Бесплатные тренировки, аренда, скидки и турниры', '🚀', '#0EA5E9', 1, 'partner_1', 10),
('Badmintonist', 'Партнёрские призы', 'Скидки, бесплатная намотка, скидки на струны и воланы', '🏸', '#10B981', 2, 'partner_2', 10),
('Истокъ', 'Собственные призы', 'Фирменные товары и аксессуары компании Истокъ', '💧', '#C9A84C', 3, '', 10),
('Благотворительность', 'Пожертвуй баллы', 'Помоги провести турнир, посади дерево, поддержи проект', '❤️', '#EF4444', 4, '', 0);
