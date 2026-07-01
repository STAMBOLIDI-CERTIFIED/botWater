import asyncpg
import logging

logger = logging.getLogger(__name__)


class Database:
    def __init__(self):
        self.pool = None

    async def connect(self):
        from .config import get_settings
        s = get_settings()
        self.pool = await asyncpg.create_pool(
            host=s["DB_HOST"],
            port=s["DB_PORT"],
            user=s["DB_USER"],
            password=s["DB_PASS"],
            database=s["DB_NAME"],
            min_size=1,
            max_size=10,
        )
        await self._migrate()

    async def close(self):
        if self.pool:
            await self.pool.close()

    async def _migrate(self):
        async with self.pool.acquire() as conn:
            await conn.execute("""
                CREATE TABLE IF NOT EXISTS users (
                    id SERIAL PRIMARY KEY,
                    telegram_id BIGINT UNIQUE NOT NULL,
                    name TEXT,
                    phone TEXT,
                    balance INTEGER DEFAULT 0,
                    step TEXT DEFAULT 'start',
                    agreed_terms INTEGER DEFAULT 0,
                    passport_fio TEXT,
                    passport_series TEXT,
                    passport_snumber TEXT DEFAULT '',
                    passport_inn TEXT,
                    start_payload TEXT DEFAULT '',
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW()
                );
                CREATE TABLE IF NOT EXISTS qr_codes (
                    id SERIAL PRIMARY KEY,
                    code TEXT UNIQUE NOT NULL,
                    batch TEXT DEFAULT '',
                    status TEXT DEFAULT 'active',
                    winner_id INTEGER REFERENCES users(id),
                    won_at TIMESTAMP,
                    created_at TIMESTAMP DEFAULT NOW()
                );
                CREATE TABLE IF NOT EXISTS scans (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL REFERENCES users(id),
                    code_id INTEGER NOT NULL REFERENCES qr_codes(id),
                    scanned_at TIMESTAMP DEFAULT NOW(),
                    UNIQUE(user_id, code_id)
                );
                CREATE TABLE IF NOT EXISTS points_log (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL REFERENCES users(id),
                    amount INTEGER NOT NULL,
                    type TEXT NOT NULL,
                    description TEXT DEFAULT '',
                    created_at TIMESTAMP DEFAULT NOW()
                );
                CREATE TABLE IF NOT EXISTS prizes (
                    id SERIAL PRIMARY KEY,
                    name TEXT NOT NULL,
                    description TEXT DEFAULT '',
                    image_url TEXT DEFAULT '',
                    price_points INTEGER NOT NULL,
                    active INTEGER DEFAULT 1,
                    created_at TIMESTAMP DEFAULT NOW()
                );
                CREATE TABLE IF NOT EXISTS orders (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL REFERENCES users(id),
                    prize_id INTEGER NOT NULL REFERENCES prizes(id),
                    status TEXT DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT NOW()
                );
                CREATE TABLE IF NOT EXISTS raffles (
                    id SERIAL PRIMARY KEY,
                    scheduled_at TIMESTAMP NOT NULL,
                    winner_scan_id INTEGER REFERENCES scans(id),
                    prize_amount INTEGER DEFAULT 0,
                    status TEXT DEFAULT 'pending',
                    payout_choice TEXT,
                    payout_status TEXT,
                    payout_deadline TIMESTAMP,
                    created_at TIMESTAMP DEFAULT NOW()
                );
                CREATE TABLE IF NOT EXISTS admin_codes (
                    id SERIAL PRIMARY KEY,
                    code TEXT UNIQUE NOT NULL,
                    telegram_id BIGINT NOT NULL,
                    used INTEGER DEFAULT 0,
                    expires_at TIMESTAMP NOT NULL,
                    created_at TIMESTAMP DEFAULT NOW()
                );
                CREATE TABLE IF NOT EXISTS admins (
                    id SERIAL PRIMARY KEY,
                    telegram_id BIGINT UNIQUE NOT NULL,
                    name TEXT DEFAULT '',
                    added_by INTEGER DEFAULT 0,
                    created_at TIMESTAMP DEFAULT NOW()
                );
                CREATE TABLE IF NOT EXISTS bottles (
                    id SERIAL PRIMARY KEY,
                    bottle_id TEXT UNIQUE NOT NULL,
                    batch TEXT DEFAULT '',
                    year TEXT DEFAULT '',
                    assigned_to INTEGER REFERENCES users(id),
                    assigned_at TIMESTAMP,
                    created_at TIMESTAMP DEFAULT NOW()
                );
            """)

    # ─── Users ──────────────────────────────────────────

    async def get_user(self, telegram_id: int) -> dict | None:
        async with self.pool.acquire() as conn:
            row = await conn.fetchrow("SELECT * FROM users WHERE telegram_id = $1", telegram_id)
            return dict(row) if row else None

    async def get_user_by_id(self, user_id: int) -> dict | None:
        async with self.pool.acquire() as conn:
            row = await conn.fetchrow("SELECT * FROM users WHERE id = $1", user_id)
            return dict(row) if row else None

    async def create_user(self, telegram_id: int, name: str = "", step: str = "start", start_payload: str = "") -> dict:
        async with self.pool.acquire() as conn:
            row = await conn.fetchrow(
                """INSERT INTO users (telegram_id, name, step, start_payload)
                   VALUES ($1, $2, $3, $4)
                   ON CONFLICT (telegram_id) DO UPDATE SET name = COALESCE(NULLIF(users.name, ''), $2)
                   RETURNING *""",
                telegram_id, name, step, start_payload,
            )
            return dict(row)

    async def update_user_step(self, telegram_id: int, step: str):
        async with self.pool.acquire() as conn:
            await conn.execute("UPDATE users SET step = $1, updated_at = NOW() WHERE telegram_id = $2", step, telegram_id)

    async def update_user_name(self, telegram_id: int, name: str):
        async with self.pool.acquire() as conn:
            await conn.execute("UPDATE users SET name = $1, updated_at = NOW() WHERE telegram_id = $2", name, telegram_id)

    async def update_user_phone(self, telegram_id: int, phone: str):
        async with self.pool.acquire() as conn:
            await conn.execute("UPDATE users SET phone = $1, updated_at = NOW() WHERE telegram_id = $2", phone, telegram_id)

    async def accept_terms(self, telegram_id: int):
        async with self.pool.acquire() as conn:
            await conn.execute("UPDATE users SET agreed_terms = 1, step = 'menu', updated_at = NOW() WHERE telegram_id = $1", telegram_id)

    async def update_passport_data(self, telegram_id: int, fio: str = "", snumber: str = "", inn: str = ""):
        async with self.pool.acquire() as conn:
            fields = []
            args = []
            i = 1
            if fio:
                fields.append(f"passport_fio = ${i}"); args.append(fio); i += 1
            if snumber:
                fields.append(f"passport_snumber = ${i}"); args.append(snumber); i += 1
            if inn:
                fields.append(f"passport_inn = ${i}"); args.append(inn); i += 1
            if fields:
                args.append(telegram_id)
                await conn.execute(f"UPDATE users SET {', '.join(fields)}, updated_at = NOW() WHERE telegram_id = ${i}", *args)

    async def update_user_balance(self, telegram_id: int, balance: int):
        async with self.pool.acquire() as conn:
            await conn.execute("UPDATE users SET balance = $1, updated_at = NOW() WHERE telegram_id = $2", balance, telegram_id)

    async def get_all_users(self) -> list[dict]:
        async with self.pool.acquire() as conn:
            rows = await conn.fetch("SELECT * FROM users ORDER BY id DESC")
            return [dict(r) for r in rows]

    async def get_user_stats(self, telegram_id: int) -> dict:
        async with self.pool.acquire() as conn:
            user = await conn.fetchrow("SELECT balance FROM users WHERE telegram_id = $1", telegram_id)
            scan_count = await conn.fetchval(
                """SELECT COUNT(*) FROM scans s
                   JOIN users u ON u.id = s.user_id
                   WHERE u.telegram_id = $1""", telegram_id)
            return {"balance": user["balance"] if user else 0, "total_scans": scan_count or 0}

    async def search_users(self, query: str) -> list[dict]:
        async with self.pool.acquire() as conn:
            pattern = f"%{query}%"
            rows = await conn.fetch(
                "SELECT * FROM users WHERE name ILIKE $1 OR phone ILIKE $1 OR telegram_id::text ILIKE $1 ORDER BY id DESC",
                pattern,
            )
            return [dict(r) for r in rows]

    # ─── QR Codes ───────────────────────────────────────

    async def get_all_codes(self) -> list[dict]:
        async with self.pool.acquire() as conn:
            rows = await conn.fetch("""
                SELECT q.*, u.name as winner_name
                FROM qr_codes q
                LEFT JOIN users u ON u.id = q.winner_id
                ORDER BY q.id DESC
            """)
            return [dict(r) for r in rows]

    async def get_active_codes_count(self) -> int:
        async with self.pool.acquire() as conn:
            return await conn.fetchval("SELECT COUNT(*) FROM qr_codes WHERE status = 'active'")

    async def register_code_batch(self, codes: list[str], batch: str) -> int:
        async with self.pool.acquire() as conn:
            count = 0
            for code in codes:
                try:
                    await conn.execute(
                        "INSERT INTO qr_codes (code, batch) VALUES ($1, $2) ON CONFLICT DO NOTHING",
                        code, batch,
                    )
                    count += 1
                except Exception:
                    pass
            return count

    async def get_code_by_value(self, code: str) -> dict | None:
        async with self.pool.acquire() as conn:
            row = await conn.fetchrow("SELECT * FROM qr_codes WHERE code = $1", code)
            return dict(row) if row else None

    async def mark_code_won(self, code_id: int, winner_user_id: int):
        async with self.pool.acquire() as conn:
            await conn.execute(
                "UPDATE qr_codes SET status = 'won', winner_id = $1, won_at = NOW() WHERE id = $2",
                winner_user_id, code_id,
            )

    async def get_code_stats(self) -> dict:
        async with self.pool.acquire() as conn:
            total = await conn.fetchval("SELECT COUNT(*) FROM qr_codes")
            active = await conn.fetchval("SELECT COUNT(*) FROM qr_codes WHERE status = 'active'")
            won = await conn.fetchval("SELECT COUNT(*) FROM qr_codes WHERE status = 'won'")
            used = await conn.fetchval("SELECT COUNT(*) FROM qr_codes WHERE status = 'used'")
            return {"total": total or 0, "active": active or 0, "won": won or 0, "used": used or 0}

    # ─── Scans ──────────────────────────────────────────

    async def add_scan(self, user_id: int, code_id: int) -> bool:
        async with self.pool.acquire() as conn:
            try:
                await conn.execute(
                    "INSERT INTO scans (user_id, code_id) VALUES ($1, $2) ON CONFLICT DO NOTHING",
                    user_id, code_id,
                )
                return True
            except Exception:
                return False

    async def get_user_scans(self, user_id: int) -> list[dict]:
        async with self.pool.acquire() as conn:
            rows = await conn.fetch("""
                SELECT s.*, q.code, q.batch
                FROM scans s
                JOIN qr_codes q ON q.id = s.code_id
                WHERE s.user_id = $1
                ORDER BY s.scanned_at DESC
            """, user_id)
            return [dict(r) for r in rows]

    async def get_scans(self, telegram_id: int) -> list[dict]:
        async with self.pool.acquire() as conn:
            rows = await conn.fetch("""
                SELECT s.*, q.code, q.batch, u.name as user_name
                FROM scans s
                JOIN qr_codes q ON q.id = s.code_id
                JOIN users u ON u.id = s.user_id
                WHERE u.telegram_id = $1
                ORDER BY s.scanned_at DESC
            """, telegram_id)
            return [dict(r) for r in rows]

    async def count_user_scans(self, user_id: int) -> int:
        async with self.pool.acquire() as conn:
            return await conn.fetchval("SELECT COUNT(*) FROM scans WHERE user_id = $1", user_id) or 0

    # ─── Points ─────────────────────────────────────────

    async def add_balance(self, telegram_id: int, amount: int, typ: str = "admin", description: str = ""):
        async with self.pool.acquire() as conn:
            await conn.execute(
                "UPDATE users SET balance = balance + $1, updated_at = NOW() WHERE telegram_id = $2",
                amount, telegram_id,
            )
            user = await conn.fetchrow("SELECT id FROM users WHERE telegram_id = $1", telegram_id)
            if user:
                await conn.execute(
                    "INSERT INTO points_log (user_id, amount, type, description) VALUES ($1, $2, $3, $4)",
                    user["id"], amount, typ, description,
                )

    async def get_points_log(self, telegram_id: int) -> list[dict]:
        async with self.pool.acquire() as conn:
            rows = await conn.fetch("""
                SELECT p.* FROM points_log p
                JOIN users u ON u.id = p.user_id
                WHERE u.telegram_id = $1
                ORDER BY p.created_at DESC LIMIT 50
            """, telegram_id)
            return [dict(r) for r in rows]

    # ─── Prizes ─────────────────────────────────────────

    async def get_prizes(self) -> list[dict]:
        async with self.pool.acquire() as conn:
            rows = await conn.fetch("SELECT * FROM prizes WHERE active = 1 ORDER BY price_points ASC")
            return [dict(r) for r in rows]

    async def get_prize(self, prize_id: int) -> dict | None:
        async with self.pool.acquire() as conn:
            row = await conn.fetchrow("SELECT * FROM prizes WHERE id = $1", prize_id)
            return dict(row) if row else None

    async def add_prize(self, name: str, description: str, image_url: str, price_points: int):
        async with self.pool.acquire() as conn:
            await conn.execute(
                "INSERT INTO prizes (name, description, image_url, price_points) VALUES ($1, $2, $3, $4)",
                name, description, image_url, price_points,
            )

    async def delete_prize(self, prize_id: int):
        async with self.pool.acquire() as conn:
            await conn.execute("DELETE FROM prizes WHERE id = $1", prize_id)

    # ─── Orders ─────────────────────────────────────────

    async def create_order(self, user_id: int, prize_id: int) -> int:
        async with self.pool.acquire() as conn:
            row = await conn.fetchrow(
                "INSERT INTO orders (user_id, prize_id) VALUES ($1, $2) RETURNING id",
                user_id, prize_id,
            )
            return row["id"]

    async def get_pending_orders(self) -> list[dict]:
        async with self.pool.acquire() as conn:
            rows = await conn.fetch("""
                SELECT o.*, u.name, u.telegram_id, u.phone, p.name as prize_name
                FROM orders o
                JOIN users u ON u.id = o.user_id
                JOIN prizes p ON p.id = o.prize_id
                WHERE o.status = 'pending'
                ORDER BY o.created_at DESC
            """)
            return [dict(r) for r in rows]

    async def complete_order(self, order_id: int):
        async with self.pool.acquire() as conn:
            await conn.execute("UPDATE orders SET status = 'completed' WHERE id = $1", order_id)

    # ─── Raffles ────────────────────────────────────────

    async def get_raffles(self) -> list[dict]:
        async with self.pool.acquire() as conn:
            rows = await conn.fetch("""
                SELECT r.*, u.name as winner_name, u.telegram_id, q.code as winning_code
                FROM raffles r
                LEFT JOIN scans s ON s.id = r.winner_scan_id
                LEFT JOIN qr_codes q ON q.id = s.code_id
                LEFT JOIN users u ON u.id = q.winner_id
                ORDER BY r.created_at DESC
            """)
            return [dict(r) for r in rows]

    async def get_raffle_results(self) -> list[dict]:
        async with self.pool.acquire() as conn:
            rows = await conn.fetch("""
                SELECT r.*, u.name as winner_name, q.code as winning_code
                FROM raffles r
                LEFT JOIN scans s ON s.id = r.winner_scan_id
                LEFT JOIN qr_codes q ON q.id = s.code_id
                LEFT JOIN users u ON u.id = q.winner_id
                WHERE r.status = 'completed'
                ORDER BY r.created_at DESC
            """)
            return [dict(r) for r in rows]

    async def get_raffle_stats(self) -> dict:
        async with self.pool.acquire() as conn:
            total = await conn.fetchval("SELECT COUNT(*) FROM raffles") or 0
            completed = await conn.fetchval("SELECT COUNT(*) FROM raffles WHERE status = 'completed'") or 0
            return {"total_raffles": total, "completed": completed}

    async def run_raffle(self, prize_amount: int) -> dict | None:
        async with self.pool.acquire() as conn:
            active_code = await conn.fetchrow(
                "SELECT q.* FROM qr_codes q WHERE q.status = 'active' ORDER BY RANDOM() LIMIT 1",
            )
            if not active_code:
                return None
            scan = await conn.fetchrow(
                "SELECT s.* FROM scans s WHERE s.code_id = $1 ORDER BY s.scanned_at DESC LIMIT 1",
                active_code["id"],
            )
            if not scan:
                return None
            winner = await conn.fetchrow("SELECT * FROM users WHERE id = $1", scan["user_id"])
            await conn.execute(
                "UPDATE qr_codes SET status = 'won', winner_id = $1, won_at = NOW() WHERE id = $2",
                scan["user_id"], active_code["id"],
            )
            deadline = await conn.fetchval("SELECT NOW() + INTERVAL '7 days'")
            raffle = await conn.fetchrow(
                """INSERT INTO raffles (scheduled_at, winner_scan_id, prize_amount, status, payout_deadline)
                   VALUES (NOW(), $1, $2, 'completed', $3) RETURNING *""",
                scan["id"], prize_amount, deadline,
            )
            return {
                "raffle": dict(raffle),
                "winner": {
                    "telegram_id": winner["telegram_id"],
                    "name": winner["name"],
                    "code": active_code["code"],
                },
                "raffle_id": raffle["id"],
            }

    async def mark_payout_paid(self, raffle_id: int):
        async with self.pool.acquire() as conn:
            await conn.execute(
                "UPDATE raffles SET payout_status = 'paid', payout_choice = 'money' WHERE id = $1",
                raffle_id,
            )

    async def set_payout_choice(self, raffle_id: int, choice: str):
        async with self.pool.acquire() as conn:
            if choice == "points":
                raffle = await conn.fetchrow("SELECT * FROM raffles WHERE id = $1", raffle_id)
                if raffle:
                    scan = await conn.fetchrow("SELECT * FROM scans WHERE id = $1", raffle["winner_scan_id"])
                    if scan:
                        user = await conn.fetchrow("SELECT * FROM users WHERE id = $1", scan["user_id"])
                        if user:
                            points = raffle["prize_amount"] * 10
                            await conn.execute(
                                "UPDATE users SET balance = balance + $1, updated_at = NOW() WHERE id = $2",
                                points, user["id"],
                            )
                            await conn.execute(
                                "INSERT INTO points_log (user_id, amount, type, description) VALUES ($1, $2, 'conversion', 'Конвертация выигрыша в баллы')",
                                user["id"], points,
                            )
                            await conn.execute(
                                "UPDATE raffles SET payout_status = 'converted', payout_choice = 'points' WHERE id = $1",
                                raffle_id,
                            )
            else:
                await conn.execute(
                    "UPDATE raffles SET payout_choice = $1 WHERE id = $2", choice, raffle_id,
                )

    async def get_pending_payouts(self) -> list[dict]:
        async with self.pool.acquire() as conn:
            rows = await conn.fetch("""
                SELECT r.*, u.name, u.telegram_id, u.phone, u.passport_fio, u.passport_snumber, u.passport_inn
                FROM raffles r
                JOIN scans s ON s.id = r.winner_scan_id
                JOIN users u ON u.id = s.user_id
                WHERE r.status = 'completed' AND r.payout_choice = 'money' AND r.payout_status IS NULL
                ORDER BY r.created_at DESC
            """)
            return [dict(r) for r in rows]

    async def get_user_raffle_wins(self, telegram_id: int) -> list[dict]:
        async with self.pool.acquire() as conn:
            rows = await conn.fetch("""
                SELECT r.*, q.code
                FROM raffles r
                JOIN scans s ON s.id = r.winner_scan_id
                JOIN qr_codes q ON q.id = s.code_id
                JOIN users u ON u.id = s.user_id
                WHERE u.telegram_id = $1 AND r.status = 'completed'
                ORDER BY r.created_at DESC
            """, telegram_id)
            return [dict(r) for r in rows]

    async def process_expired_payouts(self):
        async with self.pool.acquire() as conn:
            expired = await conn.fetch("""
                SELECT r.*, s.user_id
                FROM raffles r
                JOIN scans s ON s.id = r.winner_scan_id
                WHERE r.status = 'completed'
                  AND r.payout_choice = 'money'
                  AND r.payout_status IS NULL
                  AND r.payout_deadline < NOW()
            """)
            for raffle in expired:
                points = raffle["prize_amount"] * 5
                await conn.execute(
                    "UPDATE users SET balance = balance + $1, updated_at = NOW() WHERE id = $2",
                    points, raffle["user_id"],
                )
                await conn.execute(
                    "INSERT INTO points_log (user_id, amount, type, description) VALUES ($1, $2, 'conversion', 'Автоконвертация просроченной выплаты')",
                    raffle["user_id"], points,
                )
                await conn.execute(
                    "UPDATE raffles SET payout_status = 'converted', payout_choice = 'points' WHERE id = $1",
                    raffle["id"],
                )
            return len(expired)

    # ─── Bottles ────────────────────────────────────────

    async def create_bottles_batch(self, count: int, batch: str, year: str) -> list[str]:
        import random
        import string

        alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789"
        async with self.pool.acquire() as conn:
            last = await conn.fetchval(
                "SELECT bottle_id FROM bottles WHERE year = $1 AND batch = $2 ORDER BY id DESC LIMIT 1",
                year, batch,
            )
            seq_start = 1
            if last:
                parts = last.split("-")
                try:
                    seq_start = int(parts[3]) + 1 if len(parts) > 3 else 1
                except (ValueError, IndexError):
                    seq_start = 1

            ids = []
            for i in range(count):
                seq = seq_start + i
                rand = "".join(random.choices(alphabet, k=4))
                bottle_id = f"BTL-{year}-{batch}-{seq:04d}-{rand}"
                for attempt in range(10):
                    existing = await conn.fetchval("SELECT 1 FROM bottles WHERE bottle_id = $1", bottle_id)
                    if not existing:
                        break
                    rand = "".join(random.choices(alphabet, k=4))
                    bottle_id = f"BTL-{year}-{batch}-{seq:04d}-{rand}"
                await conn.execute(
                    "INSERT INTO bottles (bottle_id, batch, year) VALUES ($1, $2, $3) ON CONFLICT DO NOTHING",
                    bottle_id, batch, year,
                )
                ids.append(bottle_id)
            return ids

    async def get_bottles(self, batch: str = "", sort: str = "id", direction: str = "DESC",
                          limit: int = 200, offset: int = 0) -> list[dict]:
        allowed_sort = {"id", "bottle_id", "year", "batch", "assigned_to", "created_at"}
        sort_col = sort if sort in allowed_sort else "id"
        dir_ = "ASC" if direction.upper() == "ASC" else "DESC"
        async with self.pool.acquire() as conn:
            if batch:
                rows = await conn.fetch(
                    f"SELECT * FROM bottles WHERE batch = $1 ORDER BY {sort_col} {dir_} LIMIT $2 OFFSET $3",
                    batch, limit, offset,
                )
            else:
                rows = await conn.fetch(
                    f"SELECT * FROM bottles ORDER BY {sort_col} {dir_} LIMIT $1 OFFSET $2",
                    limit, offset,
                )
            return [dict(r) for r in rows]

    async def get_bottle_by_code(self, bottle_id: str) -> dict | None:
        async with self.pool.acquire() as conn:
            row = await conn.fetchrow("SELECT * FROM bottles WHERE bottle_id = $1", bottle_id)
            return dict(row) if row else None

    async def search_bottles(self, query: str, sort: str = "id", direction: str = "DESC",
                             limit: int = 200, offset: int = 0) -> list[dict]:
        allowed_sort = {"id", "bottle_id", "year", "batch", "assigned_to", "created_at"}
        sort_col = sort if sort in allowed_sort else "id"
        dir_ = "ASC" if direction.upper() == "ASC" else "DESC"
        pattern = f"%{query}%"
        async with self.pool.acquire() as conn:
            rows = await conn.fetch(
                f"""SELECT * FROM bottles
                    WHERE bottle_id ILIKE $1 OR batch ILIKE $1 OR year ILIKE $1
                    ORDER BY {sort_col} {dir_} LIMIT $2 OFFSET $3""",
                pattern, limit, offset,
            )
            return [dict(r) for r in rows]

    async def delete_bottle(self, bottle_id: str) -> bool:
        async with self.pool.acquire() as conn:
            result = await conn.execute("DELETE FROM bottles WHERE bottle_id = $1", bottle_id)
            return "DELETE 1" in result

    async def delete_batch(self, year: str, batch: str) -> int:
        async with self.pool.acquire() as conn:
            result = await conn.execute("DELETE FROM bottles WHERE year = $1 AND batch = $2", year, batch)
            parts = result.split()
            return int(parts[-1]) if len(parts) > 1 else 0

    async def get_bottle_batches(self) -> list[dict]:
        async with self.pool.acquire() as conn:
            rows = await conn.fetch("""
                SELECT year, batch, COUNT(*) as count
                FROM bottles
                GROUP BY year, batch
                ORDER BY year DESC, batch DESC
            """)
            return [dict(r) for r in rows]

    async def count_bottles(self, batch: str = "", search: str = "") -> int:
        async with self.pool.acquire() as conn:
            if search:
                pattern = f"%{search}%"
                return await conn.fetchval(
                    "SELECT COUNT(*) FROM bottles WHERE bottle_id ILIKE $1 OR batch ILIKE $1 OR year ILIKE $1",
                    pattern,
                ) or 0
            if batch:
                return await conn.fetchval("SELECT COUNT(*) FROM bottles WHERE batch = $1", batch) or 0
            return await conn.fetchval("SELECT COUNT(*) FROM bottles") or 0

    async def assign_bottle(self, bottle_id: str, user_id: int):
        async with self.pool.acquire() as conn:
            await conn.execute(
                "UPDATE bottles SET assigned_to = $1, assigned_at = NOW() WHERE bottle_id = $2",
                user_id, bottle_id,
            )

    async def get_unassigned_bottle_count(self) -> int:
        async with self.pool.acquire() as conn:
            return await conn.fetchval("SELECT COUNT(*) FROM bottles WHERE assigned_to IS NULL") or 0

    async def get_assigned_bottle_count(self) -> int:
        async with self.pool.acquire() as conn:
            return await conn.fetchval("SELECT COUNT(*) FROM bottles WHERE assigned_to IS NOT NULL") or 0

    # ─── Admins ─────────────────────────────────────────

    async def get_admins(self) -> list[dict]:
        async with self.pool.acquire() as conn:
            rows = await conn.fetch("SELECT * FROM admins ORDER BY created_at DESC")
            return [dict(r) for r in rows]

    async def is_admin(self, telegram_id: int) -> bool:
        from .config import get_settings
        s = get_settings()
        if telegram_id == s["SUPERADMIN_ID"]:
            return True
        if telegram_id in s["ADMIN_IDS"]:
            return True
        async with self.pool.acquire() as conn:
            row = await conn.fetchval("SELECT 1 FROM admins WHERE telegram_id = $1", telegram_id)
            return bool(row)

    async def add_admin(self, telegram_id: int, name: str = "", added_by: int = 0) -> bool:
        async with self.pool.acquire() as conn:
            try:
                await conn.execute(
                    "INSERT INTO admins (telegram_id, name, added_by) VALUES ($1, $2, $3) ON CONFLICT DO NOTHING",
                    telegram_id, name, added_by,
                )
                return True
            except Exception:
                return False

    async def remove_admin(self, telegram_id: int) -> bool:
        async with self.pool.acquire() as conn:
            result = await conn.execute("DELETE FROM admins WHERE telegram_id = $1", telegram_id)
            return "DELETE 1" in result

    # ─── Admin Codes ────────────────────────────────────

    async def create_access_code(self, telegram_id: int) -> str:
        import random
        code = str(random.randint(100000, 999999))
        async with self.pool.acquire() as conn:
            await conn.execute(
                "INSERT INTO admin_codes (code, telegram_id, expires_at) VALUES ($1, $2, NOW() + INTERVAL '5 minutes')",
                code, telegram_id,
            )
        return code

    async def validate_access_code(self, code: str, telegram_id: int) -> bool:
        async with self.pool.acquire() as conn:
            row = await conn.fetchrow(
                "SELECT * FROM admin_codes WHERE code = $1 AND telegram_id = $2 AND used = 0 AND expires_at > NOW()",
                code, telegram_id,
            )
            if row:
                await conn.execute("UPDATE admin_codes SET used = 1 WHERE id = $1", row["id"])
                return True
            return False
