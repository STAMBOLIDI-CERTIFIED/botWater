"""Shared dependencies for route modules."""
import logging
from pathlib import Path

from .config import get_settings
from .database import Database

logger = logging.getLogger(__name__)

BASE_DIR = Path(__file__).parent.parent

db = Database()
