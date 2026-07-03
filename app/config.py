import os
import time
from functools import lru_cache
from urllib.parse import urlparse
from pathlib import Path

_load_dotenv_done = False


def _load_dotenv():
    global _load_dotenv_done
    if _load_dotenv_done:
        return
    env_path = Path(__file__).parent.parent / ".env"
    if env_path.exists():
        for line in env_path.read_text().splitlines():
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, _, val = line.partition("=")
            key = key.strip()
            val = val.strip().strip("\"'")
            if key and not os.environ.get(key):
                os.environ[key] = val
    prod_path = Path(__file__).parent.parent / "prod.env"
    if prod_path.exists():
        for line in prod_path.read_text().splitlines():
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, _, val = line.partition("=")
            key = key.strip()
            val = val.strip().strip("\"'")
            if key and not os.environ.get(key):
                os.environ[key] = val
    _load_dotenv_done = True


@lru_cache
def get_settings():
    _load_dotenv()
    bot_token = os.environ.get("BOT_TOKEN") or os.environ.get("API_TOKEN") or os.environ.get("TELEGRAM_BOT_TOKEN") or ""
    domain = os.environ.get("DOMAIN", "localhost:8080")
    parsed = urlparse(domain)
    scheme = "https" if parsed.scheme == "https" else ("https" if "trycloudflare" in domain or "bothost" in domain or "waterprize" in domain else "http")
    if not domain.startswith("http"):
        domain = f"{scheme}://{domain}"
    index_path = Path(__file__).parent.parent / "public" / "index.html"
    v = str(int(index_path.stat().st_mtime)) if index_path.exists() else "1"
    webapp_url = os.environ.get("WEBAPP_URL", f"{domain}/index.html?v={v}")
    admin_panel_url = os.environ.get("ADMIN_PANEL_URL", f"{domain}/admin?v={v}")
    bot_username = os.environ.get("BOT_USERNAME", "WaterPrizeBot")
    admin_ids_str = os.environ.get("ADMIN_IDS", "818439646")
    admin_ids = [int(x.strip()) for x in admin_ids_str.split(",") if x.strip()]

    return {
        "BOT_TOKEN": bot_token,
        "DB_HOST": os.environ.get("DB_HOST", "localhost"),
        "DB_PORT": int(os.environ.get("DB_PORT", 5432)),
        "DB_NAME": os.environ.get("DB_NAME", "waterprize"),
        "DB_USER": os.environ.get("DB_USER", "postgres"),
        "DB_PASS": os.environ.get("DB_PASS", ""),
        "DOMAIN": domain,
        "WEBAPP_URL": webapp_url,
        "ADMIN_PANEL_URL": admin_panel_url,
        "BOT_USERNAME": bot_username,
        "ADMIN_IDS": admin_ids,
        "SUPERADMIN_ID": 818439646,
        "PORT": int(os.environ.get("PORT", 8080)),
        "SUPABASE_URL": os.environ.get("SUPABASE_URL", ""),
        "SUPABASE_KEY": os.environ.get("SUPABASE_KEY", ""),
    }
