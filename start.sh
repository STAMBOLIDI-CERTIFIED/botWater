#!/bin/bash
cd /Users/stambolidi/PhpstormProjects/bot_water

# Kill old processes
pkill -f "uvicorn app.main:app" 2>/dev/null
pkill -f cloudflared 2>/dev/null
sleep 1

# Start bot
.venv/bin/uvicorn app.main:app --host 0.0.0.0 --port 8080 --log-level info > /tmp/bot.log 2>&1 &
echo "Бот запущен"

# Start tunnel
cloudflared tunnel --url http://localhost:8080 > /tmp/tunnel.log 2>&1 &
sleep 8

# Get URL and update webhook
URL=$(grep -o 'https://[a-z-]*\.trycloudflare\.com' /tmp/tunnel.log | head -1)
if [ -n "$URL" ]; then
    echo "Туннель: $URL"

    # Update .env
    sed -i '' "s|WEBAPP_URL=.*|WEBAPP_URL=${URL}/index.html|" .env
    sed -i '' "s|DOMAIN=.*|DOMAIN=${URL#https://}|" .env
    sed -i '' "s|ADMIN_PANEL_URL=.*|ADMIN_PANEL_URL=${URL}/admin|" .env

    # Set webhook
    curl -s "https://api.telegram.org/bot$(grep BOT_TOKEN .env | cut -d= -f2)/setWebhook?url=${URL}/webhook" > /dev/null

    # Set menu button
    curl -s -X POST "https://api.telegram.org/bot$(grep BOT_TOKEN .env | cut -d= -f2)/setChatMenuButton" \
        -H "Content-Type: application/json" \
        -d "{\"menu_button\":{\"type\":\"web_app\",\"text\":\"🚀 Открыть\",\"web_app\":{\"url\":\"${URL}/index.html\"}}}" > /dev/null

    echo "Вебхук и меню обновлены"
else
    echo "Ошибка: туннель не запущен"
fi
