-- 018: картинки в Postgres — чтобы не зависеть от git-деплоя файлов
-- path — относительный путь внутри public/uploads (совпадает с URL /uploads/<path>)

CREATE TABLE IF NOT EXISTS media_files (
    id BIGSERIAL PRIMARY KEY,
    path TEXT NOT NULL UNIQUE,
    mime TEXT NOT NULL DEFAULT 'image/jpeg',
    bytes BYTEA NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_media_files_path ON media_files (path);
