#!/bin/sh
set -e

# Ensure bootstrap/cache directory exists and is writable
mkdir -p bootstrap/cache
chmod -R 775 bootstrap/cache

# Cache config, routes, and views at container startup after runtime env is injected.
# Runs here (not at image build time) so that APP_KEY and all env vars are available.
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
