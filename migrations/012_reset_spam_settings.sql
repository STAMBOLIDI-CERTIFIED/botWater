-- Reset all msg_* settings to safe defaults (remove spam injected by attackers)
UPDATE settings SET value = '👋 Добро пожаловать, {name}! 💧' WHERE key = 'msg_welcome';
UPDATE settings SET value = '✅ Сканирование выполнено! +{points} баллов' WHERE key = 'msg_scan_success_1';
UPDATE settings SET value = '✅ Сканирование выполнено! +{points} баллов' WHERE key = 'msg_scan_success_2';
UPDATE settings SET value = '🎁 Вы получили подарок!' WHERE key = 'msg_gift_prompt';
UPDATE settings SET value = '🪙 Ваш баланс: {balance} баллов' WHERE key = 'msg_balance';
UPDATE settings SET value = '📈 Статистика обновлена' WHERE key = 'msg_stats';
UPDATE settings SET value = '💚 Спасибо за пожертвование! +{amount} баллов' WHERE key = 'msg_donation_success';
UPDATE settings SET value = '🥳 <b>Заказ оформлен!</b>\n\nПриз: {name}\nНомер заказа: #{order_id}\n\nМы свяжемся с вами для уточнения получения.' WHERE key = 'msg_exchange_success';
UPDATE settings SET value = '🎉 Уровень повышен! {level}' WHERE key = 'msg_level_up';
UPDATE settings SET value = '🔑 Сканирование партнёра! +{points} баллов' WHERE key = 'msg_partner_scan';
