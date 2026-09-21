-- ============================================================
-- Support Chat — чат поддержки пользователь ↔ администратор
-- ============================================================

-- ─── Support chats ───────────────────────────────────
CREATE TABLE IF NOT EXISTS support_chats (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    status TEXT DEFAULT 'open',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_support_chats_user_id ON support_chats(user_id);
CREATE INDEX IF NOT EXISTS idx_support_chats_status ON support_chats(status);

-- ─── Support messages ────────────────────────────────
CREATE TABLE IF NOT EXISTS support_messages (
    id BIGSERIAL PRIMARY KEY,
    chat_id BIGINT REFERENCES support_chats(id) ON DELETE CASCADE,
    sender_type TEXT NOT NULL DEFAULT 'user',
    message TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_support_messages_chat_id ON support_messages(chat_id);

-- ─── RLS ─────────────────────────────────────────────
ALTER TABLE support_chats ENABLE ROW LEVEL SECURITY;
ALTER TABLE support_messages ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Allow full access" ON support_chats FOR ALL USING (true);
CREATE POLICY "Allow full access" ON support_messages FOR ALL USING (true);
