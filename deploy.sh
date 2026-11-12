#!/usr/bin/env bash
# Обновяване: изтегля кода, зависимостите, миграциите, кеша.
# На сървъра:   sudo -u restrstat /srv/restrstat/deploy.sh
set -euo pipefail
cd "$(dirname "$0")"
PHP="${PHP:-php}"

[ -f .env ] || { echo "Липсва .env — виж DEPLOY.md." >&2; exit 1; }

if [ -d .git ] && git remote get-url origin >/dev/null 2>&1; then
    git pull --ff-only
fi

composer install --no-dev --optimize-autoloader --no-interaction --quiet

grep -qE '^APP_KEY=.+' .env || "$PHP" artisan key:generate --force

if "$PHP" artisan migrate:status >/dev/null 2>&1; then
    "$PHP" artisan down --retry=30
    trap 'echo "Нещо се обърка. Сайтът е в режим на поддръжка; след поправка: $PHP artisan up" >&2' ERR
fi
"$PHP" artisan migrate --force
"$PHP" artisan optimize
"$PHP" artisan up
echo "Готово."
