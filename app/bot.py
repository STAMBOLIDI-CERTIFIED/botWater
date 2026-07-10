import json
import logging
import httpx

logger = logging.getLogger(__name__)

_API_BASE = "https://api.telegram.org/bot"


def _token() -> str:
    from .config import get_settings
    return get_settings()["BOT_TOKEN"]


def _url(method: str) -> str:
    return f"{_API_BASE}{_token()}/{method}"


_chat_messages: dict[int, list[int]] = {}


async def send_message(chat_id: int, text: str, **kwargs):
    payload = {"chat_id": chat_id, "text": text, "parse_mode": "HTML", **kwargs}
    async with httpx.AsyncClient() as client:
        try:
            resp = await client.post(_url("sendMessage"), json=payload)
            data = resp.json()
            if data and data.get("ok") and data.get("result", {}).get("message_id"):
                _chat_messages.setdefault(chat_id, []).append(data["result"]["message_id"])
            return data
        except Exception as e:
            logger.error(f"sendMessage failed: {e}")
            return None


async def delete_chat_messages(chat_id: int):
    msgs = _chat_messages.pop(chat_id, [])
    if not msgs:
        return
    async with httpx.AsyncClient() as client:
        for msg_id in msgs:
            try:
                await client.post(_url("deleteMessage"), json={"chat_id": chat_id, "message_id": msg_id})
            except Exception:
                pass


async def answer_callback(callback_id: str, text: str = "", show_alert: bool = False):
    payload = {"callback_query_id": callback_id, "text": text, "show_alert": show_alert}
    async with httpx.AsyncClient() as client:
        try:
            await client.post(_url("answerCallbackQuery"), json=payload)
        except Exception as e:
            logger.error(f"answerCallbackQuery failed: {e}")


# ─── Keyboards ──────────────────────────────────────────

def main_menu_keyboard(webapp_url: str, is_admin: bool = False, chat_id: int = 0) -> dict:
    app_url = webapp_url
    if chat_id:
        sep = "&" if "?" in app_url else "?"
        app_url = app_url + sep + "user_id=" + str(chat_id)
    kb = {
        "inline_keyboard": [
            [
                {"text": "📱 Открыть приложение", "web_app": {"url": app_url}},
            ],
            [
                {"text": "💰 Баланс", "callback_data": "balance"},
                {"text": "📊 Статистика", "callback_data": "stats"},
            ],
            [
                {"text": "🎰 Следующий розыгрыш", "callback_data": "raffle_info"},
            ],
        ]
    }
    if is_admin:
        kb["inline_keyboard"].append([
            {"text": "🔐 Админ-панель", "web_app": {"url": get_settings()["ADMIN_PANEL_URL"]}}
        ])
    return kb


def contact_keyboard() -> dict:
    return {
        "keyboard": [
            [
                {
                    "text": "📱 Отправить номер телефона",
                    "request_contact": True,
                }
            ]
        ],
        "resize_keyboard": True,
        "one_time_keyboard": True,
    }


def terms_keyboard() -> dict:
    return {
        "inline_keyboard": [
            [
                {"text": "✅ Принимаю", "callback_data": "accept_terms"},
                {"text": "❌ Отклоняю", "callback_data": "decline_terms"},
            ],
            [{"text": "📄 Пользовательское соглашение", "url": get_settings()["WEBAPP_URL"].replace("index.html", "terms.html")}],
        ]
    }


def payout_choice_keyboard(raffle_id: int) -> dict:
    return {
        "inline_keyboard": [
            [
                {"text": "💳 Получить деньги", "callback_data": f"payout_money:{raffle_id}"},
                {"text": "🔄 Конвертировать в баллы", "callback_data": f"payout_points:{raffle_id}"},
            ],
        ]
    }


def consent_keyboard(raffle_id: int) -> dict:
    return {
        "inline_keyboard": [
            [
                {"text": "✅ Согласен", "callback_data": f"accept_consent:{raffle_id}"},
                {"text": "❌ Отмена", "callback_data": f"cancel_consent:{raffle_id}"},
            ],
        ]
    }


def passport_confirm_keyboard(raffle_id: int) -> dict:
    return {
        "inline_keyboard": [
            [
                {"text": "✅ Всё верно", "callback_data": f"confirm_passport:{raffle_id}"},
                {"text": "🔄 Заново", "callback_data": f"restart_passport:{raffle_id}"},
            ],
        ]
    }


def exchange_confirm_keyboard(prize_id: int) -> dict:
    return {
        "inline_keyboard": [
            [
                {"text": "✅ Подтвердить", "callback_data": f"exchange_prize:{prize_id}"},
                {"text": "❌ Отмена", "callback_data": "cancel_exchange"},
            ],
        ]
    }


# ─── Helpers ────────────────────────────────────────────

def get_settings():
    from .config import get_settings as _gs
    return _gs()


async def process_expired_payouts(db):
    count = await db.process_expired_payouts()
    if count:
        logger.info(f"Auto-converted {count} expired payouts to points")


# ─── Main Dispatcher ────────────────────────────────────

async def handle_update(db, body: dict):
    if "message" in body:
        await handle_message(db, body["message"])
    elif "callback_query" in body:
        await handle_callback(db, body["callback_query"])
    elif "my_chat_member" in body:
        pass


# ─── Message Handler ────────────────────────────────────

async def handle_message(db, msg: dict):
    chat_id = msg["chat"]["id"]
    text = msg.get("text", "")
    contact = msg.get("contact")
    web_app_data = msg.get("web_app_data")
    user = await db.get_user(chat_id)
    s = get_settings()
    tg_name = msg.get("from", {}).get("first_name", "")

    logger.info(f"handle_message chat_id={chat_id} text={text!r} web_app_data={web_app_data!r} user_step={user.get('step') if user else None}")

    if web_app_data:
        await handle_webapp_data(db, web_app_data.get("data", ""), chat_id)
        return

    if text.startswith("/"):
        await delete_chat_messages(chat_id)

    if text.startswith("/start"):
        payload = ""
        if " " in text:
            payload = text.split(" ", 1)[1]
        if not user:
            user = await db.create_user(chat_id, tg_name, "menu", payload)
        elif user.get("step") not in ("start", None, "menu"):
            await db.update_user_step(chat_id, "menu")
        await handle_start(db, chat_id, user, payload)
        return

    step = user.get("step", "start")

    if step in ("ask_fio",):
        if not text or len(text.strip()) < 3:
            await send_message(chat_id, "⚠️ Введите ФИО полностью (минимум 3 символа):")
            return
        await db.update_passport_data(chat_id, fio=text.strip())
        await db.update_user_step(chat_id, "ask_passport")
        raffle_id = user.get("start_payload", "").replace("payout_", "")
        await send_message(chat_id, "📄 Введите <b>серию и номер паспорта</b> (цифры, без пробелов):")
        return

    if step == "ask_passport":
        snumber = text.strip().replace(" ", "").replace("-", "")
        if not snumber.isdigit() or len(snumber) < 6:
            await send_message(chat_id, "⚠️ Введите корректные серию и номер паспорта (только цифры):")
            return
        await db.update_passport_data(chat_id, snumber=snumber)
        await db.update_user_step(chat_id, "ask_inn")
        await send_message(chat_id, "📄 Введите <b>ИНН</b> (12 цифр):")
        return

    if step == "ask_inn":
        inn = text.strip()
        if not inn.isdigit() or len(inn) not in (10, 12):
            await send_message(chat_id, "⚠️ Введите корректный ИНН (10 или 12 цифр):")
            return
        await db.update_passport_data(chat_id, inn=inn)
        await db.update_user_step(chat_id, "ask_confirm_passport")
        user_data = await db.get_user(chat_id)
        raffle_id = user_data.get("start_payload", "").replace("payout_", "") if user_data else ""
        try:
            rid = int(raffle_id) if raffle_id and raffle_id.isdigit() else 0
        except ValueError:
            rid = 0
        await send_message(
            chat_id,
            f"📋 <b>Проверьте данные:</b>\n\n"
            f"ФИО: {user_data['passport_fio']}\n"
            f"Паспорт: {user_data['passport_snumber']}\n"
            f"ИНН: {user_data['passport_inn']}\n\n"
            f"Всё верно?",
            reply_markup=passport_confirm_keyboard(rid),
        )
        return

    # ── Commands ──
    if text == "/profile":
        await show_profile(db, chat_id, user)
        return

    if text == "/admin":
        if user and await db.is_admin(chat_id):
            s = get_settings()
            await send_message(
                chat_id,
                f"🔐 <b>Админ-панель</b>\n\nНажмите кнопку ниже для входа:",
                reply_markup={"inline_keyboard": [[{"text": "🔐 Админ-панель", "web_app": {"url": s["ADMIN_PANEL_URL"]}}]]},
            )
        else:
            await send_message(chat_id, "⛔ Доступ запрещён.")
        return

    if text == "/terms":
        await send_message(
            chat_id,
            f"📄 <b>Пользовательское соглашение</b>\n\n{get_settings()['WEBAPP_URL'].replace('index.html', 'terms.html')}",
        )
        return

    # ── Default: try as QR code ──
    if step == "menu" or step.startswith("ask_"):
        # Maybe it's a QR code scan from manual input
        pass


# ─── Start Handler ──────────────────────────────────────

async def handle_start(db, chat_id: int, user: dict | None, payload: str):
    s = get_settings()
    logger.info(f"handle_start chat_id={chat_id} payload={payload!r} user={user}")
    if not user:
        user = await db.get_user(chat_id)
    if not user:
        await show_main_menu(db, chat_id)
        return

    if user.get("step") != "menu":
        await db.update_user_step(chat_id, "menu")

    # Extract bottle_XXXX from payload (supports full URLs and bare codes)
    if payload:
        if "start=bottle_" in payload:
            payload = payload.split("start=bottle_", 1)[1].split("&", 1)[0]
        elif payload.startswith("bottle_"):
            payload = payload[len("bottle_"):]
        elif payload.startswith("BTL-"):
            pass
        else:
            payload = ""

    bottle_id = payload
    if bottle_id:
        bottle = await db.get_bottle_by_code(bottle_id)
        logger.info(f"handle_start bottle_id={bottle_id} bottle_found={bottle is not None} assigned_to={bottle.get('assigned_to') if bottle else None}")
        if not bottle:
            await send_message(chat_id, "❌ Бутылка не найдена в системе.")
            await show_main_menu(db, chat_id, user)
            return
        if user["step"] != "menu":
            await db.update_user_step(chat_id, "menu")
        if bottle.get("assigned_to"):
            await send_message(chat_id, "⚠️ Эта бутылка уже была отсканирована другим пользователем.")
            await show_main_menu(db, chat_id, user)
            return
        user_row = await db.get_user(chat_id)
        await db.assign_bottle(bottle_id, user_row["id"])
        await db.add_balance(chat_id, 10, "scan", f"Сканирование бутылки {bottle_id}")
        await db.add_tree_xp(chat_id, 10)
        await send_message(
            chat_id,
            f"✅ <b>Бутылка зарегистрирована!</b>\n\n"
            f"➕ Вам начислено <b>10 баллов</b>\n"
            f"💰 Текущий баланс: <b>{user_row['balance'] + 10} баллов</b>",
        )
        await show_main_menu(db, chat_id, user)
        return

    await show_main_menu(db, chat_id, user)


# ─── Main Menu ──────────────────────────────────────────

async def show_main_menu(db, chat_id: int, user: dict | None = None):
    user = await db.get_user(chat_id) or await db.create_user(chat_id)
    if user["step"] != "menu":
        await db.update_user_step(chat_id, "menu")
    s = get_settings()
    is_admin = await db.is_admin(chat_id)
    await send_message(
        chat_id,
        f"💧 <b>Главное меню</b>\n\n"
        f"Привет, {user['name'] or 'друг'}! 👋\n"
        f"💰 Баланс: <b>{user['balance']} баллов</b>\n\n"
        f"Сканируйте QR-коды на бутылках и получайте баллы!",
        reply_markup=main_menu_keyboard(s["WEBAPP_URL"], is_admin, chat_id),
    )


# ─── Profile ────────────────────────────────────────────

async def show_profile(db, chat_id: int, user: dict | None = None):
    if not user:
        user = await db.get_user(chat_id)
    if not user:
        await send_message(chat_id, "Пользователь не найден.")
        return
    stats = await db.get_user_stats(chat_id)
    await send_message(
        chat_id,
        f"👤 <b>Профиль</b>\n\n"
        f"ID: <code>{chat_id}</code>\n"
        f"Имя: {user['name'] or '—'}\n"
        f"Телефон: {user['phone'] or '—'}\n"
        f"💰 Баланс: <b>{stats['balance']} баллов</b>\n"
        f"📊 Сканирований: <b>{stats['total_scans']}</b>\n\n"
        f"📄 <a href='{get_settings()['WEBAPP_URL'].replace('index.html', 'terms.html')}'>Пользовательское соглашение</a>",
    )


# ─── Callback Handler ───────────────────────────────────

async def handle_callback(db, cbd: dict):
    cid = cbd["id"]
    chat_id = cbd["message"]["chat"]["id"]
    data = cbd["data"]
    user = await db.get_user(chat_id)
    s = get_settings()

    if data == "accept_terms":
        await db.accept_terms(chat_id)
        await answer_callback(cid, "✅ Соглашение принято!")
        payload = user.get("start_payload", "") if user else ""
        if payload and payload.startswith("bottle_"):
            await handle_start(db, chat_id, user, payload)
        else:
            await show_main_menu(db, chat_id, user)
        return

    if data == "decline_terms":
        await answer_callback(cid, "❌ Вы отказались от соглашения", show_alert=True)
        await send_message(
            chat_id,
            "К сожалению, без принятия соглашения использование бота невозможно.",
        )
        return

    if data == "balance":
        await answer_callback(cid)
        stats = await db.get_user_stats(chat_id)
        await send_message(
            chat_id,
            f"💰 <b>Ваш баланс:</b> {stats['balance']} баллов\n"
            f"📊 Всего сканирований: {stats['total_scans']}",
        )
        return

    if data == "stats":
        await answer_callback(cid)
        stats = await db.get_user_stats(chat_id)
        codes_count = await db.get_active_codes_count()
        unassigned = await db.get_unassigned_bottle_count()
        await send_message(
            chat_id,
            f"📊 <b>Статистика</b>\n\n"
            f"👤 Ваши сканирования: <b>{stats['total_scans']}</b>\n"
            f"💰 Баллы: <b>{stats['balance']}</b>\n"
            f"📱 Активных QR-кодов: <b>{codes_count}</b>\n"
            f"🍾 Свободных бутылок: <b>{unassigned}</b>",
        )
        return

    if data == "raffle_info":
        await answer_callback(cid)
        raf_stats = await db.get_raffle_stats()
        await send_message(
            chat_id,
            f"🎰 <b>Розыгрыши</b>\n\n"
            f"Всего розыгрышей: <b>{raf_stats['total_raffles']}</b>\n"
            f"Завершено: <b>{raf_stats['completed']}</b>\n\n"
            f"Следите за новостями в приложении!",
        )
        return

    if data.startswith("payout_money:"):
        raffle_id = int(data.split(":", 1)[1])
        await answer_callback(cid)
        await db.set_payout_choice(raffle_id, "money")
        user_data = await db.get_user(chat_id)
        if user_data and not user_data.get("passport_fio"):
            await db.update_user_step(chat_id, "ask_fio")
            await db.update_passport_data(chat_id, snumber="pending")
            await send_message(
                chat_id,
                "📋 Для получения выплаты необходимо заполнить данные.\n\n"
                "Введите <b>ФИО полностью</b>:",
            )
        else:
            await send_message(
                chat_id,
                "✅ Вы выбрали получение денег. Администратор свяжется с вами для выплаты.",
            )
        return

    if data.startswith("payout_points:"):
        raffle_id = int(data.split(":", 1)[1])
        await answer_callback(cid)
        raffle = None
        async with db.pool.acquire() as conn:
            row = await conn.fetchrow("SELECT * FROM raffles WHERE id = $1", raffle_id)
            if row:
                raffle = dict(row)
        if raffle and raffle.get("prize_amount"):
            points = raffle["prize_amount"] * 10
            await db.set_payout_choice(raffle_id, "points")
            await send_message(
                chat_id,
                f"✅ Выигрыш конвертирован в баллы!\n"
                f"➕ Начислено <b>{points} баллов</b>",
            )
        else:
            await send_message(chat_id, "⚠️ Ошибка конвертации.")
        return

    if data.startswith("accept_consent:"):
        raffle_id = int(data.split(":", 1)[1])
        await answer_callback(cid, "✅ Спасибо!")
        await db.update_user_step(chat_id, "ask_fio")
        await send_message(chat_id, "📋 Введите <b>ФИО полностью</b> (как в паспорте):")
        return

    if data.startswith("cancel_consent:"):
        await answer_callback(cid, "❌ Отменено")
        await db.update_user_step(chat_id, "menu")
        await show_main_menu(db, chat_id, user)
        return

    if data.startswith("confirm_passport:"):
        raffle_id = data.split(":", 1)[1]
        await answer_callback(cid, "✅ Данные сохранены!")
        await db.update_user_step(chat_id, "menu")
        await send_message(
            chat_id,
            "✅ Ваши данные переданы администратору. Ожидайте выплату.",
        )
        if raffle_id and raffle_id.isdigit():
            admin_ids = s["ADMIN_IDS"]
            for aid in admin_ids:
                await send_message(
                    aid,
                    f"📋 <b>Новые данные для выплаты</b>\n"
                    f"Пользователь: <code>{chat_id}</code>\n"
                    f"Розыгрыш: #{raffle_id}\n\n"
                    f"Требуется обработка в админ-панели.",
                )
        return

    if data.startswith("restart_passport:"):
        raffle_id = data.split(":", 1)[1]
        await answer_callback(cid, "🔄 Начнём заново")
        await db.update_user_step(chat_id, "ask_fio")
        await db.update_passport_data(chat_id, fio="", snumber="", inn="")
        await send_message(chat_id, "📋 Введите <b>ФИО полностью</b> (как в паспорте):")
        return

    if data == "cancel_exchange":
        await answer_callback(cid, "❌ Обмен отменён")
        return

    if data.startswith("exchange_prize:"):
        prize_id = int(data.split(":", 1)[1])
        await answer_callback(cid)
        prize = await db.get_prize(prize_id)
        if not prize:
            await send_message(chat_id, "❌ Приз не найден.")
            return
        user_row = await db.get_user(chat_id)
        if not user_row or user_row["balance"] < prize["price_points"]:
            await send_message(chat_id, "❌ Недостаточно баллов для обмена.")
            return
        await db.update_user_balance(chat_id, user_row["balance"] - prize["price_points"])
        await db.add_balance(chat_id, -prize["price_points"], "exchange", f"Обмен на приз «{prize['name']}»")
        order_id = await db.create_order(user_row["id"], prize_id)
        await send_message(
            chat_id,
            f"🎉 <b>Заказ оформлен!</b>\n\n"
            f"Приз: {prize['name']}\n"
            f"Номер заказа: #{order_id}\n\n"
            f"Мы свяжемся с вами для уточнения получения.",
        )
        return

    logger.warning(f"Unknown callback: {data}")


# ─── WebApp Data Handler ───────────────────────────────

async def handle_webapp_data(db, data: str, chat_id: int):
    s = get_settings()
    logger.info(f"handle_webapp_data chat_id={chat_id} data={data!r}")

    if data == "profile":
        await show_profile(db, chat_id)
        return

    if data == "history":
        scans = await db.get_scans(chat_id)
        if not scans:
            await send_message(chat_id, "📭 У вас пока нет сканирований.")
            return
        lines = [f"📅 <b>История сканирований</b>\n"]
        for sc in scans[:20]:
            lines.append(f"• {sc.get('code', '—')} — {sc['scanned_at'].strftime('%d.%m.%Y %H:%M') if sc.get('scanned_at') else '—'}")
        await send_message(chat_id, "\n".join(lines))
        return

    if data == "points_log":
        log = await db.get_points_log(chat_id)
        if not log:
            await send_message(chat_id, "📭 Нет операций с баллами.")
            return
        lines = [f"💰 <b>История баллов</b>\n"]
        for entry in log[:20]:
            sign = "+" if entry["amount"] > 0 else ""
            lines.append(f"{sign}{entry['amount']} — {entry.get('description', '') or entry['type']} ({entry['created_at'].strftime('%d.%m.%Y') if entry.get('created_at') else '—'})")
        await send_message(chat_id, "\n".join(lines))
        return

    if data == "raffles":
        raffles = await db.get_raffle_results()
        if not raffles:
            await send_message(chat_id, "📭 Розыгрышей пока нет.")
            return
        lines = [f"🎰 <b>Результаты розыгрышей</b>\n"]
        for r in raffles[:20]:
            won = "🏆" if r.get("user_won") else ""
            lines.append(f"{won} #{r['id']} — {r.get('winner_name', '—')} — {r['prize_amount']} руб.")
        await send_message(chat_id, "\n".join(lines))
        return

    if data == "/start" or data == "menu":
        await show_main_menu(db, chat_id)
        return

    if data.startswith("exchange:"):
        prize_id = int(data.split(":", 1)[1])
        prize = await db.get_prize(prize_id)
        if not prize:
            await send_message(chat_id, "❌ Приз не найден.")
            return
        user_row = await db.get_user(chat_id)
        bal = user_row["balance"] if user_row else 0
        await send_message(
            chat_id,
            f"🎁 <b>Обмен баллов на приз</b>\n\n"
            f"{prize['name']}\n"
            f"💰 Цена: {prize['price_points']} баллов\n"
            f"💳 Ваш баланс: {bal} баллов\n\n"
            f"Подтвердить обмен?",
            reply_markup=exchange_confirm_keyboard(prize_id),
        )
        return

    # Try to handle as QR code value
    if data and len(data) > 3:
        await handle_start(db, chat_id, await db.get_user(chat_id), data)
        return
