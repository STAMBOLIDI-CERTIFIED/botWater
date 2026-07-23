import json
import logging
import io
import zipfile
import base64
import html as html_mod
import hmac
import hashlib
import secrets
import time
from urllib.parse import quote, parse_qs

from pathlib import Path
from datetime import datetime

from fastapi import FastAPI, Request, Form, HTTPException
from fastapi.responses import HTMLResponse, JSONResponse, Response, RedirectResponse
from fastapi.staticfiles import StaticFiles
from starlette.middleware.base import BaseHTTPMiddleware
from jinja2 import Environment, FileSystemLoader, select_autoescape

class SafeJinja2Templates:
    def __init__(self, directory: str):
        self.env = Environment(
            loader=FileSystemLoader(directory),
            autoescape=select_autoescape(["html", "xml"]),
            cache_size=0,
        )
    def TemplateResponse(self, name: str, context: dict):
        from starlette.templating import _TemplateResponse
        template = self.env.get_template(name)
        return _TemplateResponse(template, context)

from .config import get_settings
from .database import Database
from . import bot

logging.basicConfig(level=logging.INFO, format='%(asctime)s %(name)s %(levelname)s %(message)s')
logger = logging.getLogger(__name__)

app = FastAPI(title="WaterPrize")

class NoCacheMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next):
        response = await call_next(request)
        if "text/html" in response.headers.get("content-type", ""):
            response.headers["Cache-Control"] = "no-cache, no-store, must-revalidate, private"
            response.headers["Pragma"] = "no-cache"
            response.headers["Expires"] = "0"
        return response

app.add_middleware(NoCacheMiddleware)

db = Database()

BASE_DIR = Path(__file__).parent.parent
templates = SafeJinja2Templates(directory=str(BASE_DIR / "app" / "templates"))

# ─── Startup / Shutdown ─────────────────────────────────

@app.on_event("startup")
async def startup():
    s = get_settings()
    if not s["BOT_TOKEN"]:
        logger.error("BOT_TOKEN is not set!")
        import sys
        sys.exit(1)
    await db.connect()
    logger.info("Database connected")
    logger.info(f"WEBAPP_URL={s['WEBAPP_URL']}")
    await _setup_bot_commands()


@app.on_event("shutdown")
async def shutdown():
    await db.close()

# ─── Bot Commands Setup ────────────────────────────────

async def _setup_bot_commands():
    import httpx
    s = get_settings()
    token = s["BOT_TOKEN"]
    api = f"https://api.telegram.org/bot{token}"
    domain = s["DOMAIN"]
    async with httpx.AsyncClient() as client:
        await client.post(f"{api}/setMyCommands", json={
            "commands": [
                {"command": "start", "description": "🚀 Главное меню"},
                {"command": "profile", "description": "👤 Мой профиль"},
                {"command": "admin", "description": "🔐 Админ-панель"},
                {"command": "terms", "description": "📄 Пользовательское соглашение"},
            ],
        })
        await client.post(f"{api}/setChatMenuButton", json={
            "menu_button": {
                "type": "web_app",
                "text": "🚀 Открыть",
                "web_app": {"url": s["WEBAPP_URL"]},
            },
        })
        webhook_url = f"{domain}/webhook"
        await client.post(f"{api}/setWebhook", json={"url": webhook_url})
        logger.info(f"Webhook set to {webhook_url}")
        logger.info("Bot commands and menu button set")

# ─── Webhook ────────────────────────────────────────────

_counter = 0

@app.post("/webhook")
async def webhook(request: Request):
    global _counter
    try:
        body = await request.json()
    except Exception:
        raise HTTPException(400, "Invalid JSON")

    if not body or "update_id" not in body:
        raise HTTPException(400, "Missing update_id")

    _counter += 1
    if _counter >= 50:
        await bot.process_expired_payouts(db)
        _counter = 0

    await bot.handle_update(db, body)
    return {"ok": True}

# ─── API ────────────────────────────────────────────────

@app.get("/api/user")
async def api_user(user_id: int = 0):
    if not user_id:
        return JSONResponse(None)
    stats = await db.get_user_stats(user_id)
    user = await db.get_user(user_id)
    return {
        "balance": stats["balance"],
        "total_scans": stats["total_scans"],
        "phone": user.get("phone") if user else None,
        "name": user.get("name") if user else None,
    }

@app.get("/api/prizes")
async def api_prizes():
    return await db.get_prizes()

@app.get("/api/shop/categories")
async def api_shop_categories():
    return await db.get_shop_categories()

@app.get("/api/shop/categories/{category_id}")
async def api_shop_category_items(category_id: int):
    category = await db.get_shop_category(category_id)
    if not category:
        return JSONResponse({"error": "not found"}, status_code=404)
    items = await db.get_prizes_by_category(category_id)
    return {"category": category, "items": items}

@app.get("/api/shop/categories/{category_id}/items")
async def api_shop_category_items_list(category_id: int):
    return await db.get_prizes_by_category(category_id)

@app.get("/api/raffles")
async def api_raffles(user_id: int = 0):
    raffles = await db.get_raffle_results()
    for r in raffles:
        r["user_won"] = bool(user_id) and r.get("telegram_id") == user_id
    return raffles

@app.get("/api/history")
async def api_history(user_id: int = 0):
    if not user_id:
        return []
    return await db.get_scans(user_id)

@app.get("/api/points_log")
async def api_points_log(user_id: int = 0):
    if not user_id:
        return []
    return await db.get_points_log(user_id)

@app.get("/api/tree")
async def api_tree(user_id: int = 0):
    if not user_id:
        return {"xp": 0, "level": 1, "next_level_xp": 100, "progress": 0}
    return await db.get_tree_state(user_id)

@app.get("/api/notifications")
async def api_notifications(user_id: int = 0):
    if not user_id:
        return []
    return await db.get_notifications(user_id)

@app.get("/api/notifications/clear")
async def api_notifications_clear(user_id: int = 0):
    if user_id:
        await db.clear_notifications(user_id)
    return {"ok": True}

@app.get("/api/gift")
async def api_gift_status(user_id: int = 0):
    if not user_id:
        return JSONResponse({"error": "missing user_id"}, status_code=400)
    try:
        opened = await db.has_gift_been_opened(user_id)
    except Exception:
        opened = False
    user = await db.get_user(user_id)
    return {
        "opened": opened,
        "gift_points": user.get("gift_points") if user else 0,
        "balance": user.get("balance") if user else 0,
    }

@app.post("/api/gift/open")
async def api_gift_open(request: Request):
    body = await request.json()
    user_id = body.get("user_id", 0)
    if not user_id:
        return JSONResponse({"error": "missing user_id"}, status_code=400)

    try:
        opened = await db.has_gift_been_opened(user_id)
    except Exception:
        opened = False
    if opened:
        return JSONResponse({"error": "gift already opened"}, status_code=409)

    import random
    points = random.choices([10, 15, 25, 50, 100], weights=[35, 30, 20, 10, 5], k=1)[0]

    try:
        await db.mark_gift_opened(user_id, points)
    except Exception as e:
        logger.error(f"mark_gift_opened failed: {e}")
        return JSONResponse({"error": "failed to save gift"}, status_code=500)

    try:
        nearest = await db.get_nearest_prize(user_id)
    except Exception:
        nearest = None

    user = await db.get_user(user_id)

    return {
        "ok": True,
        "points": points,
        "balance": user.get("balance") if user else points,
        "nearest_prize": nearest,
    }

@app.get("/api/nearest_prize")
async def api_nearest_prize(user_id: int = 0):
    if not user_id:
        return JSONResponse({"error": "missing user_id"}, status_code=400)
    nearest = await db.get_nearest_prize(user_id)
    return nearest or {}

@app.post("/api/scan")
async def api_scan(request: Request):
    body = await request.json()
    user_id = body.get("user_id", 0)
    bottle_id = body.get("bottle_id", "")
    if not user_id or not bottle_id:
        return JSONResponse({"ok": False, "error": "missing user_id or bottle_id"}, status_code=400)

    user = await db.get_user(user_id)
    if not user:
        return JSONResponse({"ok": False, "error": "user not found"}, status_code=404)

    bottle = await db.get_bottle_by_code(bottle_id)
    if not bottle:
        return JSONResponse({"ok": False, "error": "bottle not found"}, status_code=404)
    if bottle.get("assigned_to"):
        return JSONResponse({"ok": False, "error": "already scanned"}, status_code=409)

    await db.assign_bottle(bottle_id, user["id"])
    await db.add_balance(user_id, 10, "scan", f"Сканирование бутылки {bottle_id}")
    await db.add_tree_xp(user_id, 10)

    stats = await db.get_user_stats(user_id)
    tree = await db.get_tree_state(user_id)
    return {"ok": True, "balance": stats["balance"], "total_scans": stats["total_scans"], "xp": tree["xp"], "level": tree["level"]}

# ─── Admin Auth ────────────────────────────────────────

_ADMIN_TOKENS: dict[str, dict] = {}


def _sign_admin_token(tg_id: int) -> str:
    tok = secrets.token_urlsafe(24)
    _ADMIN_TOKENS[tok] = {"tg_id": tg_id, "expires": time.time() + 3600}
    return tok


def get_admin_session(request: Request) -> int | None:
    token = request.cookies.get("admin_token")
    if token:
        try:
            data = json.loads(token)
            return data.get("tg_id")
        except (json.JSONDecodeError, TypeError):
            pass
    tok = request.query_params.get("token")
    if tok and tok in _ADMIN_TOKENS:
        entry = _ADMIN_TOKENS[tok]
        if entry["expires"] > time.time():
            return entry["tg_id"]
    return None


def set_admin_cookie(tg_id: int) -> Response:
    data = json.dumps({"tg_id": tg_id})
    token = _sign_admin_token(tg_id)
    resp = RedirectResponse(url=f"/admin?page=dashboard&token={token}", status_code=302)
    resp.set_cookie(key="admin_token", value=data, max_age=86400 * 30, httponly=True, samesite="lax")
    return resp


async def require_admin(request: Request):
    tg_id = get_admin_session(request)
    if tg_id and await db.is_admin(tg_id):
        return tg_id
    return None

def admin_context(request: Request, page: str, **extra) -> dict:
    return {"request": request, "page": page, **extra}

# ─── Admin Login / Logout ──────────────────────────────

@app.post("/admin/auto-login")
async def admin_auto_login(request: Request):
    try:
        body = await request.json()
        tg_id = body.get("telegram_id")
    except Exception:
        raise HTTPException(400, "Invalid JSON")
    if tg_id and await db.is_admin(tg_id):
        token = _sign_admin_token(tg_id)
        return {"token": token, "tg_id": tg_id}
    raise HTTPException(403, "Access denied")

@app.get("/admin/login", response_class=HTMLResponse)
async def admin_login_page(request: Request):
    return templates.TemplateResponse("login.html", {"request": request, "error": ""})

@app.post("/admin/login")
async def admin_login_action(request: Request, telegram_id: int = Form(...)):
    if await db.is_admin(telegram_id):
        return set_admin_cookie(telegram_id)
    return templates.TemplateResponse("login.html", {"request": request, "error": "Доступ запрещён"})

@app.get("/admin/logout")
async def admin_logout():
    resp = RedirectResponse(url="/admin/login")
    resp.delete_cookie("admin_token")
    return resp

# ─── Admin Panel ────────────────────────────────────────

@app.api_route("/admin", methods=["GET", "POST"], response_class=HTMLResponse)
async def admin_panel(request: Request, page: str = "dashboard"):
    admin_id = await require_admin(request)
    if not admin_id:
        return RedirectResponse(url="/admin/login")

    s = get_settings()
    success_msg = request.query_params.get("msg", "")

    if request.method == "POST":
        form = await request.form()
        result = await _handle_admin_post(page, form, admin_id)
        if result:
            return result

    ctx = admin_context(request, page, success_msg=success_msg)

    if page == "dashboard":
        ctx["stats"] = await _dashboard_stats()
    elif page == "users":
        search = request.query_params.get("search", "")
        if search:
            ctx["users"] = await db.search_users(search)
            ctx["search"] = search
        else:
            ctx["users"] = await db.get_all_users()
    elif page == "codes":
        ctx["codes"] = await db.get_all_codes()
        ctx["active_count"] = await db.get_active_codes_count()
        ctx["code_stats"] = await db.get_code_stats()
    elif page == "raffles":
        ctx["raffles"] = await db.get_raffles()
        ctx["active_codes"] = await db.get_active_codes_count()
    elif page == "payouts":
        ctx["payouts"] = await db.get_pending_payouts()
        ctx["all_payouts"] = await db.get_raffles()
    elif page == "points":
        ctx["users"] = await db.get_all_users()
    elif page == "prizes":
        ctx["prizes"] = await db.get_all_prizes()
        ctx["categories"] = await db.get_shop_categories()
    elif page == "shop":
        ctx["categories"] = await db.get_shop_categories()
    elif page == "orders":
        ctx["orders"] = await db.get_pending_orders()
    elif page == "bottles":
        batch = request.query_params.get("batch", "")
        search = request.query_params.get("search", "")
        sort = request.query_params.get("sort", "id")
        dir_ = request.query_params.get("dir", "DESC")
        limit = int(request.query_params.get("limit", 200))
        offset = int(request.query_params.get("offset", 0))
        if search:
            ctx["bottles"] = await db.search_bottles(search, sort, dir_, limit, offset)
            ctx["total"] = await db.count_bottles(search=search)
        else:
            ctx["bottles"] = await db.get_bottles(batch, sort, dir_, limit, offset)
            ctx["total"] = await db.count_bottles(batch=batch)
        ctx["batches"] = await db.get_bottle_batches()
    elif page == "admins":
        ctx["admins"] = await db.get_admins()
        ctx["superadmin_id"] = s["SUPERADMIN_ID"]

    template = f"{page}.html"
    template_path = Path(__file__).parent / "templates" / template
    if not template_path.exists():
        template = "dashboard.html"
    return templates.TemplateResponse(template, ctx)

async def _dashboard_stats() -> dict:
    users = len(await db.get_all_users())
    codes = await db.get_active_codes_count()
    raffle_stats = await db.get_raffle_stats()
    unassigned = await db.get_unassigned_bottle_count()
    assigned = await db.get_assigned_bottle_count()
    return {
        "total_users": users,
        "active_codes": codes,
        "total_raffles": raffle_stats["total_raffles"],
        "completed_raffles": raffle_stats["completed"],
        "unassigned_bottles": unassigned,
        "assigned_bottles": assigned,
    }

async def _handle_admin_post(page: str, form, admin_id: int) -> RedirectResponse | None:
    s = get_settings()
    r = lambda m: RedirectResponse(url=f"/admin?page={page}&msg={quote(str(m))}", status_code=302)

    if page == "users":
        if "add_balance" in form:
            tg_id = int(form["telegram_id"])
            amount = int(form["amount"])
            reason = form.get("reason", "")
            await db.add_balance(tg_id, amount, "admin", reason or "Начисление администратором")
            user = await db.get_user(tg_id)
            if user:
                await bot.send_message(tg_id, f"💰 Вам начислено <b>{amount} баллов</b>\nПричина: {reason or '—'}")
            return r(f"Начислено {amount} баллов")
        elif "deduct_balance" in form:
            tg_id = int(form["telegram_id"])
            amount = int(form["amount"])
            reason = form.get("reason", "")
            await db.add_balance(tg_id, -amount, "admin_deduct", reason or "Списание администратором")
            await bot.send_message(tg_id, f"💸 С вас списано <b>{amount} баллов</b>\nПричина: {reason or '—'}")
            return r(f"Списано {amount} баллов")

    elif page == "codes":
        if "add_codes" in form:
            codes_text = form["codes"]
            codes_list = [c.strip() for c in codes_text.replace("\r\n", "\n").split("\n") if c.strip()]
            batch = form.get("batch", f"batch_{datetime.now().strftime('%Y%m%d%H%M%S')}")
            count = await db.register_code_batch(codes_list, batch)
            return r(f"Добавлено {count} кодов из партии {batch}")
        if "mark_won" in form:
            code_id = int(form["code_id"])
            winner_tg = int(form["winner_telegram_id"])
            winner = await db.get_user(winner_tg)
            if winner:
                await db.mark_code_won(code_id, winner["id"])
                return r(f"Код #{code_id} отмечен как выигрышный")
            return r("Пользователь не найден")

    elif page == "raffles":
        if "run_raffle" in form:
            prize_amount = int(form.get("prize_amount", 1000))
            result = await db.run_raffle(prize_amount)
            if result:
                w = result["winner"]
                await bot.send_message(
                    w["telegram_id"],
                    f"🎉 <b>Поздравляем!</b>\n\nВаш код <code>{w['code']}</code> выиграл <b>{prize_amount} руб!</b>\n\n7 дней на выбор: получить деньги или конвертировать в баллы.",
                    reply_markup=bot.payout_choice_keyboard(result["raffle_id"]),
                )
                return r(f"Розыгрыш проведён! Победитель: {w['name']}")
            return r("Нет активных кодов для розыгрыша")

    elif page == "prizes":
        if "add_prize" in form:
            name = form["name"]
            desc = form.get("description", "")
            price = int(form["price"])
            category_id = int(form.get("category_id", 0))
            image_url = ""
            file = form.get("prize_image")
            if file and hasattr(file, "filename") and file.filename:
                upload_dir = BASE_DIR / "public" / "uploads" / "prizes"
                upload_dir.mkdir(parents=True, exist_ok=True)
                ext = Path(file.filename).suffix.lower()
                if ext in (".png", ".jpg", ".jpeg", ".webp"):
                    filename = f"prize_{datetime.now().timestamp()}{ext}"
                    content = await file.read()
                    (upload_dir / filename).write_bytes(content)
                    image_url = f"/uploads/prizes/{filename}"
            await db.add_prize(name, desc, image_url, price, category_id)
            return r(f"Приз «{name}» добавлен")
        if "delete_prize" in form:
            prize_id = int(form["prize_id"])
            prize = await db.get_prize(prize_id)
            if prize and prize.get("image_url"):
                img_path = BASE_DIR / "public" / prize["image_url"].lstrip("/")
                if img_path.exists():
                    img_path.unlink()
            await db.delete_prize(prize_id)
            return r("Приз удалён")

    elif page == "shop":
        if "update_category" in form:
            cat_id = int(form["category_id"])
            data = {
                "title": form.get("title", ""),
                "subtitle": form.get("subtitle", ""),
                "description": form.get("description", ""),
                "icon": form.get("icon", "🎁"),
                "color": form.get("color", "#C9A84C"),
                "sort_order": int(form.get("sort_order", 0)),
                "is_active": "is_active" in form,
            }
            file = form.get("category_image")
            if file and hasattr(file, "filename") and file.filename:
                upload_dir = BASE_DIR / "public" / "uploads" / "shop"
                upload_dir.mkdir(parents=True, exist_ok=True)
                ext = Path(file.filename).suffix.lower()
                if ext in (".png", ".jpg", ".jpeg", ".webp"):
                    filename = f"cat_{cat_id}_{datetime.now().timestamp()}{ext}"
                    content = await file.read()
                    (upload_dir / filename).write_bytes(content)
                    data["image_url"] = f"/uploads/shop/{filename}"
            await db.update_shop_category(cat_id, data)
            return r(f"Категория «{data['title']}» обновлена")

    elif page == "orders":
        if "complete_order" in form:
            order_id = int(form["order_id"])
            tg_id = int(form["telegram_id"])
            await db.complete_order(order_id)
            await bot.send_message(tg_id, f"✅ Ваш заказ #{order_id} выполнен! Мы свяжемся с вами для уточнения получения.")
            return r(f"Заказ #{order_id} выполнен")

    elif page == "payouts":
        if "mark_paid" in form:
            raffle_id = int(form["raffle_id"])
            tg_id = int(form["telegram_id"])
            await db.mark_payout_paid(raffle_id)
            await bot.send_message(tg_id, f"💰 Выплата по выигрышу #{raffle_id} выполнена!")
            return r(f"Выплата #{raffle_id} отмечена")

    elif page == "points":
        if "adjust_points" in form:
            tg_id = int(form["telegram_id"])
            amount = int(form["amount"])
            reason = form.get("reason", "Корректировка администратором")
            await db.add_balance(tg_id, amount, "admin_adjust", reason)
            label = "Начислено" if amount > 0 else "Списано"
            await bot.send_message(tg_id, f"{'💰' if amount > 0 else '💸'} <b>{label} {abs(amount)} баллов</b>\nПричина: {reason}")
            return r(f"{label} {abs(amount)} баллов")
        if "adjust_xp" in form:
            tg_id = int(form["telegram_id_xp"])
            xp = int(form["xp_amount"])
            reason = form.get("xp_reason", "Начисление опыта")
            await db.add_tree_xp(tg_id, xp)
            label = "Начислено" if xp > 0 else "Списано"
            await bot.send_message(tg_id, f"{'🌳' if xp > 0 else '🍂'} <b>{label} {abs(xp)} XP</b>\nПричина: {reason}")
            return r(f"{label} {abs(xp)} XP")

    elif page == "bottles":
        if "generate_bottles" in form:
            count = min(max(int(form["count"]), 1), 10000)
            batch = form.get("batch", "")
            year = form.get("year", "")
            ids = await db.create_bottles_batch(count, batch, year)
            return r(f"Создано {len(ids)} бутылок")
        if "delete_bottle" in form:
            bottle_id = form["bottle_id"]
            ok = await db.delete_bottle(bottle_id)
            return r(f"Бутылка {bottle_id} {'удалена' if ok else 'не найдена'}")
        if "delete_batch" in form:
            year = form["year"]
            batch = form["batch"]
            count = await db.delete_batch(year, batch)
            return r(f"Удалено {count} бутылок из партии {year}-{batch}")

    elif page == "admins":
        if "add_admin" in form:
            tg_id = int(form["tg_id"])
            name = form.get("name", "")
            if await db.is_admin(tg_id):
                return r("Ошибка: администратор уже существует")
            await db.add_admin(tg_id, name, admin_id)
            await bot.send_message(tg_id, "🔑 Вам предоставлен доступ к админ-панели WaterPrize!\n\nОтправьте /start в боте для входа.")
            return r(f"Администратор {tg_id} добавлен")
        if "remove_admin" in form:
            tg_id = int(form["tg_id"])
            if tg_id == s["SUPERADMIN_ID"]:
                return r("Нельзя удалить супер-администратора")
            if tg_id == admin_id:
                return r("Нельзя удалить самого себя")
            if await db.remove_admin(tg_id):
                return r(f"Администратор {tg_id} удалён")
            return r("Ошибка при удалении")

    return None

# ─── Downloads ─────────────────────────────────────────

@app.get("/admin/download/{dl_type}")
async def admin_download(dl_type: str, request: Request):
    admin_id = await require_admin(request)
    if not admin_id:
        raise HTTPException(403)

    import qrcode
    from io import BytesIO

    s = get_settings()
    params = dict(request.query_params)

    if dl_type == "single" and params.get("id"):
        bottle_id = params["id"]
        bottle = await db.get_bottle_by_code(bottle_id)
        if not bottle:
            raise HTTPException(404)
        link = f"https://t.me/{s['BOT_USERNAME']}?start=bottle_{bottle_id}"
        img = qrcode.make(link)
        buf = BytesIO()
        img.save(buf, format="PNG")
        return Response(
            content=buf.getvalue(),
            media_type="image/png",
            headers={"Content-Disposition": f'attachment; filename="{bottle_id}.png"'},
        )

    if dl_type == "qr_zip" and params.get("batch"):
        batch_val = params["batch"]
        sub = params.get("sub", "")
        batch_key = f"{batch_val}-{sub}" if sub else batch_val
        bottles = await db.get_bottles(batch_key if sub else batch_val)
        if not bottles:
            raise HTTPException(404)
        buf = BytesIO()
        with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED) as zf:
            for b in bottles:
                link = f"https://t.me/{s['BOT_USERNAME']}?start=bottle_{b['bottle_id']}"
                img = qrcode.make(link)
                img_buf = BytesIO()
                img.save(img_buf, format="PNG")
                zf.writestr(f"{b['bottle_id']}.png", img_buf.getvalue())
        buf.seek(0)
        filename = f"bottles_{batch_val}{'_' + sub if sub else ''}.zip"
        return Response(
            content=buf.getvalue(),
            media_type="application/zip",
            headers={"Content-Disposition": f'attachment; filename="{filename}"'},
        )

    if dl_type == "pdf" and params.get("batch"):
        try:
            from weasyprint import HTML as WeasyprintHTML
        except ImportError:
            raise HTTPException(500, "weasyprint not installed")
        batch_val = params["batch"]
        sub = params.get("sub", "")
        batch_key = f"{batch_val}-{sub}" if sub else batch_val
        bottles = await db.get_bottles(batch_key if sub else batch_val)
        if not bottles:
            raise HTTPException(404)
        pdf_html = _build_pdf_html(bottles)
        pdf = WeasyprintHTML(string=pdf_html).write_pdf()
        filename = f"bottles_{batch_val}{'_' + sub if sub else ''}.pdf"
        return Response(
            content=pdf,
            media_type="application/pdf",
            headers={"Content-Disposition": f'attachment; filename="{filename}"'},
        )

    raise HTTPException(400)

def _build_pdf_html(bottles: list) -> str:
    import qrcode
    from io import BytesIO

    s = get_settings()
    cols = 8
    per_page = 80
    html_ = """<html><head><meta charset="UTF-8"><style>
        @page { margin: 5mm; size: A4 landscape; }
        body { margin: 0; padding: 0; font-family: sans-serif; }
        table { width: 100%; border-collapse: collapse; page-break-after: always; }
        td { width: """ + str(100 / cols) + """%; text-align: center; vertical-align: middle; padding: 2mm 1mm; }
        td img { width: 14mm; height: 14mm; display: block; margin: 0 auto; }
        td .label { font-size: 5.5pt; text-align: center; margin-top: 1mm; word-break: break-all; line-height: 1.2; color: #222; }
    </style></head><body>"""
    for chunk in [bottles[i:i + per_page] for i in range(0, len(bottles), per_page)]:
        html_ += '<table cellspacing="0" cellpadding="0">'
        for row_i in range(0, len(chunk), cols):
            html_ += "<tr>"
            for c in range(cols):
                if row_i + c < len(chunk):
                    b = chunk[row_i + c]
                    link = f"https://t.me/{s['BOT_USERNAME']}?start=bottle_{b['bottle_id']}"
                    img = qrcode.make(link)
                    buf = BytesIO()
                    img.save(buf, format="PNG")
                    b64 = base64.b64encode(buf.getvalue()).decode()
                    html_ += f'<td><img src="data:image/png;base64,{b64}" alt="QR"><div class="label">{html_mod.escape(b["bottle_id"])}</div></td>'
                else:
                    html_ += "<td></td>"
            html_ += "</tr>"
        html_ += "</table>"
    html_ += "</body></html>"
    return html_

# ─── Health ─────────────────────────────────────────────

@app.get("/health")
async def health():
    return {"status": "ok", "time": datetime.now().isoformat()}

# ─── Static Files (after all routes) ──────────────────

static_dir = BASE_DIR / "public"
if static_dir.exists():
    app.mount("/", StaticFiles(directory=str(static_dir), html=True), name="static")

    @app.get("/index.html")
    async def index_html():
        file_path = static_dir / "index.html"
        if file_path.exists():
            return HTMLResponse(content=file_path.read_text(encoding="utf-8"))
        return HTMLResponse(content="Not found", status_code=404)

# ─── Entry ─────────────────────────────────────────────

def run():
    import uvicorn
    s = get_settings()
    uvicorn.run(app, host="0.0.0.0", port=s["PORT"])
