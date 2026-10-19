"""API routes for the frontend app."""
import html
import io
import json
import mimetypes
import random
import re
import time
import zipfile
from urllib.parse import parse_qsl
import hashlib
import hmac

from fastapi import APIRouter, HTTPException, Request, Response
from fastapi.responses import JSONResponse, StreamingResponse

from ..deps import db, get_settings, BASE_DIR
from .. import bot as tg_bot

router = APIRouter(prefix="/api")

# Каталог магазина — справочные данные, кэшируем на сервере и в браузере.
# Возвращаем «сырые» объекты (в них datetime), сериализацию делает FastAPI.
_CATALOG_TTL = 60.0
_CATALOG_CACHE_CONTROL = "public, max-age=60"
_categories_cache: dict = {"ts": 0.0, "val": None}
_category_cache: dict[int, tuple[float, dict]] = {}

# Аватары проксируем через бэкенд, чтобы не отдавать клиенту токен бота.
_AVATAR_TTL = 3600.0
_avatar_cache: dict[int, tuple[float, bytes, str]] = {}


def _validate_init_data(init_data: str, bot_token: str) -> dict | None:
    if not init_data or not bot_token:
        return None
    try:
        parsed = dict(parse_qsl(init_data, keep_blank_values=True))
    except Exception:
        return None
    hash_val = parsed.pop("hash", None)
    if not hash_val:
        return None
    data_check_string = "\n".join(f"{k}={parsed[k]}" for k in sorted(parsed))
    secret_key = hmac.new(b"WebAppData", bot_token.encode(), hashlib.sha256).digest()
    computed = hmac.new(secret_key, data_check_string.encode(), hashlib.sha256).hexdigest()
    if not hmac.compare_digest(computed, hash_val):
        return None
    # initData не должен быть слишком старым (защита от реплея).
    auth_date = parsed.get("auth_date")
    if auth_date:
        try:
            age = time.time() - int(auth_date)
        except (TypeError, ValueError):
            return None
        if age < -300 or age > 86400:
            return None
    user_json = parsed.get("user", "")
    if user_json:
        try:
            return json.loads(user_json)
        except Exception:
            pass
    return {}


def _auth_uid(request: Request) -> int | None:
    """Telegram id из проверенного initData (или None в dev-режиме)."""
    user = getattr(request.state, "tg_user", None) or {}
    try:
        uid = user.get("id")
        return int(uid) if uid else None
    except (TypeError, ValueError):
        return None


def _self(request: Request, user_id: int = 0) -> int:
    """Разрешает доступ только к своему аккаунту.

    Если initData проверен — сверяем id и игнорируем чужой user_id.
    Без initData сюда можно попасть только при ALLOW_INSECURE_API=1
    (локальная разработка), тогда доверяем переданному user_id.
    """
    auth = _auth_uid(request)
    if auth is not None:
        if user_id and int(user_id) != auth:
            raise HTTPException(403, "forbidden")
        return auth
    if getattr(request.state, "dev_auth", False) and user_id:
        return int(user_id)
    raise HTTPException(401, "unauthorized")


async def _self_or_admin(request: Request, telegram_id: int) -> int:
    auth = _auth_uid(request)
    if auth is not None and int(telegram_id) == auth:
        return auth
    if auth is None and getattr(request.state, "dev_auth", False) and telegram_id:
        return int(telegram_id)
    return await _require_admin(request)


async def _require_admin(request: Request) -> int:
    uid = _auth_uid(request)
    if uid is None:
        raise HTTPException(401, "unauthorized")
    try:
        if await db.is_admin(uid):
            return uid
    except Exception:
        pass
    raise HTTPException(403, "forbidden")


@router.get("/avatar/{user_id}")
async def api_avatar(user_id: int):
    if user_id <= 0:
        raise HTTPException(status_code=404)
    now = time.time()
    hit = _avatar_cache.get(user_id)
    if hit and now - hit[0] < _AVATAR_TTL:
        return Response(content=hit[1], media_type=hit[2], headers={"Cache-Control": "public, max-age=3600"})
    import httpx
    s = get_settings()
    try:
        async with httpx.AsyncClient() as client:
            up = await client.post(
                f"https://api.telegram.org/bot{s['BOT_TOKEN']}/getUserProfilePhotos",
                json={"user_id": user_id, "limit": 1}, timeout=10,
            )
            up_data = up.json()
            photos = (up_data.get("result") or {}).get("photos") or [] if up_data.get("ok") else []
            if not photos:
                raise HTTPException(status_code=404)
            fr = await client.post(
                f"https://api.telegram.org/bot{s['BOT_TOKEN']}/getFile",
                json={"file_id": photos[0][-1]["file_id"]}, timeout=10,
            )
            fd = fr.json()
            fp = (fd.get("result") or {}).get("file_path") if fd.get("ok") else None
            if not fp:
                raise HTTPException(status_code=404)
            r = await client.get(
                f"https://api.telegram.org/file/bot{s['BOT_TOKEN']}/{fp}", timeout=15,
            )
            if r.status_code != 200:
                raise HTTPException(status_code=404)
            ctype = mimetypes.guess_type(fp)[0] or "image/jpeg"
            _avatar_cache[user_id] = (now, r.content, ctype)
            return Response(content=r.content, media_type=ctype, headers={"Cache-Control": "public, max-age=3600"})
    except HTTPException:
        raise
    except Exception:
        raise HTTPException(status_code=404)


@router.get("/user")
async def api_user(request: Request, user_id: int = 0):
    user_id = _self(request, user_id)
    stats = await db.get_user_stats(user_id)
    user = await db.get_user(user_id)
    if not user:
        return None
    name = user.get("name") or ""
    username = ""
    photo_url = f"/api/avatar/{user_id}"
    try:
        import httpx
        s = get_settings()
        async with httpx.AsyncClient() as client:
            try:
                r = await client.post(
                    f"https://api.telegram.org/bot{s['BOT_TOKEN']}/getChat",
                    json={"chat_id": user_id}, timeout=10
                )
                d = r.json()
                if d.get("ok"):
                    chat = d["result"]
                    tg_name = chat.get("first_name", "")
                    if tg_name and not name:
                        name = tg_name
                        await db.update_user_name(user_id, tg_name)
                    username = chat.get("username", "") or ""
            except Exception:
                pass
    except Exception:
        pass
    return {
        "balance": stats["balance"],
        "total_scans": stats["total_scans"],
        "telegram_id": user.get("telegram_id"),
        "phone": user.get("phone"),
        "name": name,
        "username": username,
        "photo_url": photo_url,
    }


@router.post("/user/save")
async def api_user_save(request: Request):
    body = await request.json()
    user_id = _self(request, body.get("user_id", 0))
    name = body.get("name", "")
    if not user_id:
        return JSONResponse({"ok": False}, status_code=400)
    user = await db.get_user(user_id)
    if not user:
        return JSONResponse({"ok": False, "error": "user not found"}, status_code=404)
    if name and not user.get("name"):
        await db.update_user_name(user_id, name)
    return {"ok": True}


@router.get("/prizes")
async def api_prizes():
    return await db.get_prizes()


@router.get("/shop/categories")
async def api_shop_categories(response: Response):
    response.headers["Cache-Control"] = _CATALOG_CACHE_CONTROL
    now = time.monotonic()
    cached = _categories_cache["val"]
    if cached is not None and now - _categories_cache["ts"] < _CATALOG_TTL:
        return cached
    val = await db.get_shop_categories()
    _categories_cache["val"] = val
    _categories_cache["ts"] = now
    return val


@router.get("/shop/categories/{category_id}")
async def api_shop_category_items(category_id: int, response: Response):
    response.headers["Cache-Control"] = _CATALOG_CACHE_CONTROL
    now = time.monotonic()
    hit = _category_cache.get(category_id)
    if hit and now - hit[0] < _CATALOG_TTL:
        return hit[1]
    category = await db.get_shop_category(category_id)
    if not category:
        return JSONResponse({"error": "not found"}, status_code=404)
    items = await db.get_prizes_by_category(category_id)
    payload = {"category": category, "items": items}
    _category_cache[category_id] = (now, payload)
    return payload


@router.get("/shop/categories/{category_id}/items")
async def api_shop_category_items_list(category_id: int):
    return await db.get_prizes_by_category(category_id)


@router.get("/raffles")
async def api_raffles(user_id: int = 0):
    raffles = await db.get_raffle_results()
    for r in raffles:
        r["user_won"] = bool(user_id) and r.get("telegram_id") == user_id
    return raffles


@router.get("/history")
async def api_history(request: Request, user_id: int = 0):
    user_id = _self(request, user_id)
    return await db.get_scans(user_id)


@router.get("/points_log")
async def api_points_log(request: Request, user_id: int = 0):
    user_id = _self(request, user_id)
    return await db.get_points_log(user_id)


@router.get("/tree")
async def api_tree(request: Request, user_id: int = 0):
    user_id = _self(request, user_id)
    if not user_id:
        return {"xp": 0, "level": 1, "next_level_xp": 100, "progress": 0,
                "partners": [], "gift": {"available": False, "reason": "no_user"},
                "rules": await _tree_xp_rules(), "history": []}
    state = await db.get_tree_state(user_id)
    user = await db.get_user(user_id)
    if user:
        info = await db.get_partner_gift_info(user["id"])
        state["partners"] = info.get("partners", [])
        state["gift"] = info.get("gift", {})
        state["history"] = await db.get_tree_xp_history(user_id, limit=8)
    else:
        state["partners"] = []
        state["gift"] = {"available": False, "reason": "user_not_found"}
        state["history"] = []
    state["rules"] = await _tree_xp_rules()
    # Картинки стадий: настройки tree_stage_img_1..6 (пусто = дефолт).
    # Читаем напрямую через get_setting (без кэша get_bot_setting),
    # чтобы замена картинки в админке подхватывалась сразу.
    stages_meta = [
        (1, "Росток", "0–99"), (2, "Саженец", "100–499"), (3, "Молодое дерево", "500–999"),
        (4, "Крепкое дерево", "1000–1999"), (5, "Могучее дерево", "2000–4999"), (6, "Древо жизни", "5000+"),
    ]
    stages = []
    for lv, nm, xp_rng in stages_meta:
        img = ((await db.get_setting(f"tree_stage_img_{lv}")) or "").strip()
        stages.append({"level": lv, "name": nm, "xp": xp_rng, "img": img})
    state["stages"] = stages
    # Цель: сколько опыта осталось до следующей стадии
    xp = state.get("xp") or 0
    nxt = state.get("next_level_xp") or 0
    state["to_next"] = max(0, nxt - xp) if (state.get("level") or 1) < 6 else 0
    # NFT-уникальность дерева: tint + редкость + серийник из tree_seed
    # Админ-оверрайды (tree_rarity/tree_hue/tree_serial) перебивают авто-вывод
    if user:
        seed = await db.get_tree_seed(user_id)
        nft = db.derive_tree_nft(seed) if seed else None
        if nft:
            ov_rarity = (user.get("tree_rarity") or "").strip()
            ov_hue = user.get("tree_hue")
            ov_serial = (user.get("tree_serial") or "").strip()
            if ov_rarity in ("common", "rare", "epic", "legendary"):
                nft["rarity"] = ov_rarity
            if isinstance(ov_hue, int) and 0 <= ov_hue <= 359:
                nft["hue"] = ov_hue
            if ov_serial:
                nft["serial"] = ov_serial
        state["nft"] = nft
    else:
        state["nft"] = None
    return state


async def _tree_xp_rules() -> list[dict]:
    """Источники опыта для блока «За что начисляется опыт»."""
    return [
        {"key": "coupon_redeem", "title": "Трать купоны",
         "desc": "Активируй купон у партнёра", "value": "+цена купона XP"},
    ]


@router.post("/tree/gift/claim")
async def api_tree_gift_claim(request: Request):
    body = await request.json()
    user_id = _self(request, body.get("user_id", 0))
    if not user_id:
        return JSONResponse({"ok": False, "error": "missing parameters"}, status_code=400)
    user = await db.get_user(user_id)
    if not user:
        return JSONResponse({"ok": False, "error": "user_not_found"}, status_code=404)
    result = await db.claim_partner_gift(user["id"])
    if not result.get("ok"):
        status = 400 if result.get("error") != "internal_error" else 500
        return JSONResponse(result, status_code=status)
    return result


@router.get("/notifications")
async def api_notifications(request: Request, user_id: int = 0):
    user_id = _self(request, user_id)
    return await db.get_notifications(user_id)


@router.get("/notifications/clear")
async def api_notifications_clear(request: Request, user_id: int = 0):
    user_id = _self(request, user_id)
    await db.clear_notifications(user_id)
    return {"ok": True}


@router.get("/gift")
async def api_gift_status(request: Request, user_id: int = 0):
    user_id = _self(request, user_id)
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


@router.post("/gift/open")
async def api_gift_open(request: Request):
    import logging
    logger = logging.getLogger(__name__)

    body = await request.json()
    user_id = _self(request, body.get("user_id", 0))

    try:
        opened = await db.has_gift_been_opened(user_id)
    except Exception:
        opened = False
    if opened:
        return JSONResponse({"error": "gift already opened"}, status_code=409)

    gift_min = await db.get_bot_setting_int("gift_min", 10)
    gift_max = await db.get_bot_setting_int("gift_max", 100)
    gift_values = [gift_min, int(gift_min * 1.5), int(gift_min * 2.5), int(gift_min * 5), gift_max]
    gift_weights = [35, 30, 20, 10, 5]
    points = random.choices(gift_values, weights=gift_weights, k=1)[0]

    try:
        awarded = await db.mark_gift_opened(user_id, points)
    except Exception as e:
        logger.error(f"mark_gift_opened failed: {e}")
        return JSONResponse({"error": "failed to save gift"}, status_code=500)
    if not awarded:
        return JSONResponse({"error": "gift already opened"}, status_code=409)

    try:
        await db.create_notification(user_id, "points", "Подарок", f"Вы получили {points} баллов", "menu")
    except Exception:
        pass

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


@router.get("/nearest_prize")
async def api_nearest_prize(request: Request, user_id: int = 0):
    user_id = _self(request, user_id)
    nearest = await db.get_nearest_prize(user_id)
    return nearest or {}


@router.post("/scan")
async def api_scan(request: Request):
    body = await request.json()
    user_id = _self(request, body.get("user_id", 0))
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

    # Сканирование бутылок отключено: не начисляем баллы и опыт.
    await db.assign_bottle(bottle_id, user["id"])

    stats = await db.get_user_stats(user_id)
    tree = await db.get_tree_state(user_id)
    return {"ok": True, "balance": stats["balance"], "total_scans": stats["total_scans"], "xp": tree["xp"], "level": tree["level"]}


@router.get("/settings")
async def api_settings():
    keys = [
        "splash_logo_url",
        "dgis_api_key",
        "app_version",
        "terms_doc_url",
        "privacy_doc_url",
        "tile_shop_image",
        "tile_coupons_image",
        "tile_partner_image",
        "tile_support_image",
    ]
    vals = await db.get_settings_map(keys)
    tiles = {}
    for key, name in (
        ("tile_shop_image", "shop"),
        ("tile_coupons_image", "coupons"),
        ("tile_partner_image", "partner"),
        ("tile_support_image", "support"),
    ):
        val = vals.get(key)
        if val:
            tiles[name] = val
    return {
        "splash_logo_url": vals.get("splash_logo_url") or "",
        "dgis_api_key": vals.get("dgis_api_key") or "",
        "app_version": vals.get("app_version") or "",
        "terms_url": vals.get("terms_doc_url") or "",
        "privacy_url": vals.get("privacy_doc_url") or "",
        "tiles": tiles,
    }


# ─── Правовые документы: чтение прямо в мини-приложении ───

_LEGAL_TITLES = {
    "terms": "Пользовательское соглашение",
    "privacy": "Согласие на обработку персональных данных",
}
_LEGAL_SETTING = {
    "terms": "terms_doc_url",
    "privacy": "privacy_doc_url",
}


def _default_legal_html(kind: str) -> str:
    """Встроенная terms.html: соглашение или блок политики конфиденциальности."""
    try:
        raw = (BASE_DIR / "public" / "terms.html").read_text(encoding="utf-8")
    except Exception:
        return ""
    m = re.search(r'<div class="container">(.*)</div>', raw, re.S | re.I)
    body = m.group(1) if m else raw
    body = re.sub(r'<a class="back".*?</a>', "", body, flags=re.S | re.I)
    # Заголовок h1 дублирует заголовок страницы-просмотрщика
    body = re.sub(r"<h1[^>]*>.*?</h1>", "", body, flags=re.S | re.I, count=1)
    idx = body.find("<hr>")
    if idx != -1:
        body = body[:idx] if kind == "terms" else body[idx + len("<hr>"):]
    return body.strip()


def _docx_text(data: bytes) -> str:
    try:
        with zipfile.ZipFile(io.BytesIO(data)) as z:
            xml = z.read("word/document.xml").decode("utf-8", "replace")
    except Exception:
        return ""
    xml = re.sub(r"<w:tab[^>]*/>", "\t", xml)
    xml = re.sub(r"<w:br[^>]*/>", "\n", xml)
    xml = re.sub(r"</w:p>", "\n\n", xml)
    xml = re.sub(r"<[^>]+>", "", xml)
    return html.unescape(xml).strip()


def _odt_text(data: bytes) -> str:
    try:
        with zipfile.ZipFile(io.BytesIO(data)) as z:
            xml = z.read("content.xml").decode("utf-8", "replace")
    except Exception:
        return ""
    xml = re.sub(r"<text:line-break[^>]*/>", "\n", xml)
    xml = re.sub(r"</text:p>", "\n\n", xml)
    xml = re.sub(r"<[^>]+>", "", xml)
    return html.unescape(xml).strip()


def _text_to_html(text: str) -> str:
    out = []
    for line in text.replace("\r\n", "\n").replace("\r", "\n").split("\n"):
        line = line.strip()
        if line:
            out.append("<p>" + html.escape(line) + "</p>")
    return "".join(out)


async def _legal_bytes(url: str) -> bytes | None:
    """Байты локального файла из /uploads (диск или media_files в БД)."""
    if not url.startswith("/uploads/"):
        return None
    rel = url.split("?", 1)[0].split("#", 1)[0][len("/uploads/"):]
    if not rel or any(seg in ("", ".", "..") for seg in rel.split("/")):
        return None
    try:
        path = (BASE_DIR / "public" / "uploads" / rel).resolve()
        root = (BASE_DIR / "public" / "uploads").resolve()
        if root in path.parents and path.is_file():
            return path.read_bytes()
    except Exception:
        pass
    row = await db.get_media_file_by_path(rel)
    return row["bytes"] if row else None


@router.get("/legal/{kind}")
async def api_legal(kind: str):
    if kind not in _LEGAL_TITLES:
        raise HTTPException(status_code=404, detail="not found")
    title = _LEGAL_TITLES[kind]
    url = (await db.get_setting(_LEGAL_SETTING[kind]) or "").strip()
    if not url:
        return {"ok": True, "type": "html", "title": title, "content": _default_legal_html(kind)}

    path = url.split("?", 1)[0].split("#", 1)[0]
    ext = path.rsplit(".", 1)[-1].lower() if "." in path else ""
    if ext in ("html", "htm"):
        data = await _legal_bytes(url)
        if data:
            return {"ok": True, "type": "html", "title": title, "content": data.decode("utf-8", "replace")}
        return {"ok": True, "type": "link", "title": title, "url": url}
    if ext == "pdf":
        return {"ok": True, "type": "pdf", "title": title, "url": url}

    data = await _legal_bytes(url)
    if data is None:
        return {"ok": True, "type": "link", "title": title, "url": url}
    if ext == "docx":
        text = _docx_text(data)
    elif ext == "odt":
        text = _odt_text(data)
    elif ext in ("txt", "text", "md"):
        text = data.decode("utf-8", "replace")
        if "\ufffd" in text:
            try:
                text = data.decode("cp1251")
            except Exception:
                pass
    else:
        return {"ok": True, "type": "link", "title": title, "url": url}
    content = _text_to_html(text)
    if not content:
        return {"ok": True, "type": "link", "title": title, "url": url}
    return {"ok": True, "type": "html", "title": title, "content": content}


# Геокодинг текстового адреса партнёра (2GIS Geocoder) — для карты в карточке купона.
# Результат кэшируем: адреса статичны, а у бесплатного ключа лимит запросов.
_GEOCODE_TTL = 7 * 86400.0
_geocode_cache: dict[str, tuple[float, list | None]] = {}


@router.get("/geocode")
async def api_geocode(address: str = ""):
    address = (address or "").strip()
    if not address:
        return {"ok": False, "coords": None}
    key = await db.get_setting("dgis_api_key")
    if not key:
        return {"ok": False, "coords": None, "reason": "no_key"}
    now = time.time()
    hit = _geocode_cache.get(address.lower())
    if hit and now - hit[0] < _GEOCODE_TTL:
        return {"ok": hit[1] is not None, "coords": hit[1]}
    coords = None
    try:
        import httpx
        async with httpx.AsyncClient(timeout=8) as client:
            r = await client.get(
                "https://catalog.api.2gis.com/3.0/items/geocode",
                params={
                    "key": key,
                    "q": address,
                    "fields": "items.point",
                    "locale": "ru_RU",
                },
            )
        items = (r.json().get("result") or {}).get("items") or []
        point = items[0].get("point") if items else None
        if point:
            coords = [float(point["lon"]), float(point["lat"])]  # формат MapGL [долгота, широта]
    except Exception:
        coords = None
    _geocode_cache[address.lower()] = (now, coords)
    return {"ok": coords is not None, "coords": coords}


@router.get("/daily-bonus/status")
async def api_daily_bonus_status(request: Request, user_id: int = 0):
    user_id = _self(request, user_id)
    user = await db.get_user(user_id) if user_id else None
    if not user:
        return {"enabled": False}
    return await db.daily_bonus_state(user)


@router.get("/daily-bonus/claim")
async def api_daily_bonus_claim(request: Request, user_id: int = 0):
    user_id = _self(request, user_id)
    return await db.claim_daily_bonus(user_id)


@router.get("/partner/qr/{category_id}")
async def api_partner_qr(category_id: int):
    category = await db.get_shop_category(category_id)
    if not category:
        return JSONResponse({"error": "not found"}, status_code=404)
    qr_code = category.get("qr_code", "")
    if not qr_code:
        return JSONResponse({"error": "no qr code for this partner"}, status_code=404)

    s = get_settings()
    bot_username = s.get("BOT_USERNAME", "WaterPrizeBot")
    qr_content = f"https://t.me/{bot_username}?start={qr_code}"

    try:
        import qrcode
        from qrcode.image.styledpil import StyledPilImage
        from qrcode.image.styles.modifiers import RoundedModule
        from qrcode.image.styles.colormasks import SolidFillColorMask
    except ImportError:
        return JSONResponse({"error": "qrcode library not installed"}, status_code=500)

    qr = qrcode.QRCode(
        version=1,
        error_correction=qrcode.constants.ERROR_CORRECT_M,
        box_size=10,
        border=2,
    )
    qr.add_data(qr_content)
    qr.make(fit=True)

    accent = (category.get("color") or "#0EA5E9").lstrip("#")
    try:
        fg = tuple(int(accent[i:i+2], 16) for i in (0, 2, 4))
    except (ValueError, IndexError):
        fg = (14, 165, 233)

    img = qr.make_image(
        image_factory=StyledPilImage,
        color_mask=SolidFillColorMask(
            back_color=(255, 255, 255),
            front_color=fg,
        ),
    )

    buf = io.BytesIO()
    img.save(buf, format="PNG")
    buf.seek(0)

    return StreamingResponse(buf, media_type="image/png", headers={
        "Cache-Control": "public, max-age=3600",
    })


@router.post("/partner/scan")
async def api_partner_scan(request: Request):
    body = await request.json()
    user_id = _self(request, body.get("user_id", 0))
    qr_code = body.get("qr_code", "")
    if not user_id or not qr_code:
        return JSONResponse({"ok": False, "error": "missing user_id or qr_code"}, status_code=400)

    result = await db.process_partner_scan(user_id, qr_code)
    if not result.get("ok"):
        error = result.get("error", "")
        if error == "partner_not_found":
            status = 404
        else:
            status = 400
        return JSONResponse(result, status_code=status)
    return result


# ─── Coupon Redeem ──────────────────────────────────

@router.post("/coupon/redeem")
async def api_coupon_redeem(request: Request):
    body = await request.json()
    user_id = _self(request, body.get("user_id", 0))
    order_id = body.get("order_id", 0)
    partner_id = body.get("partner_id", 0)

    if not user_id or not order_id or not partner_id:
        return JSONResponse({"ok": False, "error": "missing parameters"}, status_code=400)

    result = await db.redeem_coupon(user_id, order_id, partner_id)
    if not result.get("ok"):
        return JSONResponse(result, status_code=400)
    return result


@router.post("/exchange")
async def api_exchange(request: Request):
    body = await request.json()
    user_id = _self(request, body.get("user_id", 0))
    prize_id = body.get("prize_id", 0)
    if not user_id or not prize_id:
        return JSONResponse({"ok": False, "error": "missing parameters"}, status_code=400)
    user = await db.get_user(user_id)
    if not user:
        return JSONResponse({"ok": False, "error": "user_not_found"}, status_code=404)
    prize = await db.get_prize(prize_id)
    if not prize:
        return JSONResponse({"ok": False, "error": "prize_not_found"}, status_code=404)
    price = int(prize["price_points"] or 0)
    # Атомарное списание: при параллельных запросах баланс не уйдёт в минус.
    balance = await db.spend_balance(
        user_id, price, "exchange", f"Обмен на приз «{prize['name']}»"
    )
    if balance is None:
        fresh = await db.get_user(user_id) or {}
        return JSONResponse({
            "ok": False, "error": "not_enough_points",
            "need": max(0, price - (fresh.get("balance") or 0)),
        }, status_code=400)
    try:
        order_id = await db.create_order(user["id"], prize_id)
        await db.record_journey(user["id"], "coupon_buy", partner_id=prize.get("category_id"), related_id=order_id, points_used=price)
        # Опыт начисляется только за ИСПОЛЬЗОВАННЫЙ купон (см. grant_coupon_xp)
        await db.create_notification(user_id, "points", "Обмен баллов", f"Приз: {prize['name']}", "history")
        return {"ok": True, "order_id": order_id, "prize_name": prize["name"],
                "balance": balance}
    except Exception as e:
        import logging
        logging.getLogger(__name__).exception(f"exchange error: {e}")
        # Заказ не создан — возвращаем списанные баллы.
        try:
            await db.add_balance(user_id, price, "exchange_refund", f"Возврат за приз «{prize['name']}»")
        except Exception:
            pass
        return JSONResponse({"ok": False, "error": "internal_error"}, status_code=500)


@router.get("/user/{user_id}/available-coupons")
async def api_available_coupons(request: Request, user_id: int):
    user_id = _self(request, user_id)
    user = await db.get_user(user_id)
    if not user:
        return JSONResponse({"ok": False, "error": "user not found"}, status_code=404)
    orders = await db.get_user_completed_orders(user["id"])
    return {"ok": True, "orders": orders}


@router.get("/user/{user_id}/journey")
async def api_user_journey(request: Request, user_id: int):
    user_id = _self(request, user_id)
    user = await db.get_user(user_id)
    if not user:
        return JSONResponse({"ok": False, "error": "user not found"}, status_code=404)
    journey = await db.get_user_journey(user["id"])
    return {"ok": True, "journey": journey}


# ─── User Coupons ────────────────────────────────────

@router.get("/user/{user_id}/coupons")
async def api_user_coupons(request: Request, user_id: int):
    user_id = _self(request, user_id)
    user = await db.get_user(user_id)
    if not user:
        return JSONResponse({"ok": False, "error": "user not found"}, status_code=404)
    coupons = await db.get_user_coupons(user["id"])
    return {"ok": True, "coupons": coupons}


@router.post("/coupon/favorite")
async def api_coupon_favorite(request: Request):
    body = await request.json()
    user_id = _self(request, body.get("user_id", 0))
    coupon_id = body.get("coupon_id", 0)
    favorite = bool(body.get("favorite", True))

    if not user_id or not coupon_id:
        return JSONResponse({"ok": False, "error": "missing parameters"}, status_code=400)
    user = await db.get_user(user_id)
    if not user:
        return JSONResponse({"ok": False, "error": "user not found"}, status_code=404)

    coupon = await db.set_user_coupon_favorite(user["id"], coupon_id, favorite)
    if not coupon:
        return JSONResponse({"ok": False, "error": "coupon not found"}, status_code=404)
    return {"ok": True, "coupon_id": coupon["id"], "is_favorite": bool(coupon["is_favorite"])}


# ─── Partner Dashboard ────────────────────────────────

@router.get("/partner-account/{telegram_id}")
async def api_partner_account(request: Request, telegram_id: int):
    await _self_or_admin(request, telegram_id)
    account = await db.get_partner_account_by_telegram_id(telegram_id)
    if not account:
        return JSONResponse({"ok": False, "error": "partner not found"}, status_code=404)
    return {"ok": True, "account": account}


@router.get("/partner-account/{telegram_id}/stats")
async def api_partner_stats(request: Request, telegram_id: int):
    await _self_or_admin(request, telegram_id)
    account = await db.get_partner_account_by_telegram_id(telegram_id)
    if not account:
        return JSONResponse({"ok": False, "error": "partner not found"}, status_code=404)
    kpi = await db.get_partner_kpi(account)
    return {"ok": True, "kpi": kpi}


@router.get("/partner-account/{partner_id}/used-coupons")
async def api_partner_used_coupons(request: Request, partner_id: int):
    account = await db.get_partner_account(partner_id)
    if not account:
        return JSONResponse({"ok": False, "error": "partner not found"}, status_code=404)
    await _self_or_admin(request, int(account.get("telegram_id") or 0))
    category_id = account.get("category_id")
    coupons = await db.get_partner_used_coupons(category_id)
    stats = await db.get_partner_coupon_stats(category_id)
    return {"ok": True, "coupons": coupons, "stats": stats, "account": account}


# ─── Coupon Activate (scan by partner) ────────────────

@router.post("/coupon/activate")
async def api_coupon_activate(request: Request):
    body = await request.json()
    qr_code = body.get("qr_code", "")
    partner_telegram_id = _self(request, body.get("partner_telegram_id", 0))

    if not qr_code or not partner_telegram_id:
        return JSONResponse({"ok": False, "error": "missing parameters"}, status_code=400)

    account = await db.get_partner_account_by_telegram_id(partner_telegram_id)
    if not account:
        return JSONResponse({"ok": False, "error": "partner_not_found"}, status_code=403)

    result = await db.activate_user_coupon(qr_code, account["id"])
    if not result.get("ok"):
        return JSONResponse(result, status_code=400)
    return result


# ─── Support Chat ─────────────────────────────────────

@router.get("/support/chat")
async def api_support_chat(request: Request, user_id: int = 0):
    user_id = _self(request, user_id)
    chat = await db.get_or_create_support_chat(user_id)
    if not chat:
        return JSONResponse({"error": "user not found"}, status_code=404)
    messages = await db.get_support_messages(chat["id"])
    return {"chat": chat, "messages": messages}


@router.post("/support/send")
async def api_support_send(request: Request):
    body = await request.json()
    user_id = _self(request, body.get("user_id", 0))
    message = body.get("message", "").strip()
    if not user_id or not message:
        return JSONResponse({"ok": False, "error": "missing user_id or message"}, status_code=400)
    chat = await db.get_or_create_support_chat(user_id)
    if not chat:
        return JSONResponse({"ok": False, "error": "user not found"}, status_code=404)
    msg = await db.send_support_message(chat["id"], "user", message)
    if not msg:
        return JSONResponse({"ok": False, "error": "failed to send"}, status_code=500)

    try:
        user = await db.get_user(user_id)
        admins = await db.get_admins()
        from ..config import get_settings
        s = get_settings()
        admin_ids = list(s.get("ADMIN_IDS", []))
        if s.get("SUPERADMIN_ID"):
            admin_ids.append(s["SUPERADMIN_ID"])
        for a in admins:
            if a.get("telegram_id") and a["telegram_id"] not in admin_ids:
                admin_ids.append(a["telegram_id"])
        if admin_ids:
            from html import escape
            user_name = user.get("name", "") if user else str(user_id)
            text = f"💬 Новое сообщение в чате поддержки от {escape(str(user_name))}:\n\n{escape(message)}"
            for admin_tg_id in admin_ids:
                try:
                    await tg_bot.send_message(admin_tg_id, text)
                except Exception:
                    pass
    except Exception:
        pass

    return {"ok": True, "message": msg}


@router.get("/support/admin/chats")
async def api_support_admin_chats(request: Request):
    await _require_admin(request)
    chats = await db.get_all_support_chats()
    return chats


@router.post("/support/admin/reply")
async def api_support_admin_reply(request: Request):
    await _require_admin(request)
    body = await request.json()
    chat_id = body.get("chat_id", 0)
    message = body.get("message", "").strip()
    if not chat_id or not message:
        return JSONResponse({"ok": False, "error": "missing chat_id or message"}, status_code=400)
    msg = await db.send_support_message(chat_id, "admin", message)
    if not msg:
        return JSONResponse({"ok": False, "error": "failed to send"}, status_code=500)

    try:
        chat = await db.get_support_chat_with_user(chat_id)
        if chat and chat.get("user_telegram_id"):
            from html import escape
            text = f"💬 Ответ поддержки:\n\n{escape(message)}"
            await tg_bot.send_message(chat["user_telegram_id"], text)
    except Exception:
        pass

    return {"ok": True, "message": msg}


@router.post("/support/admin/close")
async def api_support_admin_close(request: Request):
    await _require_admin(request)
    body = await request.json()
    chat_id = body.get("chat_id", 0)
    if not chat_id:
        return JSONResponse({"ok": False, "error": "missing chat_id"}, status_code=400)
    await db.close_support_chat(chat_id)
    return {"ok": True}
