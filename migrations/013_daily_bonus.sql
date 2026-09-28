-- Ежедневный бонус: серия из 7 дней
ALTER TABLE users ADD COLUMN IF NOT EXISTS daily_streak INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS daily_last_bonus DATE;

-- Сентинел для первой записи (чтобы WHERE daily_last_bonus <> date работал атомарно)
UPDATE users SET daily_last_bonus = DATE '1970-01-01' WHERE daily_last_bonus IS NULL;
ALTER TABLE users ALTER COLUMN daily_last_bonus SET DEFAULT DATE '1970-01-01';
ALTER TABLE users ALTER COLUMN daily_last_bonus SET NOT NULL;
