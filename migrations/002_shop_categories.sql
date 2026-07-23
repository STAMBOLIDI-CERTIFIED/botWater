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

ALTER TABLE prizes ADD COLUMN IF NOT EXISTS category_id INT REFERENCES shop_categories(id) ON DELETE SET NULL;

INSERT INTO shop_categories (title, subtitle, description, icon, color, sort_order) VALUES
('Space', 'Партнёрские призы', 'Бесплатные тренировки, аренда, скидки и турниры', '🚀', '#0EA5E9', 1),
('Badmintonist', 'Партнёрские призы', 'Скидки, бесплатная намотка, скидки на струны и воланы', '🏸', '#10B981', 2),
('Истокъ', 'Собственные призы', 'Фирменные товары и аксессуары компании Истокъ', '💧', '#C9A84C', 3),
('Благотворительность', 'Пожертвуй баллы', 'Помоги провести турнир, посади дерево, поддержи проект', '❤️', '#EF4444', 4);
