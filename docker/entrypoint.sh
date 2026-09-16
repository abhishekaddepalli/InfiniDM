#!/bin/sh
set -e

# Cache configuration & routes if in production
if [ "$APP_ENV" = "production" ]; then
    echo "Running in production mode..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# Ensure storage permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Execute supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
