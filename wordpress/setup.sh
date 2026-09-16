#!/bin/bash
# WaterPrize WordPress Setup Script
# Запуск: chmod +x setup.sh && ./setup.sh

set -e

echo "💧 WaterPrize WordPress Setup"
echo "=============================="

# Check Docker
if ! command -v docker &> /dev/null; then
    echo "❌ Docker не найден. Установите Docker Desktop: https://docker.com/products/docker-desktop"
    exit 1
fi

# Check Docker Compose
if ! docker compose version &> /dev/null; then
    echo "❌ Docker Compose не найден."
    exit 1
fi

echo ""
echo "📦 Запуск Docker контейнеров..."
cd "$(dirname "$0")"
docker compose up -d

echo ""
echo "⏳ Ожидание запуска MySQL..."
sleep 10

echo ""
echo "✅ WordPress запущен!"
echo ""
echo "🌐 Откройте: http://localhost:8080"
echo "   - Пройдите установку WordPress"
echo "   - Установите плагин: WP-плагины → Добавить → Загрузить → выберите папку waterprize-admin"
echo "   - Активируйте плагин"
echo "   - Настройте подключение к БД в WaterPrize → Настройки БД"
echo ""
echo "📊 REST API: http://localhost:8080/wp-json/waterprize/v1/stats"
echo ""
echo "Для остановки: docker compose down"
echo "Для удаления данных: docker compose down -v"
