#!/bin/bash
set -e

# Ensure proper ownership (exclude .git folder)
echo "Setting proper ownership..."
chown -R laravel:laravel /var/www/html/app 2>/dev/null || true
chown -R laravel:laravel /var/www/html/bootstrap 2>/dev/null || true
chown -R laravel:laravel /var/www/html/config 2>/dev/null || true
chown -R laravel:laravel /var/www/html/database 2>/dev/null || true
chown -R laravel:laravel /var/www/html/public 2>/dev/null || true
chown -R laravel:laravel /var/www/html/resources 2>/dev/null || true
chown -R laravel:laravel /var/www/html/routes 2>/dev/null || true
chown -R laravel:laravel /var/www/html/storage 2>/dev/null || true
chown -R laravel:laravel /var/www/html/tests 2>/dev/null || true
chown -R laravel:laravel /var/www/html/vendor 2>/dev/null || true
chown laravel:laravel /var/www/html/.env* 2>/dev/null || true
chown laravel:laravel /var/www/html/composer.* 2>/dev/null || true
chown laravel:laravel /var/www/html/package.* 2>/dev/null || true
chown laravel:laravel /var/www/html/artisan 2>/dev/null || true

# Check if vendor directory exists, if not install dependencies
if [ ! -d "/var/www/html/vendor" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader
fi

# Check if .env exists, if not copy from .env.example
if [ ! -f "/var/www/html/.env" ]; then
    echo "Creating .env file..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Generate application key if not exists
if ! grep -q "APP_KEY=" /var/www/html/.env || [ -z "$(grep APP_KEY /var/www/html/.env | cut -d'=' -f2)" ]; then
    echo "Generating application key..."
    php artisan key:generate
fi

# Run database migrations
echo "Running database migrations..."
# php artisan migrate --force

# Clear and cache config
echo "Clearing and caching configuration..."
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache || echo "WARNING: view:cache failed (missing components), continuing anyway..."

# Set proper permissions
chmod -R 755 /var/www/html/storage
chmod -R 755 /var/www/html/bootstrap/cache
chown -R laravel:laravel /var/www/html/storage
chown -R laravel:laravel /var/www/html/bootstrap/cache

# Execute the main command
# Fix php-fpm user
sed -i 's/^user = www-data/user = laravel/' /usr/local/etc/php-fpm.d/www.conf
sed -i 's/^group = www-data/group = laravel/' /usr/local/etc/php-fpm.d/www.conf

# Fix permissions
chmod -R 777 /var/www/html/storage/
chmod -R 777 /var/www/html/bootstrap/cache/

exec "$@"