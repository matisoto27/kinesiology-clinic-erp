#!/bin/sh
set -e

cd /app

until php artisan db:show > /dev/null 2>&1; do
  echo "Waiting..."
  sleep 2
done

if [ -z "$APP_KEY" ]; then
  export APP_KEY="$(php artisan key:generate --show)"
fi

php artisan migrate --force --seed --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port=8000
