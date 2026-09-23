"""Validate Telegram Mini App initData for API authentication."""
import hashlib
import hmac
from urllib.parse import unquote, urlparse, parse_qsl

from fastapi import Request
from fastapi.responses import JSONResponse


def validate_telegram_init_data(init_data: str, bot_token: str) -> dict | None:
    """
    Validate Telegram Mini App initData.
    Returns parsed user dict if valid, None if invalid.
    https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
    """
    if not init_data or not bot_token:
        return None

    try:
        parsed = dict(parse_qsl(init_data, keep_blank_values=True))
    except Exception:
        return None

    hash_val = parsed.pop("hash", None)
    if not hash_val:
        return None

    # Build data-check-string
    data_check_arr = []
    for key in sorted(parsed.keys()):
        val = parsed[key]
        # bot API sends dates as integers, webapp sends them as strings
        # we need to keep them as-is for validation
        data_check_arr.append(f"{key}={val}")
    data_check_string = "\n".join(data_check_arr)

    # Secret key = SHA256(bot_token)
    secret_key = hmac.new(
        b"WebAppData", bot_token.encode(), hashlib.sha256
    ).digest()

    # Compute HMAC-SHA256
    computed_hash = hmac.new(
        secret_key, data_check_string.encode(), hashlib.sha256
    ).hexdigest()

    if not hmac.compare_digest(computed_hash, hash_val):
        return None

    # Parse user from initData
    user_json = parsed.get("user", "")
    if user_json:
        import json
        try:
            return json.loads(user_json)
        except Exception:
            pass

    return {}


async def verify_init_data(request: Request) -> JSONResponse | None:
    """FastAPI dependency: verify X-Telegram-Init-Data header."""
    from .config import get_settings
    s = get_settings()

    init_data = request.headers.get("X-Telegram-Init-Data", "")
    user = validate_telegram_init_data(init_data, s["BOT_TOKEN"])
    if user is None:
        return JSONResponse({"ok": False, "error": "unauthorized"}, status_code=401)
    return None  # means OK, no error
