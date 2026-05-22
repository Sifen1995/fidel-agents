#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if ! command -v composer >/dev/null 2>&1; then
    echo "Composer is required. Run this script inside the app container or install Composer locally."
    exit 1
fi

if [ ! -f composer.lock ] && [ ! -d vendor/laravel/framework ]; then
    echo "==> Installing PHP dependencies..."
    if ! composer install --no-interaction --prefer-dist; then
        echo "==> Retrying composer install with --no-scripts (fix storage/bootstrap/cache permissions, then run: php artisan package:discover)..."
        composer install --no-interaction --prefer-dist --no-scripts
    fi
fi

if [ ! -w bootstrap/cache ] || [ ! -w storage/logs ]; then
    echo ""
    echo "WARNING: bootstrap/cache or storage/logs is not writable by $(whoami)."
    echo "  Host fix: sudo chown -R \"$(whoami):$(whoami)\" storage bootstrap/cache"
    echo "  Or run Composer/Artisan inside Docker after: docker compose up -d"
    echo ""
fi

if [ ! -f .env ]; then
    echo "==> Seeding environment file..."
    cp .env.example .env
fi

if [ -f artisan ] && ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    echo "==> Generating application key..."
    php artisan key:generate --no-interaction
fi

echo "==> Fidel Academy environment skeleton is ready."
echo "    Start services: docker compose up -d --build"
echo "    Ollama endpoint: ${OLLAMA_BASE_URL:-http://host.docker.internal:11434}"
