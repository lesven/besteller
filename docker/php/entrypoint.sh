#!/bin/bash

# Wait for database to be ready
echo "Waiting for database..."
while ! nc -z db 3306; do
  sleep 1
done

echo "Database is ready!"

# Install composer dependencies
if [ ! -d "vendor" ]; then
    composer install --no-dev --optimize-autoloader
fi

# Run migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Create admin user if it doesn't exist
php bin/console app:user:create admin@besteller.local AdminPassword123456 || true

# Start PHP-FPM
php-fpm
