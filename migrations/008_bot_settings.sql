-- ─── Bot Settings: rewards & messages ─────────────────
-- Seed default values (ON CONFLICT DO NOTHING preserves existing overrides)

-- Rewards
INSERT INTO settings (key, value) VALUES
    ('scan_balance', '10'),
    ('scan_xp', '10'),
    ('partner_scan_default', '10'),
    ('gift_min', '10'),
    ('gift_max', '100'),
    ('conversion_multiplier', '10'),
    ('expired_conversion_multiplier', '5'),
    ('level_up_bonus', '0'),
    ('tree_threshold_2', '100'),
    ('tree_threshold_3', '500'),
    ('tree_threshold_4', '1000'),
    ('tree_threshold_5', '2000'),
    ('tree_threshold_6', '5000')
ON CONFLICT (key) DO NOTHING;

-- Messages
INSERT INTO settings (key, value) VALUES
    ('msg_welcome', '💧 <b>Главное меню</b>\n\nПривет, {name}! 👋\n🪙 Баланс: <b>{balance} баллов</b>\n\nСканируйте QR-коды на бутылках и получайте баллы!\nКнопки для быстрого доступа теперь под полем ввода 👇'),
    ('msg_scan_success_1', '🥳 <b>Поздравляем!</b>\n\nВы зарегистрировали бутылку и автоматически стали участником главного конкурса призов.\n\nНо это ещё не всё 👇'),
    ('msg_scan_success_2', '🎀 <b>Для вас доступен моментальный подарок!</b>\n\nОткройте мини-приложение и получите свой первый приз прямо сейчас.\n\nВнутри вас уже ждут случайные баллы, которые можно копить и обменивать на реальные призы.'),
    ('msg_gift_prompt', '🎀 <b>Для вас доступен моментальный подарок!</b>\n\nОткройте мини-приложение и получите свой первый приз прямо сейчас.\n\nВнутри вас уже ждут случайные баллы, которые можно копить и обменивать на реальные призы.'),
    ('msg_balance', '🪙 <b>Ваш баланс:</b> {balance} баллов\n📈 Всего сканирований: {total_scans}'),
    ('msg_stats', '📈 <b>Статистика</b>\n\n🙋 Ваши сканирования: <b>{total_scans}</b>\n🪙 Баллы: <b>{balance}</b>\n📲 Активных QR-кодов: <b>{codes_count}</b>\n🧊 Свободных бутылок: <b>{unassigned}</b>'),
    ('msg_donation_success', '💚 <b>Спасибо за пожертвование!</b>\n\nВы пожертвовали <b>{amount} баллов</b>.\nВаши баллы пойдут на добрые дела и поддержку проектов.'),
    ('msg_exchange_success', '🥳 <b>Заказ оформлен!</b>\n\nПриз: {name}\nНомер заказа: #{order_id}\n\nМы свяжемся с вами для уточнения получения.'),
    ('msg_level_up', '🎉 <b>Поздравляем!</b>\n\nВаше дерево выросло до уровня «{level_name}»!\nПродолжайте сканировать бутылки для роста.')
ON CONFLICT (key) DO NOTHING;
