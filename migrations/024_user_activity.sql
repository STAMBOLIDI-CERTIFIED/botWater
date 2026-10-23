-- 024: активность пользователей в мини-аппе (статистика прочтения рассылок)

ALTER TABLE users ADD COLUMN IF NOT EXISTS last_active_at TIMESTAMPTZ;

CREATE INDEX IF NOT EXISTS idx_users_last_active_at ON users (last_active_at);

-- Статистику ведём только для рассылок, отправленных после включения трекинга.
ALTER TABLE broadcasts ADD COLUMN IF NOT EXISTS reads_tracked BOOLEAN NOT NULL DEFAULT FALSE;
