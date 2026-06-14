#!/bin/sh
set -e

# Cache configuration and routes at runtime to ensure environment variables are read correctly
echo "Caching config, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations at runtime
echo "Running database migrations..."
php artisan migrate --force

# Start supervisor
echo "Starting supervisor..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
