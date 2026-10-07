-- 017: рассылки из админки + учёт сообщений бота для автоочистки чата

CREATE TABLE IF NOT EXISTS broadcasts (
    id BIGSERIAL PRIMARY KEY,
    title TEXT NOT NULL DEFAULT '',
    body TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'sending',
    recipients INTEGER NOT NULL DEFAULT 0,
    sent_count INTEGER NOT NULL DEFAULT 0,
    error TEXT NOT NULL DEFAULT '',
    created_by TEXT NOT NULL DEFAULT '',
    media TEXT NOT NULL DEFAULT '[]',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    sent_at TIMESTAMPTZ
);

-- Сообщения, отправленные ботом. delete_after IS NULL — не удалять автоматически
-- (рассылки из админки); иначе сообщение удаляется в указанный момент.
CREATE TABLE IF NOT EXISTS bot_messages (
    id BIGSERIAL PRIMARY KEY,
    chat_id BIGINT NOT NULL,
    message_id BIGINT NOT NULL,
    broadcast_id BIGINT REFERENCES broadcasts(id) ON DELETE CASCADE,
    delete_after TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_bot_messages_delete_after ON bot_messages (delete_after);
CREATE INDEX IF NOT EXISTS idx_bot_messages_chat_id ON bot_messages (chat_id);
CREATE INDEX IF NOT EXISTS idx_bot_messages_broadcast_id ON bot_messages (broadcast_id);
