-- Партнёр-источник: закрепление на 12 месяцев.
-- Источник = партнёр первого скана, но максимум на 12 мес.;
-- после окончания срока следующий отсканированный QR становится новым источником
-- (до этого момента комиссии продолжает получать прежний источник).

-- ─── Явная привязка источника ────────────────────────
ALTER TABLE users ADD COLUMN IF NOT EXISTS source_partner_id INTEGER;
ALTER TABLE users ADD COLUMN IF NOT EXISTS source_bound_at TIMESTAMPTZ;

-- ─── Бэкфилл по истории сканов (цепочка «первый скан,
--     затем первый скан позже bound + 12 мес.») ──────
DO $$
DECLARE
    u RECORD;
    cur_cat INTEGER;
    cur_ts TIMESTAMPTZ;
    nxt RECORD;
BEGIN
    FOR u IN SELECT id FROM users WHERE source_partner_id IS NULL AND source_bound_at IS NULL LOOP
        SELECT ps.category_id, ps.scanned_at INTO cur_cat, cur_ts
        FROM partner_scans ps
        WHERE ps.user_id = u.id AND ps.category_id IS NOT NULL
        ORDER BY ps.scanned_at ASC
        LIMIT 1;

        IF cur_cat IS NULL THEN
            CONTINUE;
        END IF;

        LOOP
            SELECT ps.category_id, ps.scanned_at INTO nxt
            FROM partner_scans ps
            WHERE ps.user_id = u.id
              AND ps.category_id IS NOT NULL
              AND ps.scanned_at > cur_ts + INTERVAL '12 months'
            ORDER BY ps.scanned_at ASC
            LIMIT 1;
            EXIT WHEN nxt.category_id IS NULL;
            cur_cat := nxt.category_id;
            cur_ts := nxt.scanned_at;
        END LOOP;

        UPDATE users
        SET source_partner_id = cur_cat, source_bound_at = cur_ts
        WHERE id = u.id;
    END LOOP;
END $$;

-- ─── Настройка срока закрепления (мес.) ──────────────
INSERT INTO settings (key, value) VALUES ('source_lock_months', '12')
ON CONFLICT (key) DO NOTHING;

-- ─── Подарок выдаётся однократно на партнёра ─────────
-- Дедупликация: из неревокнутых дублей оставить самую раннюю запись.
DELETE FROM user_gifts g
USING user_gifts k
WHERE g.user_id = k.user_id
  AND g.category_id = k.category_id
  AND g.id > k.id
  AND g.status <> 'revoked'
  AND k.status <> 'revoked';

CREATE UNIQUE INDEX IF NOT EXISTS uq_user_gifts_user_category
    ON user_gifts (user_id, category_id)
    WHERE status <> 'revoked';
