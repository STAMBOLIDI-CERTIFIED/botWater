import logging
from datetime import datetime
from pathlib import Path

from fastapi import FastAPI
from fastapi.responses import HTMLResponse
from fastapi.staticfiles import StaticFiles
from starlette.middleware.base import BaseHTTPMiddleware
from starlette.requests import Request

from .config import get_settings
from .deps import db, BASE_DIR
from .routes.webhook import router as webhook_router
from .routes.api import router as api_router
from .routes.admin import router as admin_router

logging.basicConfig(level=logging.INFO, format='%(asctime)s %(name)s %(levelname)s %(message)s')
logger = logging.getLogger(__name__)

app = FastAPI(title="WaterPrize")


class NoCacheMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next):
        response = await call_next(request)
        if "text/html" in response.headers.get("content-type", ""):
            if not request.url.path.startswith("/admin"):
                response.headers["Cache-Control"] = "no-cache, no-store, must-revalidate, private"
                response.headers["Pragma"] = "no-cache"
                response.headers["Expires"] = "0"
        return response


app.add_middleware(NoCacheMiddleware)

# ─── Include routers ───────────────────────────────────

app.include_router(webhook_router)
app.include_router(api_router)
app.include_router(admin_router)

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
                "text": "Меню",
                "web_app": {"url": s["WEBAPP_URL"]},
            },
        })
        webhook_url = f"{domain}/webhook"
        await client.post(f"{api}/setWebhook", json={"url": webhook_url})
        logger.info(f"Webhook set to {webhook_url}")
        logger.info("Bot commands and menu button set")

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
