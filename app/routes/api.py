"""API routes for the frontend app."""
import random

from fastapi import APIRouter, Request
from fastapi.responses import JSONResponse

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

    points = random.choices([10, 15, 25, 50, 100], weights=[35, 30, 20, 10, 5], k=1)[0]

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


@router.get("/check-admin")
async def api_check_admin(user_id: int = 0):
    if not user_id:
        return {"is_admin": False}
    return {"is_admin": await db.is_admin(user_id)}


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
    await db.add_balance(user_id, 10, "scan", f"Сканирование бутылки {bottle_id}")
    await db.add_tree_xp(user_id, 10)
    await db.create_notification(user_id, "scan", "Сканирование", f"+10 баллов за бутылку {bottle_id}", "history")

    stats = await db.get_user_stats(user_id)
    tree = await db.get_tree_state(user_id)
    return {"ok": True, "balance": stats["balance"], "total_scans": stats["total_scans"], "xp": tree["xp"], "level": tree["level"]}


@router.get("/settings")
async def api_settings():
    splash_logo_url = await db.get_setting("splash_logo_url")
    return {"splash_logo_url": splash_logo_url or ""}
