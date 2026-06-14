#!/bin/sh
set -e

# Run database migrations at runtime
echo "Running database migrations..."
php artisan migrate --force

# Start supervisor
echo "Starting supervisor..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
