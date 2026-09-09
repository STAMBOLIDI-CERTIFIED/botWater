"""Shared dependencies for route modules."""
import logging
from pathlib import Path

from .config import get_settings
from .database import Database

logger = logging.getLogger(__name__)

BASE_DIR = Path(__file__).parent.parent

db = Database()


class SafeJinja2Templates:
    def __init__(self, directory: str):
        from jinja2 import Environment, FileSystemLoader, select_autoescape
        self.env = Environment(
            loader=FileSystemLoader(directory),
            autoescape=select_autoescape(["html", "xml"]),
            cache_size=0,
        )

    def TemplateResponse(self, name: str, context: dict):
        from starlette.templating import _TemplateResponse
        template = self.env.get_template(name)
        return _TemplateResponse(template, context)


templates = SafeJinja2Templates(directory=str(BASE_DIR / "app" / "templates"))
