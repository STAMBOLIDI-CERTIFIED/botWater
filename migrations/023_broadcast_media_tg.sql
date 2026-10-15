-- 023: Telegram file_id фото рассылки — бот выдаёт последнюю рассылку новым пользователям

ALTER TABLE broadcasts ADD COLUMN IF NOT EXISTS media_tg TEXT NOT NULL DEFAULT '[]';
