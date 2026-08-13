import logging
import re
from datetime import datetime, timedelta

import asyncpg

logger = logging.getLogger(__name__)

_FILTER_RE = re.compile(r"^([a-z_0-9]+)=(eq|neq|gt|gte|lt|lte|ilike|is|not\.is)\.([^&]+)$")
_DOT_FILTER_RE = re.compile(r"^([a-z_0-9]+)\.(eq|neq|gt|gte|lt|lte|ilike|is|not\.is)\.([^,(]+)$")


class Database:
    def __init__(self):
        self.pool: asyncpg.Pool | None = None
        self._col_cache: dict[str, dict[str, str]] = {}

    # ─── Connection ───────────────────────────────────────

    async def connect(self):
        from .config import get_settings

        s = get_settings()
        dsn = (s.get("DATABASE_URL") or "").strip()
        if not dsn:
            dsn = (
                f"postgresql://{s['DB_USER']}:{s['DB_PASS']}"
                f"@{s['DB_HOST']}:{s['DB_PORT']}/{s['DB_NAME']}"
            )
        self.pool = await asyncpg.create_pool(dsn=dsn, min_size=1, max_size=10)
        logger.info("Postgres connected")

    async def close(self):
        if self.pool:
            await self.pool.close()
            self.pool = None

    # ─── Query builder (Supabase-REST-like → SQL) ────────

    async def _get_columns(self, conn, table: str) -> dict[str, str]:
        cached = self._col_cache.get(table)
        if cached:
            return cached
        rows = await conn.fetch(
            "SELECT column_name, data_type FROM information_schema.columns WHERE table_name=$1",
            table,
        )
        types = {r["column_name"]: r["data_type"] for r in rows}
        self._col_cache[table] = types
        return types

    def _typed_value(self, raw: str, coltype: str | None):
        v = raw
        if coltype in ("smallint", "integer", "bigint", "serial", "smallserial", "bigserial"):
            try:
                return int(v)
            except (TypeError, ValueError):
                return v
        if coltype in ("numeric", "real", "double precision", "money"):
            try:
                return float(v)
            except (TypeError, ValueError):
                return v
        if coltype == "boolean":
            return v.lower() in ("true", "t", "1")
        if coltype in ("timestamp with time zone", "timestamp without time zone"):
            try:
                return datetime.fromisoformat(v.replace("Z", "+00:00"))
            except ValueError:
                return v
        return v

    def _parse_params(self, params: str, col_types: dict[str, str] | None = None):
        select = "*"
        where: list[str] = []
        values: list = []
        order_by = ""
        limit = None
        offset = None
        if not params:
            return select, where, values, order_by, limit, offset

        for chunk in params.split("&"):
            if not chunk:
                continue
            key, _, value = chunk.partition("=")
            key = key.strip()
            value = value.strip()
            if key == "select":
                select = value
            elif key == "order":
                col, _, direction = value.partition(".")
                order_by = f"{col} {'DESC' if direction.lower() == 'desc' else 'ASC'}"
            elif key == "limit":
                try:
                    limit = int(value)
                except ValueError:
                    pass
            elif key == "offset":
                try:
                    offset = int(value)
                except ValueError:
                    pass
            elif key == "or":
                inner = value.strip("()")
                or_frags = []
                for cond in (x.strip() for x in inner.split(",") if x.strip()):
                    # inside or() the format is col.op.val (no '='); normalize
                    m = _DOT_FILTER_RE.match(cond)
                    if m:
                        cond = f"{m.group(1)}={m.group(2)}.{m.group(3)}"
                    frag = self._build_filter(values, cond, col_types)
                    if frag:
                        or_frags.append(frag)
                if or_frags:
                    where.append("(" + " OR ".join(or_frags) + ")")
            else:
                frag = self._build_filter(values, f"{key}={value}", col_types)
                if frag:
                    where.append(frag)
        return select, where, values, order_by, limit, offset

    def _build_filter(self, values: list, cond: str, col_types: dict[str, str] | None = None) -> str | None:
        m = _FILTER_RE.match(cond)
        if not m:
            return None
        col, op, raw = m.group(1), m.group(2), m.group(3)
        n = len(values) + 1

        if op == "is" and raw == "null":
            return f"{col} IS NULL"
        if op == "not.is" and raw == "null":
            return f"{col} IS NOT NULL"
        if op == "ilike":
            pattern = raw[1:-1] if raw.startswith("*") and raw.endswith("*") else raw
            values.append(f"%{pattern}%")
            return f"{col} ILIKE ${n}"

        sql_op = {
            "eq": "=", "neq": "<>", "gt": ">", "gte": ">=", "lt": "<", "lte": "<=",
        }.get(op)
        if sql_op is None:
            return None

        cast = ""
        if op in ("gt", "gte", "lt", "lte") and col_types and col_types.get(col) in (
            "timestamp with time zone", "timestamp without time zone",
        ):
            cast = "::timestamptz"
        values.append(self._typed_value(raw, col_types.get(col) if col_types else None))
        return f"{col} {sql_op} ${n}{cast}"

    @staticmethod
    def _looks_like_datetime(value: str) -> bool:
        return bool(re.search(r"\d{4}-\d{2}-\d{2}", value)) and ":" in value

    # ─── Core access ──────────────────────────────────────

    _TS_COLUMNS = {
        "scheduled_at", "payout_deadline", "expires_at", "created_at", "updated_at",
        "activated_at", "scanned_at", "assigned_at",
    }

    @classmethod
    def _conv_write(cls, data: dict) -> dict:
        out = dict(data)
        for col, val in out.items():
            if col in cls._TS_COLUMNS and isinstance(val, str) and val:
                try:
                    out[col] = datetime.fromisoformat(val.replace("Z", "+00:00"))
                except ValueError:
                    pass
        return out

    async def _fetch(self, table: str, params: str = "", method: str = "GET", json_data=None) -> list[dict]:
        async with self.pool.acquire() as conn:
            if method in ("GET",):
                col_types = await self._get_columns(conn, table)
                select, where, values, order_by, limit, offset = self._parse_params(params, col_types)
                sql = f'SELECT {select} FROM "{table}"'
                if where:
                    sql += " WHERE " + " AND ".join(where)
                if order_by:
                    sql += f" ORDER BY {order_by}"
                if limit is not None:
                    sql += f" LIMIT {limit}"
                if offset is not None:
                    sql += f" OFFSET {offset}"
                rows = await conn.fetch(sql, *values)
                return [dict(r) for r in rows]

            if method == "POST":
                data = self._conv_write(json_data or {})
                cols = list(data.keys())
                if not cols:
                    rows = await conn.fetch(f'INSERT INTO "{table}" DEFAULT VALUES RETURNING *')
                else:
                    placeholders = ", ".join(f"${i + 1}" for i in range(len(cols)))
                    sql = (
                        f'INSERT INTO "{table}" ({", ".join(cols)}) '
                        f"VALUES ({placeholders}) RETURNING *"
                    )
                    rows = await conn.fetch(sql, *[data[c] for c in cols])
                return [dict(r) for r in rows]

            if method == "PATCH":
                data = self._conv_write(json_data or {})
                cols = list(data.keys())
                if not cols:
                    return []
                _, where, values, _, _, _ = self._parse_params(params, await self._get_columns(conn, table))
                set_list = ", ".join(f"{c} = ${i + 1}" for i, c in enumerate(cols))
                sql = f'UPDATE "{table}" SET {set_list}'
                args = [data[c] for c in cols]
                if where:
                    where_sql = []
                    for frag in where:
                        frag = re.sub(
                            r"\$\d+",
                            lambda m: f"${int(m.group(0)[1:]) + len(cols)}",
                            frag,
                        )
                        where_sql.append(frag)
                    sql += " WHERE " + " AND ".join(where_sql)
                    args.extend(values)
                sql += " RETURNING *"
                rows = await conn.fetch(sql, *args)
                return [dict(r) for r in rows]

            if method == "DELETE":
                _, where, values, _, _, _ = self._parse_params(params, await self._get_columns(conn, table))
                sql = f'DELETE FROM "{table}"'
                if where:
                    sql += " WHERE " + " AND ".join(where)
                sql += " RETURNING *"
                rows = await conn.fetch(sql, *values)
                return [dict(r) for r in rows]

    async def _fetch_one(self, table: str, params: str = "") -> dict | None:
        rows = await self._fetch(table, params)
        return rows[0] if rows else None

    async def _rpc(self, fn: str, params: dict = None) -> any:
        logger.warning(f"RPC not supported on Postgres backend: {fn}")
        return None

    async def _delete_rows(self, table: str, params: str) -> int:
        rows = await self._fetch(table, params, method="DELETE")
        return len(rows)

    # ─── Migrate (schema is applied via SQL editor) ───────

    async def _migrate(self):
        pass

    # ─── Users ──────────────────────────────────────────

    async def get_user(self, telegram_id: int) -> dict | None:
        return await self._fetch_one("users", f"telegram_id=eq.{telegram_id}&select=*")

    async def get_user_by_id(self, user_id: int) -> dict | None:
        return await self._fetch_one("users", f"id=eq.{user_id}&select=*")

    async def create_user(self, telegram_id: int, name: str = "", step: str = "start", start_payload: str = "", phone: str = "") -> dict:
        existing = await self.get_user(telegram_id)
        if existing:
            if name and not existing.get("name"):
                await self._fetch("users", f"telegram_id=eq.{telegram_id}", "PATCH", {"name": name})
                existing["name"] = name
            if phone and not existing.get("phone"):
                await self._fetch("users", f"telegram_id=eq.{telegram_id}", "PATCH", {"phone": phone})
                existing["phone"] = phone
            return existing
        rows = await self._fetch("users", method="POST", json_data={
            "telegram_id": telegram_id,
            "name": name,
            "step": step,
            "start_payload": start_payload,
            "phone": phone,
        })
        return rows[0] if rows else {}

    async def update_user_step(self, telegram_id: int, step: str):
        await self._fetch("users", f"telegram_id=eq.{telegram_id}", "PATCH", {"step": step})

    async def update_user_name(self, telegram_id: int, name: str):
        await self._fetch("users", f"telegram_id=eq.{telegram_id}", "PATCH", {"name": name})

    async def update_user_phone(self, telegram_id: int, phone: str):
        logger.info(f"update_user_phone telegram_id={telegram_id} phone={phone!r}")
        result = await self._fetch("users", f"telegram_id=eq.{telegram_id}", "PATCH", {"phone": phone})
        logger.info(f"update_user_phone result: {result}")

    async def accept_terms(self, telegram_id: int):
        await self._fetch("users", f"telegram_id=eq.{telegram_id}", "PATCH", {"agreed_terms": 1, "step": "menu"})

    async def update_passport_data(self, telegram_id: int, fio: str = "", snumber: str = "", inn: str = ""):
        data = {}
        if fio:
            data["passport_fio"] = fio
        if snumber:
            data["passport_snumber"] = snumber
        if inn:
            data["passport_inn"] = inn
        if data:
            await self._fetch("users", f"telegram_id=eq.{telegram_id}", "PATCH", data)

    async def update_user_balance(self, telegram_id: int, balance: int):
        await self._fetch("users", f"telegram_id=eq.{telegram_id}", "PATCH", {"balance": balance})

    async def add_tree_xp(self, telegram_id: int, xp: int):
        user = await self.get_user(telegram_id)
        if not user:
            return
        new_xp = (user.get("tree_xp") or 0) + xp
        current_level = user.get("tree_level") or 1
        new_level = self._calc_level(new_xp, current_level)
        await self._fetch("users", f"telegram_id=eq.{telegram_id}", "PATCH", {
            "tree_xp": new_xp,
            "tree_level": new_level,
        })

    def _calc_level(self, current_xp: int, current_level: int) -> int:
        new_level = 1
        if current_xp >= 5000:
            new_level = 6
        elif current_xp >= 2000:
            new_level = 5
        elif current_xp >= 1000:
            new_level = 4
        elif current_xp >= 500:
            new_level = 3
        elif current_xp >= 100:
            new_level = 2
        return new_level

    async def get_tree_state(self, telegram_id: int) -> dict:
        user = await self._fetch_one("users", f"telegram_id=eq.{telegram_id}&select=tree_xp,tree_level")
        if not user:
            return {"xp": 0, "level": 1, "next_level_xp": 100, "progress": 0}
        current_xp = user.get("tree_xp") or 0
        level = user.get("tree_level") or 1
        thresholds = {1: 100, 2: 500, 3: 1000, 4: 2000, 5: 5000, 6: 999999}
        next_xp = thresholds.get(level, 100)
        if level >= 6:
            next_xp = thresholds[5]
            progress = 100
        else:
            prev = thresholds.get(level - 1, 0) if level > 1 else 0
            progress = min(100, int((current_xp - prev) / (next_xp - prev) * 100)) if next_xp > prev else 100
        return {"xp": current_xp, "level": level, "next_level_xp": next_xp, "progress": progress}

    async def get_all_users(self) -> list[dict]:
        return await self._fetch("users", "select=*&order=id.desc")

    async def get_user_stats(self, telegram_id: int) -> dict:
        user = await self._fetch_one("users", f"telegram_id=eq.{telegram_id}&select=balance,id")
        if not user:
            return {"balance": 0, "total_scans": 0}
        bottles = await self._fetch("bottles", f"assigned_to=eq.{user['id']}&select=id", method="GET")
        return {"balance": user.get("balance") or 0, "total_scans": len(bottles)}

    async def search_users(self, query: str) -> list[dict]:
        filters = [f"name.ilike.*{query}*", f"phone.ilike.*{query}*"]
        if query.isdigit():
            filters.append(f"telegram_id.eq.{query}")
        rows = await self._fetch("users", f"select=*&or=({','.join(filters)})&order=id.desc")
        return rows

    # ─── QR Codes ───────────────────────────────────────

    async def get_all_codes(self) -> list[dict]:
        return await self._fetch("qr_codes", "select=*&order=id.desc")

    async def get_active_codes_count(self) -> int:
        rows = await self._fetch("qr_codes", "select=id&status=eq.active")
        return len(rows)

    async def register_code_batch(self, codes: list[str], batch: str) -> int:
        count = 0
        for code in codes:
            try:
                await self._fetch("qr_codes", method="POST", json_data={"code": code, "batch": batch})
                count += 1
            except Exception:
                pass
        return count

    async def get_code_by_value(self, code: str) -> dict | None:
        return await self._fetch_one("qr_codes", f"code=eq.{code}&select=*")

    async def mark_code_won(self, code_id: int, winner_user_id: int):
        await self._fetch("qr_codes", f"id=eq.{code_id}", "PATCH", {
            "status": "won",
            "winner_id": winner_user_id,
        })

    async def get_code_stats(self) -> dict:
        all_rows = await self._fetch("qr_codes", "select=status")
        total = len(all_rows)
        active = sum(1 for r in all_rows if r.get("status") == "active")
        won = sum(1 for r in all_rows if r.get("status") == "won")
        used = sum(1 for r in all_rows if r.get("status") == "used")
        return {"total": total, "active": active, "won": won, "used": used}

    # ─── Scans ──────────────────────────────────────────

    async def add_scan(self, user_id: int, code_id: int) -> bool:
        try:
            await self._fetch("scans", method="POST", json_data={"user_id": user_id, "code_id": code_id})
            return True
        except Exception:
            return False

    async def get_user_scans(self, user_id: int) -> list[dict]:
        return await self._fetch("scans", f"select=*&user_id=eq.{user_id}&order=scanned_at.desc")

    async def get_scans(self, telegram_id: int) -> list[dict]:
        user = await self._fetch_one("users", f"telegram_id=eq.{telegram_id}&select=id")
        if not user:
            return []
        bottles = await self._fetch("bottles", f"assigned_to=eq.{user['id']}&select=bottle_id,batch,assigned_at&order=assigned_at.desc")
        return [{"code": b["bottle_id"], "batch": b.get("batch"), "scanned_at": b.get("assigned_at")} for b in bottles]

    async def count_user_scans(self, user_id: int) -> int:
        rows = await self._fetch("scans", f"select=id&user_id=eq.{user_id}")
        return len(rows)

    # ─── Points ─────────────────────────────────────────

    async def add_balance(self, telegram_id: int, amount: int, typ: str = "admin", description: str = ""):
        user = await self.get_user(telegram_id)
        if not user:
            return
        new_balance = (user.get("balance") or 0) + amount
        await self._fetch("users", f"telegram_id=eq.{telegram_id}", "PATCH", {"balance": new_balance})
        await self._fetch("points_log", method="POST", json_data={
            "user_id": user["id"],
            "amount": amount,
            "type": typ,
            "description": description,
        })

    async def get_points_log(self, telegram_id: int) -> list[dict]:
        user = await self._fetch_one("users", f"telegram_id=eq.{telegram_id}&select=id")
        if not user:
            return []
        return await self._fetch("points_log", f"select=*&user_id=eq.{user['id']}&order=created_at.desc&limit=50")

    # ─── Notifications ──────────────────────────────────

    async def get_notifications(self, telegram_id: int) -> list[dict]:
        user = await self._fetch_one("users", f"telegram_id=eq.{telegram_id}&select=id")
        if not user:
            return []
        return await self._fetch("notifications", f"select=*&user_id=eq.{user['id']}&read=eq.0&order=created_at.desc&limit=20")

    async def create_notification(self, telegram_id: int, type_: str, title: str, body: str = "", link: str = ""):
        user = await self._fetch_one("users", f"telegram_id=eq.{telegram_id}&select=id")
        if not user:
            return
        await self._fetch("notifications", method="POST", json_data={
            "user_id": user["id"],
            "type": type_,
            "title": title,
            "body": body,
            "link": link,
        })

    async def clear_notifications(self, telegram_id: int):
        user = await self._fetch_one("users", f"telegram_id=eq.{telegram_id}&select=id")
        if not user:
            return
        await self._fetch("notifications", f"user_id=eq.{user['id']}&read=eq.0", "PATCH", {"read": 1})

    # ─── Prizes ─────────────────────────────────────────

    async def get_prizes(self) -> list[dict]:
        return await self._fetch("prizes", "select=*&active=eq.1&order=price_points.asc")

    async def get_prizes_by_category(self, category_id: int) -> list[dict]:
        try:
            return await self._fetch("prizes", f"select=*&category_id=eq.{category_id}&active=eq.1&order=price_points.asc")
        except Exception:
            return []

    async def get_prize(self, prize_id: int) -> dict | None:
        return await self._fetch_one("prizes", f"id=eq.{prize_id}&select=*")

    async def add_prize(self, name: str, description: str, image_url: str, price_points: int, category_id: int = 0):
        data = {
            "name": name,
            "description": description,
            "image_url": image_url,
            "price_points": price_points,
        }
        if category_id:
            data["category_id"] = category_id
        await self._fetch("prizes", method="POST", json_data=data)

    async def delete_prize(self, prize_id: int):
        await self._fetch("prizes", f"id=eq.{prize_id}", "DELETE")

    # ─── Shop Categories ─────────────────────────────

    async def get_shop_categories(self) -> list[dict]:
        try:
            return await self._fetch("shop_categories", "select=*&order=sort_order.asc")
        except Exception:
            return []

    async def get_shop_category(self, category_id: int) -> dict | None:
        try:
            return await self._fetch_one("shop_categories", f"id=eq.{category_id}&select=*")
        except Exception:
            return None

    async def get_shop_category_by_title(self, title: str) -> dict | None:
        try:
            return await self._fetch_one("shop_categories", f"title=eq.{title}&select=*")
        except Exception:
            return None

    async def update_shop_category(self, category_id: int, data: dict):
        try:
            await self._fetch("shop_categories", f"id=eq.{category_id}", "PATCH", data)
        except Exception:
            pass

    async def get_all_prizes(self) -> list[dict]:
        try:
            return await self._fetch("prizes", "select=*&order=price_points.asc")
        except Exception:
            return []

    # ─── Orders ─────────────────────────────────────────

    async def create_order(self, user_id: int, prize_id: int) -> int:
        rows = await self._fetch("orders", method="POST", json_data={"user_id": user_id, "prize_id": prize_id})
        return rows[0]["id"] if rows else 0

    async def get_pending_orders(self) -> list[dict]:
        return await self._fetch("orders", "select=*&status=eq.pending&order=created_at.desc")

    async def complete_order(self, order_id: int):
        await self._fetch("orders", f"id=eq.{order_id}", "PATCH", {"status": "completed"})

    # ─── Raffles ────────────────────────────────────────

    async def get_raffles(self) -> list[dict]:
        return await self._fetch("raffles", "select=*&order=created_at.desc")

    async def get_raffle_results(self) -> list[dict]:
        return await self._fetch("raffles", "select=*&status=eq.completed&order=created_at.desc")

    async def get_raffle_stats(self) -> dict:
        all_rows = await self._fetch("raffles", "select=status")
        total = len(all_rows)
        completed = sum(1 for r in all_rows if r.get("status") == "completed")
        return {"total_raffles": total, "completed": completed}

    async def run_raffle(self, prize_amount: int) -> dict | None:
        active_codes = await self._fetch("qr_codes", "select=*&status=eq.active&limit=100")
        if not active_codes:
            return None
        import random
        code = random.choice(active_codes)
        scans = await self._fetch("scans", f"code_id=eq.{code['id']}&order=scanned_at.desc&limit=1")
        if not scans:
            return None
        scan = scans[0]
        winner = await self.get_user_by_id(scan["user_id"])
        if not winner:
            return None
        await self._fetch("qr_codes", f"id=eq.{code['id']}", "PATCH", {
            "status": "won",
            "winner_id": scan["user_id"],
        })
        deadline = (datetime.utcnow() + timedelta(days=7)).isoformat()
        raffles = await self._fetch("raffles", method="POST", json_data={
            "scheduled_at": datetime.utcnow().isoformat(),
            "winner_scan_id": scan["id"],
            "prize_amount": prize_amount,
            "status": "completed",
            "payout_deadline": deadline,
            "winner_name": winner.get("name", ""),
            "winning_code": code["code"],
        })
        raffle = raffles[0] if raffles else {}
        return {
            "raffle": raffle,
            "winner": {
                "telegram_id": winner["telegram_id"],
                "name": winner.get("name", ""),
                "code": code["code"],
            },
            "raffle_id": raffle.get("id"),
        }

    async def mark_payout_paid(self, raffle_id: int):
        await self._fetch("raffles", f"id=eq.{raffle_id}", "PATCH", {
            "payout_status": "paid",
            "payout_choice": "money",
        })

    async def set_payout_choice(self, raffle_id: int, choice: str):
        if choice == "points":
            raffles = await self._fetch("raffles", f"id=eq.{raffle_id}&select=*")
            if not raffles:
                return
            raffle = raffles[0]
            scans = await self._fetch("scans", f"id=eq.{raffle['winner_scan_id']}&select=*")
            if not scans:
                return
            scan = scans[0]
            users = await self._fetch("users", f"id=eq.{scan['user_id']}&select=*")
            if not users:
                return
            user = users[0]
            points = raffle["prize_amount"] * 10
            new_balance = (user.get("balance") or 0) + points
            await self._fetch("users", f"id=eq.{user['id']}", "PATCH", {"balance": new_balance})
            await self._fetch("points_log", method="POST", json_data={
                "user_id": user["id"],
                "amount": points,
                "type": "conversion",
                "description": "Конвертация выигрыша в баллы",
            })
            await self.create_notification(user["telegram_id"], "points", "Конвертация выигрыша", f"+{points} баллов за конвертацию выигрыша", "history")
            await self._fetch("raffles", f"id=eq.{raffle_id}", "PATCH", {
                "payout_status": "converted",
                "payout_choice": "points",
            })
        else:
            await self._fetch("raffles", f"id=eq.{raffle_id}", "PATCH", {"payout_choice": choice})

    async def get_pending_payouts(self) -> list[dict]:
        return await self._fetch("raffles", "select=*&status=eq.completed&payout_choice=eq.money&payout_status=is.null&order=created_at.desc")

    async def get_user_raffle_wins(self, telegram_id: int) -> list[dict]:
        return await self._fetch("raffles", f"select=*&status=eq.completed&order=created_at.desc")

    async def process_expired_payouts(self):
        all_payouts = await self._fetch("raffles", "select=*&status=eq.completed&payout_choice=eq.money&payout_status=is.null")
        now = datetime.utcnow()
        expired = []
        for r in all_payouts:
            deadline = r.get("payout_deadline")
            if deadline:
                if isinstance(deadline, str):
                    deadline = datetime.fromisoformat(deadline.replace("Z", "+00:00")).replace(tzinfo=None)
                if deadline < now:
                    expired.append(r)
        count = 0
        for raffle in expired:
            scan = raffle.get("scans") or {}
            if not isinstance(scan, dict) or not scan.get("user_id"):
                scan_rows = await self._fetch("scans", f"id=eq.{raffle.get('winner_scan_id')}&select=user_id")
                scan = scan_rows[0] if scan_rows else {}
            user_id = scan.get("user_id")
            if not user_id:
                continue
            points = raffle["prize_amount"] * 5
            users = await self._fetch("users", f"id=eq.{user_id}&select=balance")
            if users:
                new_balance = (users[0].get("balance") or 0) + points
                await self._fetch("users", f"id=eq.{user_id}", "PATCH", {"balance": new_balance})
            await self._fetch("points_log", method="POST", json_data={
                "user_id": user_id,
                "amount": points,
                "type": "conversion",
                "description": "Автоконвертация просроченной выплаты",
            })
            await self._fetch("raffles", f"id=eq.{raffle['id']}", "PATCH", {
                "payout_status": "converted",
                "payout_choice": "points",
            })
            count += 1
        return count

    # ─── Gift System ───────────────────────────────────

    async def has_gift_been_opened(self, telegram_id: int) -> bool:
        try:
            user = await self._fetch_one("users", f"telegram_id=eq.{telegram_id}&select=gift_opened")
            if not user:
                return False
            return bool(user.get("gift_opened"))
        except Exception:
            return False

    async def mark_gift_opened(self, telegram_id: int, points: int):
        user = await self.get_user(telegram_id)
        if not user:
            return
        new_balance = (user.get("balance") or 0) + points
        try:
            await self._fetch("users", f"telegram_id=eq.{telegram_id}", "PATCH", {
                "gift_opened": True,
                "gift_points": points,
                "balance": new_balance,
            })
        except Exception:
            await self._fetch("users", f"telegram_id=eq.{telegram_id}", "PATCH", {
                "balance": new_balance,
            })
        await self._fetch("points_log", method="POST", json_data={
            "user_id": user["id"],
            "amount": points,
            "type": "gift",
            "description": f"Моментальный подарок: {points} баллов",
        })

    async def get_nearest_prize(self, telegram_id: int) -> dict | None:
        user = await self.get_user(telegram_id)
        if not user:
            return None
        balance = user.get("balance") or 0
        prizes = await self.get_prizes()
        if not prizes:
            return None
        nearest = None
        for prize in prizes:
            if prize["price_points"] > balance:
                if nearest is None or prize["price_points"] < nearest["price_points"]:
                    nearest = prize
        if not nearest:
            nearest = prizes[-1] if prizes else None
        if nearest:
            missing = max(0, nearest["price_points"] - balance)
            return {
                "name": nearest["name"],
                "price": nearest["price_points"],
                "missing": missing,
                "image_url": nearest.get("image_url", ""),
            }
        return None

    async def activate_qr_code(self, telegram_id: int, qr_code: str) -> bool:
        user = await self.get_user(telegram_id)
        if not user:
            return False
        existing = await self._fetch_one("user_qr_activations",
            f"user_id=eq.{user['id']}&qr_code=eq.{qr_code}")
        if existing:
            return False
        try:
            await self._fetch("user_qr_activations", method="POST", json_data={
                "user_id": user["id"],
                "qr_code": qr_code,
            })
            return True
        except Exception:
            return False

    async def is_qr_code_activated_by_anyone(self, qr_code: str) -> dict | None:
        return await self._fetch_one("user_qr_activations",
            f"qr_code=eq.{qr_code}&select=*")

    async def get_user_activation_count(self, telegram_id: int) -> int:
        user = await self.get_user(telegram_id)
        if not user:
            return 0
        rows = await self._fetch("user_qr_activations",
            f"user_id=eq.{user['id']}&select=id")
        return len(rows)

    # ─── Bottles ────────────────────────────────────────

    async def create_bottles_batch(self, count: int, batch: str, year: str) -> list[str]:
        import random
        import string

        alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789"
        existing = await self._fetch("bottles", f"year=eq.{year}&batch=eq.{batch}&select=bottle_id&order=id.desc&limit=1")
        seq_start = 1
        if existing:
            last = existing[0]["bottle_id"]
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
                check = await self._fetch("bottles", f"bottle_id=eq.{bottle_id}&select=id&limit=1")
                if not check:
                    break
                rand = "".join(random.choices(alphabet, k=4))
                bottle_id = f"BTL-{year}-{batch}-{seq:04d}-{rand}"
            await self._fetch("bottles", method="POST", json_data={
                "bottle_id": bottle_id,
                "batch": batch,
                "year": year,
            })
            ids.append(bottle_id)
        return ids

    async def get_bottles(self, batch: str = "", year: str = "", sort: str = "id", direction: str = "DESC",
                          limit: int = 200, offset: int = 0) -> list[dict]:
        allowed_sort = {"id", "bottle_id", "year", "batch", "assigned_to", "created_at"}
        sort_col = sort if sort in allowed_sort else "id"
        dir_ = ".desc" if direction.upper() == "DESC" else ".asc"
        params = f"select=*&order={sort_col}{dir_}&limit={limit}&offset={offset}"
        if year:
            params += f"&year=eq.{year}"
        if batch:
            params += f"&batch=eq.{batch}"
        return await self._fetch("bottles", params)

    async def get_bottle_by_code(self, bottle_id: str) -> dict | None:
        return await self._fetch_one("bottles", f"bottle_id=eq.{bottle_id}&select=*")

    async def search_bottles(self, query: str, sort: str = "id", direction: str = "DESC",
                             limit: int = 200, offset: int = 0) -> list[dict]:
        allowed_sort = {"id", "bottle_id", "year", "batch", "assigned_to", "created_at"}
        sort_col = sort if sort in allowed_sort else "id"
        dir_ = ".desc" if direction.upper() == "DESC" else ".asc"
        return await self._fetch("bottles", f"select=*&or=(bottle_id.ilike.*{query}*,batch.ilike.*{query}*,year.ilike.*{query}*)&order={sort_col}{dir_}&limit={limit}&offset={offset}")

    async def delete_bottle(self, bottle_id: str) -> bool:
        return await self._delete_rows("bottles", f"bottle_id=eq.{bottle_id}") > 0

    async def delete_batch(self, year: str, batch: str) -> int:
        return await self._delete_rows("bottles", f"year=eq.{year}&batch=eq.{batch}")

    async def get_bottle_batches(self) -> list[dict]:
        all_bottles = await self._fetch("bottles", "select=year,batch")
        batches = {}
        for b in all_bottles:
            key = (b.get("year", ""), b.get("batch", ""))
            batches[key] = batches.get(key, 0) + 1
        return [{"year": k[0], "batch": k[1], "count": v} for k, v in sorted(batches.items(), reverse=True)]

    async def count_bottles(self, batch: str = "", year: str = "", search: str = "") -> int:
        params = "select=id"
        if year:
            params += f"&year=eq.{year}"
        if batch:
            params += f"&batch=eq.{batch}"
        if search:
            params += f"&or=(bottle_id.ilike.*{search}*,batch.ilike.*{search}*,year.ilike.*{search}*)"
        rows = await self._fetch("bottles", params)
        return len(rows)

    async def assign_bottle(self, bottle_id: str, user_id: int):
        await self._fetch("bottles", f"bottle_id=eq.{bottle_id}", "PATCH", {
            "assigned_to": user_id,
        })

    async def get_unassigned_bottle_count(self) -> int:
        rows = await self._fetch("bottles", "select=id&assigned_to=is.null")
        return len(rows)

    async def get_assigned_bottle_count(self) -> int:
        rows = await self._fetch("bottles", "select=id&assigned_to=not.is.null")
        return len(rows)

    # ─── Admins ─────────────────────────────────────────

    async def get_admins(self) -> list[dict]:
        return await self._fetch("admins", "select=*&order=created_at.desc")

    async def is_admin(self, telegram_id: int) -> bool:
        from .config import get_settings
        s = get_settings()
        if telegram_id == s["SUPERADMIN_ID"]:
            return True
        if telegram_id in s["ADMIN_IDS"]:
            return True
        rows = await self._fetch("admins", f"select=id&telegram_id=eq.{telegram_id}")
        return len(rows) > 0

    async def add_admin(self, telegram_id: int, name: str = "", added_by: int = 0) -> bool:
        try:
            await self._fetch("admins", method="POST", json_data={
                "telegram_id": telegram_id,
                "name": name,
                "added_by": added_by,
            })
            return True
        except Exception:
            return False

    async def remove_admin(self, telegram_id: int) -> bool:
        return await self._delete_rows("admins", f"telegram_id=eq.{telegram_id}") > 0

    # ─── Admin Codes ────────────────────────────────────

    async def create_access_code(self, telegram_id: int) -> str:
        import random
        code = str(random.randint(100000, 999999))
        expires = (datetime.utcnow() + timedelta(minutes=5)).isoformat()
        await self._fetch("admin_codes", method="POST", json_data={
            "code": code,
            "telegram_id": telegram_id,
            "expires_at": expires,
        })
        return code

    async def validate_access_code(self, code: str, telegram_id: int) -> bool:
        now = datetime.utcnow().isoformat()
        rows = await self._fetch("admin_codes", f"code=eq.{code}&telegram_id=eq.{telegram_id}&used=eq.0&expires_at=gt.{now}&select=id")
        if rows:
            await self._fetch("admin_codes", f"id=eq.{rows[0]['id']}", "PATCH", {"used": 1})
            return True
        return False

    # ─── Settings ────────────────────────────────────────

    async def get_setting(self, key: str) -> str | None:
        row = await self._fetch_one("settings", f"key=eq.{key}&select=value")
        return row["value"] if row else None

    async def set_setting(self, key: str, value: str):
        existing = await self._fetch_one("settings", f"key=eq.{key}&select=key")
        if existing:
            await self._fetch("settings", f"key=eq.{key}", "PATCH", {"value": value, "updated_at": datetime.utcnow().isoformat()})
        else:
            await self._fetch("settings", method="POST", json_data={"key": key, "value": value})
