"""API routes for the frontend app."""
import io
import random

from fastapi import APIRouter, Request
from fastapi.responses import JSONResponse, StreamingResponse

from ..deps import db, get_settings

router = APIRouter(prefix="/api")


@router.get("/user")
async def api_user(user_id: int = 0):
    if not user_id:
        return JSONResponse(None)
    stats = await db.get_user_stats(user_id)
    user = await db.get_user(user_id)
    if not user:
        return None
    name = user.get("name") or ""
    username = ""
    photo_url = ""
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
            try:
                up = await client.post(
                    f"https://api.telegram.org/bot{s['BOT_TOKEN']}/getUserProfilePhotos",
                    json={"user_id": user_id, "limit": 1}, timeout=10
                )
                up_data = up.json()
                if up_data.get("ok"):
                    photos = up_data.get("result", {}).get("photos", [])
                    if photos:
                        biggest = photos[0][-1]
                        fr = await client.post(
                            f"https://api.telegram.org/bot{s['BOT_TOKEN']}/getFile",
                            json={"file_id": biggest["file_id"]}, timeout=10
                        )
                        fd = fr.json()
                        if fd.get("ok") and fd.get("result", {}).get("file_path"):
                            photo_url = f"https://api.telegram.org/file/bot{s['BOT_TOKEN']}/{fd['result']['file_path']}"
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
    user_id = body.get("user_id", 0)
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
async def api_shop_categories():
    return await db.get_shop_categories()


@router.get("/shop/categories/{category_id}")
async def api_shop_category_items(category_id: int):
    category = await db.get_shop_category(category_id)
    if not category:
        return JSONResponse({"error": "not found"}, status_code=404)
    items = await db.get_prizes_by_category(category_id)
    return {"category": category, "items": items}


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
async def api_history(user_id: int = 0):
    if not user_id:
        return []
    return await db.get_scans(user_id)


@router.get("/points_log")
async def api_points_log(user_id: int = 0):
    if not user_id:
        return []
    return await db.get_points_log(user_id)


@router.get("/tree")
async def api_tree(user_id: int = 0):
    if not user_id:
        return {"xp": 0, "level": 1, "next_level_xp": 100, "progress": 0}
    return await db.get_tree_state(user_id)


@router.get("/notifications")
async def api_notifications(user_id: int = 0):
    if not user_id:
        return []
    return await db.get_notifications(user_id)


@router.get("/notifications/clear")
async def api_notifications_clear(user_id: int = 0):
    if user_id:
        await db.clear_notifications(user_id)
    return {"ok": True}


@router.get("/gift")
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


@router.post("/gift/open")
async def api_gift_open(request: Request):
    import logging
    logger = logging.getLogger(__name__)

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

    gift_min = await db.get_bot_setting_int("gift_min", 10)
    gift_max = await db.get_bot_setting_int("gift_max", 100)
    gift_values = [gift_min, int(gift_min * 1.5), int(gift_min * 2.5), int(gift_min * 5), gift_max]
    gift_weights = [35, 30, 20, 10, 5]
    points = random.choices(gift_values, weights=gift_weights, k=1)[0]

    try:
        await db.mark_gift_opened(user_id, points)
    except Exception as e:
        logger.error(f"mark_gift_opened failed: {e}")
        return JSONResponse({"error": "failed to save gift"}, status_code=500)

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
async def api_nearest_prize(user_id: int = 0):
    if not user_id:
        return JSONResponse({"error": "missing user_id"}, status_code=400)
    nearest = await db.get_nearest_prize(user_id)
    return nearest or {}


@router.post("/scan")
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
    scan_balance = await db.get_bot_setting_int("scan_balance", 10)
    scan_xp = await db.get_bot_setting_int("scan_xp", 10)
    await db.add_balance(user_id, scan_balance, "scan", f"Сканирование бутылки {bottle_id}")
    await db.add_tree_xp(user_id, scan_xp)
    await db.create_notification(user_id, "scan", "Сканирование", f"+{scan_balance} баллов за бутылку {bottle_id}", "history")

    stats = await db.get_user_stats(user_id)
    tree = await db.get_tree_state(user_id)
    return {"ok": True, "balance": stats["balance"], "total_scans": stats["total_scans"], "xp": tree["xp"], "level": tree["level"]}


@router.get("/settings")
async def api_settings():
    splash_logo_url = await db.get_setting("splash_logo_url")
    return {"splash_logo_url": splash_logo_url or ""}


@router.get("/partner/qr/{category_id}")
async def api_partner_qr(category_id: int):
    category = await db.get_shop_category(category_id)
    if not category:
        return JSONResponse({"error": "not found"}, status_code=404)
    qr_code = category.get("qr_code", "")
    if not qr_code:
        return JSONResponse({"error": "no qr code for this partner"}, status_code=404)

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
    qr.add_data(qr_code)
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
    user_id = body.get("user_id", 0)
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


# ─── Support Chat ─────────────────────────────────────

@router.get("/support/chat")
async def api_support_chat(user_id: int = 0):
    if not user_id:
        return JSONResponse({"error": "missing user_id"}, status_code=400)
    chat = await db.get_or_create_support_chat(user_id)
    if not chat:
        return JSONResponse({"error": "user not found"}, status_code=404)
    messages = await db.get_support_messages(chat["id"])
    return {"chat": chat, "messages": messages}


@router.post("/support/send")
async def api_support_send(request: Request):
    body = await request.json()
    user_id = body.get("user_id", 0)
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
            import httpx
            user_name = user.get("name", "") if user else str(user_id)
            text = f"💬 Новое сообщение в чате поддержки от {user_name}:\n\n{message}"
            async with httpx.AsyncClient() as client:
                for admin_tg_id in admin_ids:
                    try:
                        await client.post(
                            f"https://api.telegram.org/bot{s['BOT_TOKEN']}/sendMessage",
                            json={"chat_id": admin_tg_id, "text": text}, timeout=10
                        )
                    except Exception:
                        pass
    except Exception:
        pass

    return {"ok": True, "message": msg}


@router.get("/support/admin/chats")
async def api_support_admin_chats():
    chats = await db.get_all_support_chats()
    return chats


@router.post("/support/admin/reply")
async def api_support_admin_reply(request: Request):
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
            from ..config import get_settings
            s = get_settings()
            import httpx
            text = f"💬 Ответ поддержки:\n\n{message}"
            async with httpx.AsyncClient() as client:
                await client.post(
                    f"https://api.telegram.org/bot{s['BOT_TOKEN']}/sendMessage",
                    json={"chat_id": chat["user_telegram_id"], "text": text}, timeout=10
                )
    except Exception:
        pass

    return {"ok": True, "message": msg}


@router.post("/support/admin/close")
async def api_support_admin_close(request: Request):
    body = await request.json()
    chat_id = body.get("chat_id", 0)
    if not chat_id:
        return JSONResponse({"ok": False, "error": "missing chat_id"}, status_code=400)
    await db.close_support_chat(chat_id)
    return {"ok": True}
