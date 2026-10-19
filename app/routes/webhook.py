"""Webhook route."""
import logging
from fastapi import APIRouter, Request, HTTPException

from .. import bot
from ..deps import db

logger = logging.getLogger(__name__)

router = APIRouter()

_counter = 0


@router.post("/webhook")
async def webhook(request: Request):
    global _counter
    # Telegram присылает секрет, заданный в setWebhook (см. main._setup_bot_commands).
    # Без него любой мог бы подделать update (chat_id/from.id берутся из тела).
    secret = request.headers.get("X-Telegram-Bot-Api-Secret-Token", "")
    if not bot.verify_webhook_secret(secret):
        raise HTTPException(403, "Forbidden")

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

    try:
        await bot.handle_update(db, body)
    except Exception as e:
        logger.error(f"webhook handle_update error: {e}", exc_info=True)
    return {"ok": True}
