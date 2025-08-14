#!/bin/bash

# Wait for database to be ready
echo "Waiting for database..."
while ! nc -z db 3306; do
  sleep 1
done

echo "Database is ready!"

# Start PHP-FPM in the background
php-fpm -D

# Install composer dependencies if vendor directory doesn't exist
if [ ! -d "vendor" ]; then
    echo "Installing composer dependencies..."
    composer install --no-interaction
fi

# Run migrations
echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

# Create admin user if it doesn't exist (ignore errors if user exists)
echo "Creating admin user..."
php bin/console app:user:create admin@besteller.local AdminPassword123456 || true

echo "Setup complete. PHP-FPM is running."

# Keep container running
tail -f /dev/null
