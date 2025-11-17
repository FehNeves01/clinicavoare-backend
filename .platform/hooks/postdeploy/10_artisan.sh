#!/bin/bash
set -euo pipefail

cd /var/app/current

php artisan optimize
php artisan migrate --force --no-interaction
php artisan storage:link || true
chmod -R 775 storage bootstrap/cache

