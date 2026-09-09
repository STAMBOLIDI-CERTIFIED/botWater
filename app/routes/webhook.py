"""Webhook route."""
from fastapi import APIRouter, Request, HTTPException

from .. import bot
from ..deps import db

router = APIRouter()

_counter = 0


@router.post("/webhook")
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
