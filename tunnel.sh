#!/bin/bash
while true; do
  NEW_URL=$(cloudflared tunnel --url http://localhost:8080 2>&1 | tee /tmp/tunnel.log | grep -o 'https://[a-z-]*\.trycloudflare\.com' | head -1)
  if [ -n "$NEW_URL" ]; then
    echo "[$(date)] Tunnel: $NEW_URL"
    sed -i '' "s|DOMAIN=.*|DOMAIN=${NEW_URL#https://}|" /Users/stambolidi/PhpstormProjects/bot_water/.env
    pkill -f uvicorn 2>/dev/null
    sleep 1
    /Users/stambolidi/PhpstormProjects/bot_water/.venv/bin/uvicorn app.main:app --host 0.0.0.0 --port 8080 > /tmp/bot.log 2>&1 &
    sleep 3
    source /Users/stambolidi/PhpstormProjects/bot_water/.env 2>/dev/null
    curl -s "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook?url=https://${NEW_URL#https://}/webhook" > /dev/null
    echo "[$(date)] Webhook updated"
  fi
  sleep 30
done