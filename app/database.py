import httpx
import logging
import json
from datetime import datetime, timedelta

logger = logging.getLogger(__name__)

SUPABASE_REST = "/rest/v1"


class Database:
    def __init__(self):
        self.client = None
        self.url = ""
        self.key = ""

    async def connect(self):
        from .config import get_settings
        s = get_settings()
        self.url = s["SUPABASE_URL"]
        self.key = s["SUPABASE_KEY"]
        if not self.url or not self.key:
            raise RuntimeError("SUPABASE_URL and SUPABASE_KEY must be set")
        self.client = httpx.AsyncClient(
            base_url=self.url,
            headers={
                "apikey": self.key,
                "Authorization": f"Bearer {self.key}",
                "Content-Type": "application/json",
                "Prefer": "return=representation",
            },
            timeout=30,
        )
        logger.info("Supabase REST connected")

    async def close(self):
        if self.client:
            await self.client.aclose()

    async def _fetch(self, table: str, params: str = "", method: str = "GET", json_data=None) -> list[dict]:
        path = f"{SUPABASE_REST}/{table}"
        if params:
            path += f"?{params}"
        if method == "GET":
            r = await self.client.get(path)
        elif method == "POST":
            r = await self.client.post(path, json=json_data)
        elif method == "PATCH":
            r = await self.client.patch(path, json=json_data)
        elif method == "DELETE":
            r = await self.client.delete(path)
        else:
            return []
        r.raise_for_status()
        data = r.json()
        if isinstance(data, list):
            return data
        return [data] if data else []

    async def _fetch_one(self, table: str, params: str = "") -> dict | None:
        rows = await self._fetch(table, params)
        return rows[0] if rows else None

    async def _rpc(self, fn: str, params: dict = None) -> any:
        path = f"{SUPABASE_REST}/rpc/{fn}"
        r = await self.client.post(path, json=params or {})
        r.raise_for_status()
        return r.json()

    # ─── Migrate (via RPC) ───────────────────────────────
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
        rows = await self._fetch("users", f"select=*&or=(name.ilike.*{query}*,phone.ilike.*{query}*,telegram_id::text.ilike.*{query}*)&order=id.desc")
        return rows

    # ─── QR Codes ───────────────────────────────────────

    async def get_all_codes(self) -> list[dict]:
        return await self._fetch("qr_codes", "select=*,users:winner_id(name)&order=id.desc")

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
        return await self._fetch("scans", f"select=*,qr_codes:code_id(code,batch)&user_id=eq.{user_id}&order=scanned_at.desc")

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
        return await self._fetch("prizes", f"select=*&category_id=eq.{category_id}&active=eq.1&order=price_points.asc")

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
        return await self._fetch("shop_categories", "select=*&order=sort_order.asc")

    async def get_shop_category(self, category_id: int) -> dict | None:
        return await self._fetch_one("shop_categories", f"id=eq.{category_id}&select=*")

    async def get_shop_category_by_title(self, title: str) -> dict | None:
        return await self._fetch_one("shop_categories", f"title=eq.{title}&select=*")

    async def update_shop_category(self, category_id: int, data: dict):
        await self._fetch("shop_categories", f"id=eq.{category_id}", "PATCH", data)

    async def get_all_prizes(self) -> list[dict]:
        return await self._fetch("prizes", "select=*&order=price_points.asc")

    # ─── Orders ─────────────────────────────────────────

    async def create_order(self, user_id: int, prize_id: int) -> int:
        rows = await self._fetch("orders", method="POST", json_data={"user_id": user_id, "prize_id": prize_id})
        return rows[0]["id"] if rows else 0

    async def get_pending_orders(self) -> list[dict]:
        return await self._fetch("orders", "select=*,users:user_id(name,telegram_id,phone),prizes:prize_id(name)&status=eq.pending&order=created_at.desc")

    async def complete_order(self, order_id: int):
        await self._fetch("orders", f"id=eq.{order_id}", "PATCH", {"status": "completed"})

    # ─── Raffles ────────────────────────────────────────

    async def get_raffles(self) -> list[dict]:
        return await self._fetch("raffles", "select=*,users!raffles_winner_scan_id_fkey(name,telegram_id),scans:winner_scan_id(qr_codes(code))&order=created_at.desc")

    async def get_raffle_results(self) -> list[dict]:
        return await self._fetch("raffles", "select=*,users!raffles_winner_scan_id_fkey(name),scans:winner_scan_id(qr_codes(code))&status=eq.completed&order=created_at.desc")

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
            await self._fetch("raffles", f"id=eq.{raffle_id}", "PATCH", {
                "payout_status": "converted",
                "payout_choice": "points",
            })
        else:
            await self._fetch("raffles", f"id=eq.{raffle_id}", "PATCH", {"payout_choice": choice})

    async def get_pending_payouts(self) -> list[dict]:
        return await self._fetch("raffles", "select=*,scans:winner_scan_id(users(name,telegram_id,phone,passport_fio,passport_snumber,passport_inn))&status=eq.completed&payout_choice=eq.money&payout_status=is.null&order=created_at.desc")

    async def get_user_raffle_wins(self, telegram_id: int) -> list[dict]:
        return await self._fetch("raffles", f"select=*,scans:winner_scan_id(qr_codes(code),users(telegram_id))&status=eq.completed&scans.users.telegram_id=eq.{telegram_id}&order=created_at.desc")

    async def process_expired_payouts(self):
        all_payouts = await self._fetch("raffles", "select=*,scans:winner_scan_id(user_id)&status=eq.completed&payout_choice=eq.money&payout_status=is.null")
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
            scan = raffle.get("scans", {})
            user_id = scan.get("user_id") if isinstance(scan, dict) else None
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
            f"qr_code=eq.{qr_code}&select=*,users:user_id(telegram_id,name)")

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

    async def get_bottles(self, batch: str = "", sort: str = "id", direction: str = "DESC",
                          limit: int = 200, offset: int = 0) -> list[dict]:
        allowed_sort = {"id", "bottle_id", "year", "batch", "assigned_to", "created_at"}
        sort_col = sort if sort in allowed_sort else "id"
        dir_ = ".desc" if direction.upper() == "DESC" else ".asc"
        params = f"select=*&order={sort_col}{dir_}&limit={limit}&offset={offset}"
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
        r = await self.client.delete(f"{SUPABASE_REST}/bottles?bottle_id=eq.{bottle_id}")
        return r.status_code == 200

    async def delete_batch(self, year: str, batch: str) -> int:
        r = await self.client.delete(f"{SUPABASE_REST}/bottles?year=eq.{year}&batch=eq.{batch}")
        return 0

    async def get_bottle_batches(self) -> list[dict]:
        all_bottles = await self._fetch("bottles", "select=year,batch")
        batches = {}
        for b in all_bottles:
            key = (b.get("year", ""), b.get("batch", ""))
            batches[key] = batches.get(key, 0) + 1
        return [{"year": k[0], "batch": k[1], "count": v} for k, v in sorted(batches.items(), reverse=True)]

    async def count_bottles(self, batch: str = "", search: str = "") -> int:
        params = "select=id"
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
        r = await self.client.delete(f"{SUPABASE_REST}/admins?telegram_id=eq.{telegram_id}")
        return r.status_code == 200

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
